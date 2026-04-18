<?php
// chat/send_message.php
require_once __DIR__ . '/../config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'Method not allowed']);
    exit();
}

// 1. Auth and Payload Prep
$userId  = getAuthUserId();
$body    = json_decode(file_get_contents('php://input'), true);
$matchId = (int)   ($body['match_id'] ?? 0);
$message = trim($body['message']  ?? '');
$type    = in_array($body['type'] ?? '', ['text','image']) ? $body['type'] : 'text';

if (!$matchId || empty($message)) {
    echo json_encode(['status' => 'error', 'message' => 'match_id and message are required']);
    exit();
}

// 2. Database Connection
$db = getDB();

// 3. User & Match Verification
$authStmt = $db->prepare("SELECT user1_id, user2_id FROM matches WHERE id = ? AND (user1_id = ? OR user2_id = ?)");
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

// 4. Message Insertion
$stmt = $db->prepare("INSERT INTO messages (match_id, sender_id, message, type) VALUES (?, ?, ?, ?)");
$stmt->bind_param('iiss', $matchId, $userId, $message, $type);
$stmt->execute();
$msgId = $db->insert_id;
$stmt->close();

// 5. Fetch Full Message for Response
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

$sharedMessage = [
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
];

// 6. Non-critical Background Dispatch (NITRO Queue)
$recipientId = ((int)$matchRow['user1_id'] === $userId) ? (int)$matchRow['user2_id'] : (int)$matchRow['user1_id'];
$workerPayload = [
    'action_type'  => 'new_message', 'match_id' => $matchId, 'recipient_id' => $recipientId,
    'sender_id' => $userId, 'sender_name' => $msgRow['sender_name'],
    'message_text' => $message, 'message_type' => $type, 'message_row' => $sharedMessage
];
dispatchAsync($workerPayload);

$db->close();

// 7. Respond to mobile app INSTANTLY
$response = json_encode([
    'status'     => 'success',
    'message_id' => $msgId,
    'message'    => $sharedMessage
]);

if (function_exists('fastcgi_finish_request')) {
    header('Content-Type: application/json');
    echo $response;
    fastcgi_finish_request();
} else {
    header('Content-Type: application/json');
    echo $response;
}
exit();
