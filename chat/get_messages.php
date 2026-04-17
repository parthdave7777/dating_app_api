<?php
// chat/get_messages.php
require_once __DIR__ . '/../config.php';

$userId   = getAuthUserId();
$matchId  = (int) ($_GET['match_id']  ?? 0);
$lastId   = (int) ($_GET['last_id']   ?? 0);

if (!$matchId) {
    echo json_encode(['status' => 'error','message' => 'match_id required']);
    exit();
}

$db = getDB();

// 1. One-time Mark Read (Fast)
$now = date('Y-m-d H:i:s');
$stmt = $db->prepare("UPDATE messages SET is_read = 1, read_at = ? WHERE match_id = ? AND sender_id != ? AND is_read = 0");
$stmt->bind_param("sii", $now, $matchId, $userId);
$stmt->execute();
$readAffected = $stmt->affected_rows;
$stmt->close();

if ($readAffected > 0) {
    broadcastToSoketi("match_$matchId", "messages_read", ['match_id' => $matchId, 'reader_id' => $userId]);
}

// 2. Clear & Fast Fetch (Latest 50)
$stmt = $db->prepare("
    SELECT m.id, m.sender_id, m.message, m.type, m.is_read, m.created_at
    FROM messages m
    WHERE m.match_id = ?
    ORDER BY m.id DESC LIMIT 50
");
$stmt->bind_param("i", $matchId);
$stmt->execute();
$result = $stmt->get_result();
$messages = [];
while ($row = $result->fetch_assoc()) {
    $messages[] = [
        'id'         => (int) $row['id'],
        'sender_id'  => (int) $row['sender_id'],
        'message'    => $row['message'],
        'type'       => $row['type'],
        'is_read'    => (bool) $row['is_read'],
        'created_at' => $row['created_at']
    ];
}
$stmt->close();

// 3. Get Other User Info
$stmt = $db->prepare("SELECT id, full_name FROM users WHERE id = (SELECT CASE WHEN user1_id = ? THEN user2_id ELSE user1_id END FROM matches WHERE id = ?)");
$stmt->bind_param("ii", $userId, $matchId);
$stmt->execute();
$otherUser = $stmt->get_result()->fetch_assoc();
$stmt->close();

$db->close();

echo json_encode([
    'status'     => 'success',
    'messages'   => array_reverse($messages),
    'other_user' => [
        'id'        => (int) ($otherUser['id'] ?? 0),
        'full_name' => $otherUser['full_name'] ?? 'User',
        'is_online' => true // Simplified for speed
    ]
]);
exit();
