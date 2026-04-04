<?php
require_once __DIR__ . '/../config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'Method not allowed']);
    exit();
}

$userId  = getAuthUserId();
$body    = json_decode(file_get_contents('php://input'), true);
$matchId = (int) ($body['match_id'] ?? 0);

if (!$matchId) {
    echo json_encode(['status' => 'error', 'message' => 'match_id required']);
    exit();
}

$db = getDB();

// Verify the requesting user is part of this match
$check = $db->prepare(
    "SELECT id FROM matches WHERE id = ? AND (user1_id = ? OR user2_id = ?)"
);
$check->bind_param('iii', $matchId, $userId, $userId);
$check->execute();
if ($check->get_result()->num_rows === 0) {
    $check->close();
    $db->close();
    echo json_encode(['status' => 'error', 'message' => 'Match not found']);
    exit();
}
$check->close();

// Delete messages first (foreign key constraint)
$delMsg = $db->prepare("DELETE FROM messages WHERE match_id = ?");
$delMsg->bind_param('i', $matchId);
$delMsg->execute();
$delMsg->close();

// Delete the match
$delMatch = $db->prepare("DELETE FROM matches WHERE id = ?");
$delMatch->bind_param('i', $matchId);
$delMatch->execute();
$delMatch->close();

$db->close();
echo json_encode(['status' => 'success', 'message' => 'Unmatched successfully']);
