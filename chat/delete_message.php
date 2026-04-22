<?php
require_once __DIR__ . '/../config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'Method not allowed']);
    exit();
}

$userId    = getAuthUserId();
$body      = json_decode(file_get_contents('php://input'), true);
$messageId = (int)($body['message_id'] ?? 0);

if (!$messageId) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid message_id']);
    exit();
}

$db   = getDB();
$stmt = $db->prepare("SELECT sender_id, type, message, is_deleted FROM messages WHERE id = ?");
$stmt->bind_param('i', $messageId);
$stmt->execute();
$msg = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$msg) {
    $db->close();
    echo json_encode(['status' => 'error', 'message' => 'Message not found']);
    exit();
}

if ((int)$msg['sender_id'] !== $userId) {
    $db->close();
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit();
}

if ((int)$msg['is_deleted'] === 1) {
    $db->close();
    echo json_encode(['status' => 'error', 'message' => 'Already deleted']);
    exit();
}

// Delete image file from disk if type is image
if ($msg['type'] === 'image') {
    $path = UPLOAD_DIR . basename($msg['message']);
    if (file_exists($path)) unlink($path);
}

// Soft delete — keep the row, mark as deleted
$upd = $db->prepare(
    "UPDATE messages SET
        is_deleted = 1,
        deleted_by = ?,
        message    = 'This message was deleted',
        deleted_at = NOW()
     WHERE id = ?"
);
$upd->bind_param('ii', $userId, $messageId);
$upd->execute();
$upd->close();

// ── BROADCAST DELETION (REAL-TIME) ──────────────────────────
// Fetch match_id for broadcast
$miStmt = $db->prepare("SELECT match_id FROM messages WHERE id = ?");
$miStmt->bind_param('i', $messageId);
$miStmt->execute();
$mRow = $miStmt->get_result()->fetch_assoc();
$miStmt->close();

if ($mRow) {
    dispatchAsync([
        'action_type' => 'messages_read', // Re-using read logic to trigger a full refresh on other clients
        'match_id'    => (int)$mRow['match_id'],
        'reader_id'   => $userId
    ]);
}
$db->close();

echo json_encode([
    'status'     => 'success',
    'message_id' => $messageId,
    'message'    => 'This message was deleted',
]);
