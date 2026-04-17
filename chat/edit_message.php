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
$stmt = $db->prepare("SELECT id, match_id, sender_id, type FROM messages WHERE id = ?");
$stmt->bind_param('i', $messageId);
$stmt->execute();
$msgRow = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$msgRow) {
    echo json_encode(['status' => 'error', 'message' => 'Message not found']);
    $db->close();
    exit();
}

$matchId = (int)$msgRow['match_id'];

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
$upd = $db->prepare("UPDATE messages SET message = ?, is_edited = 1 WHERE id = ? AND sender_id = ?");
$upd->bind_param('sii', $newText, $messageId, $userId);
$upd->execute();
$upd->close();

// ── 3. ASYNC BROADCAST (NITRO) ───────────────────────────────
$workerPayload = [
    'action_type' => 'message_edited',
    'match_id'    => $matchId,
    'message_id'  => $messageId,
    'new_text'    => $newText
];
$jsonPayload = escapeshellarg(json_encode($workerPayload));
$workerPath  = __DIR__ . "/../notifications/async_worker.php";
exec("nohup php $workerPath $jsonPayload > /dev/null 2>&1 < /dev/null &");

$db->close();

echo json_encode([
    'status'     => 'success',
    'message_id' => $messageId,
    'message'    => $newText,
    'is_edited'  => true,
]);
