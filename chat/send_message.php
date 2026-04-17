<?php
// chat/send_message.php
require_once __DIR__ . '/../config.php';
$t_total_start = microtime(true);
$profile = [];

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'Method not allowed']);
    exit();
}

// 1. Auth and Payload Prep
$t_auth_start = microtime(true);
$userId  = getAuthUserId();
$body    = json_decode(file_get_contents('php://input'), true);
$matchId = (int)   ($body['match_id'] ?? 0);
$message = trim($body['message']  ?? '');
$type    = in_array($body['type'] ?? '', ['text','image']) ? $body['type'] : 'text';
$profile['auth_and_payload'] = round((microtime(true) - $t_auth_start) * 1000, 2);

if (!$matchId || empty($message)) {
    echo json_encode(['status' => 'error', 'message' => 'match_id and message are required']);
    exit();
}

// 2. Database Connection (High-Precision Timing)
$t_db_conn_start = microtime(true);
$db = getDB();
$profile['db_connection'] = round((microtime(true) - $t_db_conn_start) * 1000, 2);

// 3. User & Match Verification
$t_query_1_start = microtime(true);
$authStmt = $db->prepare("SELECT user1_id, user2_id FROM matches WHERE id = ? AND (user1_id = ? OR user2_id = ?)");
$authStmt->bind_param('iii', $matchId, $userId, $userId);
$authStmt->execute();
$matchRow = $authStmt->get_result()->fetch_assoc();
$authStmt->close();
$profile['query_match_verification'] = round((microtime(true) - $t_query_1_start) * 1000, 2);

if (!$matchRow) {
    $db->close();
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Access denied']);
    exit();
}

// 4. Message Insertion
$t_query_2_start = microtime(true);
$stmt = $db->prepare("INSERT INTO messages (match_id, sender_id, message, type) VALUES (?, ?, ?, ?)");
$stmt->bind_param('iiss', $matchId, $userId, $message, $type);
$stmt->execute();
$msgId = $db->insert_id;
$stmt->close();
$profile['query_insert_message'] = round((microtime(true) - $t_query_2_start) * 1000, 2);

// 5. Fetch Full Message for Response
$t_query_3_start = microtime(true);
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
$profile['query_fetch_result'] = round((microtime(true) - $t_query_3_start) * 1000, 2);

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

// 6. Non-critical Background Worker Spawn
$t_worker_start = microtime(true);
$recipientId = ((int)$matchRow['user1_id'] === $userId) ? (int)$matchRow['user2_id'] : (int)$matchRow['user1_id'];
$workerPayload = [
    'action_type'  => 'new_message', 'match_id' => $matchId, 'recipient_id' => $recipientId,
    'sender_id' => $userId, 'sender_name' => $msgRow['sender_name'],
    'message_text' => $message, 'message_type' => $type, 'message_row' => $sharedMessage
];
$jsonPayload = escapeshellarg(json_encode($workerPayload));
$workerPath = __DIR__ . "/../notifications/async_worker.php";
exec("nohup php $workerPath $jsonPayload > /dev/null 2>&1 < /dev/null &");
$profile['spawn_background_worker'] = round((microtime(true) - $t_worker_start) * 1000, 2);

$db->close();

// 7. Final Response Generation
$total_ms = round((microtime(true) - $t_total_start) * 1000, 2);
$response = json_encode([
    'status'     => 'success',
    'message_id' => $msgId,
    'message'    => $sharedMessage,
    'debug_ms'   => $total_ms,
    'server_profile' => $profile
]);

// EXTREMELY IMPORTANT: Fire and Forget
if (function_exists('fastcgi_finish_request')) {
    header('Content-Type: application/json');
    echo $response;
    fastcgi_finish_request();
} else {
    header('Content-Type: application/json');
    echo $response;
}
exit();
