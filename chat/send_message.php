<?php
// chat/send_message.php
require_once __DIR__ . '/../config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'Method not allowed']);
    exit();
}

$userId  = getAuthUserId();
$body    = json_decode(file_get_contents('php://input'), true);
$matchId = (int)   ($body['match_id'] ?? 0);
$message = trim($body['message']  ?? '');
$type    = in_array($body['type'] ?? '', ['text','image']) ? $body['type'] : 'text';

if (!$matchId || empty($message)) {
    echo json_encode(['status' => 'error', 'message' => 'match_id and message are required']);
    exit();
}

$db = getDB();

// 1. Verify user is in this match
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

// 2. Insert message
$stmt = $db->prepare(
    "INSERT INTO messages (match_id, sender_id, message, type) VALUES (?, ?, ?, ?)"
);
$stmt->bind_param('iiss', $matchId, $userId, $message, $type);
$stmt->execute();
$msgId = $db->insert_id;
$stmt->close();

$db->query("UPDATE users SET last_active = NOW() WHERE id = $userId");

// 3. Fetch full message object
$msgStmt = $db->prepare("
    SELECT m.*, u.full_name AS sender_name,
           (SELECT photo_url FROM user_photos WHERE user_id = m.sender_id AND is_dp = 1 LIMIT 1) AS sender_photo
    FROM messages m JOIN users u ON u.id = m.sender_id
    WHERE m.id = ?
");
$msgStmt->bind_param('i', $msgId);
$msgStmt->execute();
$msgRow = $msgStmt->get_result()->fetch_assoc();
$msgStmt->close();

$recipientId = ((int)$matchRow['user1_id'] === $userId) ? (int)$matchRow['user2_id'] : (int)$matchRow['user1_id'];
$senderName = $msgRow['sender_name'] ?? 'New message';
$msgPreview = $type === 'image' ? '📷 Photo' : $message;

// 4. TRIGGER REAL-TIME BROADCAST (SOKETI)
$socketData = [
    'message' => [
        'id'          => (int)  $msgRow['id'],
        'sender_id'   => (int)  $msgRow['sender_id'],
        'sender_name' =>        $msgRow['sender_name'],
        'message'     =>        $msgRow['message'],
        'type'        =>        $msgRow['type'],
        'is_read'     => false,
        'is_saved'    => 0,
        'is_deleted'  => false,
        'is_edited'   => false,
        'created_at'  =>        $msgRow['created_at'],
    ]
];
broadcastToSoketi("match_$matchId", "new_message", $socketData);

// 5. TRIGGER BACKGROUND NOTIFICATION (FCM)
require_once __DIR__ . '/../notifications/send_push.php';
sendPush($db, $recipientId, 'message', $senderName, $msgPreview, [
    'match_id'  => (string)$matchId,
    'sender_id' => (string)$userId,
]);

$db->close();

// 6. Respond to mobile app
echo json_encode([
    'status'     => 'success',
    'message_id' => $msgId,
    'message'    => $socketData['message']
]);
exit();
