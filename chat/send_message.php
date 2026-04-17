<?php
// chat/send_message.php
require_once __DIR__ . '/../config.php';

$userId  = getAuthUserId();
$body    = json_decode(file_get_contents('php://input'), true);
$matchId = (int) ($body['match_id'] ?? 0);
$message = trim($body['message'] ?? '');
$type    = $body['type'] ?? 'text';

if (!$matchId || empty($message)) {
    echo json_encode(['status' => 'error', 'message' => 'Missing data']);
    exit();
}

$db = getDB();

// 1. Double check match
$stmt = $db->prepare("SELECT user1_id, user2_id FROM matches WHERE id = ?");
$stmt->bind_param("i", $matchId);
$stmt->execute();
$match = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$match) {
    echo json_encode(['status' => 'error', 'message' => 'Match not found']);
    exit();
}

// 2. Fast Insert
$stmt = $db->prepare("INSERT INTO messages (match_id, sender_id, message, type) VALUES (?, ?, ?, ?)");
$stmt->bind_param("iiss", $matchId, $userId, $message, $type);
$stmt->execute();
$msgId = $db->insert_id;
$stmt->close();

$db->query("UPDATE users SET last_active = NOW() WHERE id = $userId");

// 3. Fetch for Broadcast
$msgRow = $db->query("
    SELECT m.*, u.full_name AS sender_name 
    FROM messages m JOIN users u ON u.id = m.sender_id 
    WHERE m.id = $msgId
")->fetch_assoc();

$db->close();

// 4. INSTANT BROADCAST (New 1-Second Logic)
$sharedMessage = [
    'id'          => (int)  $msgRow['id'],
    'sender_id'   => (int)  $msgRow['sender_id'],
    'sender_name' =>        $msgRow['sender_name'],
    'message'     =>        $msgRow['message'],
    'type'        =>        $msgRow['type'],
    'is_read'     => false,
    'created_at'  =>        $msgRow['created_at'],
];

broadcastToSoketi("match_$matchId", "new_message", [
    'message' => $sharedMessage
]);

// 5. Done!
echo json_encode([
    'status'     => 'success',
    'message_id' => $msgId,
    'message'    => $sharedMessage
]);
exit();
