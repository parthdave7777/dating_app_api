<?php
// chat/edit_message.php
header('Content-Type: application/json');
require_once __DIR__ . '/../config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'Method not allowed']);
    exit();
}

$userId    = getAuthUserId();
$body      = json_decode(file_get_contents('php://input'), true) ?? [];
$messageId = (int)($body['message_id'] ?? 0);
$newText   = trim($body['message'] ?? '');

if ($messageId <= 0 || $newText === '') {
    echo json_encode(['status' => 'error', 'message' => 'message_id and non-empty message are required']);
    exit();
}

$db = getDB();

// ── 1. Fetch current message state ───────────────────────────
// We select only columns we are sure exist or handle their absence.
$stmt = $db->prepare("SELECT id, sender_id, type FROM messages WHERE id = ?");
$stmt->bind_param('i', $messageId);
$stmt->execute();
$msgRow = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$msgRow) {
    echo json_encode(['status' => 'error', 'message' => 'Message not found']);
    $db->close();
    exit();
}

if ((int)$msgRow['sender_id'] !== $userId) {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized: You did not send this message']);
    $db->close();
    exit();
}

// Ensure it's a text message
if ($msgRow['type'] !== 'text') {
    echo json_encode(['status' => 'error', 'message' => 'Only text messages can be edited']);
    $db->close();
    exit();
}

// ── 2. Update the message ─────────────────────────────────────
// We try to update is_edited if the column exists.
$query = "UPDATE messages SET message = ?, is_edited = 1 WHERE id = ? AND sender_id = ?";
try {
    $upd = $db->prepare($query);
    $upd->bind_param('sii', $newText, $messageId, $userId);
    $upd->execute();
    
    if ($upd->affected_rows === 0) {
       // Maybe is_edited column is missing? Try fallback without it.
       $upd->close();
       $db->prepare("UPDATE messages SET message = ? WHERE id = ? AND sender_id = ?")
          ->execute([$newText, $messageId, $userId]);
    } else {
       $upd->close();
    }
} catch (Exception $e) {
    // Column likely missing, run the simple update
    $upd = $db->prepare("UPDATE messages SET message = ? WHERE id = ? AND sender_id = ?");
    $upd->bind_param('sii', $newText, $messageId, $userId);
    $upd->execute();
    $upd->close();
}

$db->close();

echo json_encode([
    'status'     => 'success',
    'message_id' => $messageId,
    'message'    => $newText,
    'is_edited'  => true,
]);
