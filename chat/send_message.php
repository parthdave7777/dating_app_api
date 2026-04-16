<?php
// chat/send_message.php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../notifications/pusher_config.php';

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

// ─── BACKGROUND PUSH TRIGGER (The "Linux Stealth" Method) ───
// We trigger the notification script as a separate process and move on immediately.
$phpPath = "php"; // Common path on Railway
$scriptPath = __DIR__ . "/../notifications/async_push_worker.php";
$jsonPayload = escapeshellarg(json_encode([
    'recipient_id' => $recipientId,
    'type'         => 'message',
    'title'        => $senderName,
    'body'         => $msgPreview,
    'data'         => [
        'match_id'  => (string)$matchId,
        'sender_id' => (string)$userId,
    ]
]));

// This command says: "Start this PHP script, give it this data, and don't wait for it!"
$cmd = "$phpPath $scriptPath $jsonPayload > /dev/null 2>&1 &";
@shell_exec($cmd);

// ─── BROADCAST VIA SOKETI (Instant!) ───
broadcastToSoketi('match_' . $matchId, 'new_message', [
    'message' => $msgRow
]);

$db->close();

// --- SEND SUCCESS RESPONSE TO PHONE ---
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
exit();
