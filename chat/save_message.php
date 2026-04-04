<?php
// chat/save_message.php — toggle saved state, both users can save any message
require_once __DIR__ . '/../config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'Method not allowed']);
    exit();
}

$userId    = getAuthUserId();
$body      = json_decode(file_get_contents('php://input'), true);
$messageId = (int) ($body['message_id'] ?? 0);

if (!$messageId) {
    echo json_encode(['status' => 'error', 'message' => 'message_id required']);
    exit();
}

$db = getDB();

// Verify the user belongs to the match this message is in (sender OR recipient)
$stmt = $db->prepare("
    SELECT msg.id, msg.is_saved
    FROM messages msg
    JOIN matches m ON m.id = msg.match_id
    WHERE msg.id = ?
      AND (m.user1_id = ? OR m.user2_id = ?)
");
$stmt->bind_param('iii', $messageId, $userId, $userId);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$row) {
    $db->close();
    echo json_encode(['status' => 'error', 'message' => 'Not found or denied']);
    exit();
}

// Toggle — no restriction on who can save
$update = $db->prepare("UPDATE messages SET is_saved = NOT is_saved WHERE id = ?");
$update->bind_param('i', $messageId);
$update->execute();
$update->close();

$newSaved = $row['is_saved'] ? 0 : 1;
$db->close();

echo json_encode(['status' => 'success', 'is_saved' => $newSaved]);
