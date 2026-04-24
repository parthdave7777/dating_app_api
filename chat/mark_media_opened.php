<?php
require_once __DIR__ . '/../config.php';

// Set JSON header immediately
header('Content-Type: application/json');

$userId = getAuthUserId();
$body = json_decode(file_get_contents('php://input'), true) ?? [];
$msgId = (int)($body['message_id'] ?? 0);

if (!$msgId) {
    echo json_encode(['status' => 'error', 'message' => 'Missing message_id']);
    exit;
}

$db = getDB();

// Ensure the user is part of the match that owns this message
// This is more robust check
// ── Fetch match_id for broadcast ─────────────────────────────
$stmtMatch = $db->prepare("SELECT match_id FROM messages WHERE id = ?");
$stmtMatch->bind_param('i', $msgId);
$stmtMatch->execute();
$matchId = $stmtMatch->get_result()->fetch_assoc()['match_id'] ?? 0;
$stmtMatch->close();

$stmt = $db->prepare("
    UPDATE messages 
    SET type = CASE 
        WHEN type = 'image_view_once' THEN 'image_opened' 
        WHEN type = 'video_view_once' THEN 'video_opened' 
        ELSE type 
    END, 
    is_opened = 1,
    opened_at = NOW(),
    message = 'OPENED'
    WHERE id = ? AND match_id IN (
        SELECT id FROM matches WHERE user1_id = ? OR user2_id = ?
    )
");

$stmt->bind_param('iii', $msgId, $userId, $userId);
$stmt->execute();
$affected = $stmt->affected_rows;
$stmt->close();

if ($affected > 0 && $matchId > 0) {
    dispatchAsync([
        'action_type' => 'media_opened',
        'match_id'    => $matchId,
        'message_id'  => $msgId
    ]);
}

$db->close();

echo json_encode(['status' => 'success']);
