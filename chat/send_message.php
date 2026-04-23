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

// 3. User & Match Verification + Fetch My Name for response
$authStmt = $db->prepare("
    SELECT m.user1_id, m.user2_id, u.full_name as me_name 
    FROM matches m 
    JOIN users u ON u.id = ? 
    WHERE m.id = ? AND (m.user1_id = ? OR m.user2_id = ?)
");
$authStmt->bind_param('iiii', $userId, $matchId, $userId, $userId);
$authStmt->execute();
$matchRow = $authStmt->get_result()->fetch_assoc();
$authStmt->close();

if (!$matchRow) {
    $db->close();
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Access denied']);
    exit();
}

$now = date('Y-m-d H:i:s');

// 4. Message Insertion
$stmt = $db->prepare("INSERT INTO messages (match_id, sender_id, message, type) VALUES (?, ?, ?, ?)");
$stmt->bind_param('iiss', $matchId, $userId, $message, $type);
$stmt->execute();
$msgId = $db->insert_id;
$stmt->close();

// 5. Construct response object manually (Save 1 DB Query)
$sharedMessage = [
    'id'          => (int)  $msgId,
    'sender_id'   => (int)  $userId,
    'sender_name' =>        $matchRow['me_name'],
    'message'     =>        $message,
    'type'        =>        $type,
    'is_read'     => false,
    'is_saved'    => 0,
    'is_deleted'  => false,
    'is_edited'   => false,
    'created_at'  =>        $now,
];

// === NITRO FINISH (Response to App Immediately) ===
$responseData = json_encode([
    'status'     => 'success',
    'message_id' => $msgId,
    'message'    => $sharedMessage
]);

ignore_user_abort(true);
set_time_limit(30);

// Close the connection so the mobile app finishes the request immediately
ob_start();
echo $responseData;
$size = ob_get_length();
header("Content-Length: $size");
header("Connection: close");
header("Content-Encoding: none");
ob_end_flush();
flush();

if (function_exists('fastcgi_finish_request')) {
    fastcgi_finish_request();
}

// === BACKGROUND TASKS (Notifications) ===
// 6. Non-critical Background Dispatch (NITRO Queue)
$recipientId = ((int)$matchRow['user1_id'] === $userId) ? (int)$matchRow['user2_id'] : (int)$matchRow['user1_id'];
$workerPayload = [
    'action_type'  => 'new_message', 'match_id' => $matchId, 'recipient_id' => $recipientId,
    'sender_id' => $userId, 'sender_name' => $matchRow['me_name'],
    'message_text' => $message, 'message_type' => $type, 'message_row' => $sharedMessage
];

dispatchAsync($workerPayload);

$db->close();
exit();
