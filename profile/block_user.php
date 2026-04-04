<?php
// profile/block_user.php
require_once __DIR__ . '/../config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'Method not allowed']);
    exit();
}

$userId   = getAuthUserId();
$body     = json_decode(file_get_contents('php://input'), true);
$blockedId = (int) ($body['blocked_user_id'] ?? 0);

if (!$blockedId) {
    echo json_encode(['status' => 'error', 'message' => 'blocked_user_id required']);
    exit();
}

$db   = getDB();
$stmt = $db->prepare(
    "INSERT IGNORE INTO blocks (blocker_id, blocked_user_id) VALUES (?, ?)"
);
$stmt->bind_param('ii', $userId, $blockedId);
$stmt->execute();
$stmt->close();
$db->close();

echo json_encode(['status' => 'success', 'message' => 'User blocked']);
