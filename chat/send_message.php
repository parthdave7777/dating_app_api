<?php
// chat/send_message.php
require_once __DIR__ . '/../config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'Method not allowed']);
    exit();
}

$userId  = getAuthUserId();
$body    = json_decode(file_get_contents('php://input'), true);
$matchId  = (int)   ($body['match_id'] ?? 0);
$message  = trim($body['message']  ?? '');
$type     = in_array($body['type'] ?? '', ['text','image']) ? $body['type'] : 'text';
$replyId  = isset($body['reply_to_id']) ? (int) $body['reply_to_id'] : null;

if (!$matchId) {
    echo json_encode(['status' => 'error', 'message' => 'match_id required']);
    exit();
}

if ($message === '') {
    echo json_encode(['status' => 'error', 'message' => 'Message cannot be empty']);
    exit();
}

$db = getDB();

// Verify user is in this match
$authStmt = $db->prepare(
    "SELECT user1_id, user2_id FROM matches WHERE id = ? AND (user1_id = ? OR user2_id = ?)"
);
$authStmt->bind_param('iii', $matchId, $userId, $userId);
$authStmt->execute();
$matchRow = $authStmt->get_result()->fetch_assoc();
$authStmt->close();

if (!$matchRow) {
    $db->close();
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Access denied']);
    exit();
}

// Insert message
$stmt = $db->prepare(
    "INSERT INTO messages (match_id, sender_id, message, type, reply_to_id) VALUES (?, ?, ?, ?, ?)"
);
$stmt->bind_param('iissi', $matchId, $userId, $message, $type, $replyId);
$stmt->execute();
$msgId = $db->insert_id;
$stmt->close();

$db->query("UPDATE users SET last_active = NOW() WHERE id = $userId");

// Fetch inserted message to return full object
$msgStmt = $db->prepare("
    SELECT m.*, u.full_name AS sender_name,
           (SELECT photo_url FROM user_photos WHERE user_id = m.sender_id AND is_dp = 1 LIMIT 1) AS sender_photo,
           rm.message AS reply_text
    FROM messages m 
    JOIN users u ON u.id = m.sender_id
    LEFT JOIN messages rm ON rm.id = m.reply_to_id
    WHERE m.id = ?
");
$msgStmt->bind_param('i', $msgId);
$msgStmt->execute();
$msgRow = $msgStmt->get_result()->fetch_assoc();
$msgStmt->close();

// --- SPEED OPTIMIZATION: Send response to user immediately, then process push in background ---
ob_start();

echo json_encode([
    'status'     => 'success',
    'message_id' => $msgId,
    'message'    => [
        'id'          => (int)  $msgRow['id'],
        'sender_id'   => (int)  $msgRow['sender_id'],
        'sender_name' =>        $msgRow['sender_name'],
        'message'     =>        $msgRow['message'],
        'type'         =>        $msgRow['type'],
        'is_read'      => false,
        'is_received'  => false,
        'is_view_once' => false,
        'is_opened'    => false,
        'is_saved'     => 0,
        'is_deleted'   => false,
        'is_edited'    => false,
        'created_at'   =>        $msgRow['created_at'],
    ],
]);

// Finalize the response and close the connection for the client
$size = ob_get_length();
header("Content-Length: $size");
header("Connection: close");
ob_end_flush();
flush();

// Only if running with PHP-FPM (Production servers)
if (function_exists('fastcgi_finish_request')) {
    fastcgi_finish_request();
}

// ─── BACKGROUND WORK STARTS HERE ───
// The user has already received the "success" message on their phone.
// Now we take our time to talk to Google/Firebase.

// Push notification to the other user
$recipientId = ((int)$matchRow['user1_id'] === $userId)
    ? (int) $matchRow['user2_id']
    : (int) $matchRow['user1_id'];

require_once __DIR__ . '/../notifications/send_push.php';
$senderName = $msgRow['sender_name'] ?? 'New message';
$msgPreview = $type === 'image' ? '📷 Photo' : ($type === 'video' ? '🎥 Video' : $message);

sendPush($db, $recipientId, 'message', $senderName, $msgPreview, [
    'match_id'  => (string)$matchId,
    'title'     => $senderName,
    'body'      => $msgPreview,
    'sender_id' => (string)$userId,
]);

$db->close();
exit();
