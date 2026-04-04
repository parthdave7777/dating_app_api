<?php
// profile/report_user.php
require_once __DIR__ . '/../config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'Method not allowed']);
    exit();
}

$userId = getAuthUserId();
$body   = json_decode(file_get_contents('php://input'), true);

$reportedId  = (int)   ($body['reported_user_id'] ?? 0);
$reason      = trim($body['reason']       ?? '');
$description = trim($body['description']  ?? '');

if (!$reportedId || empty($reason)) {
    echo json_encode(['status' => 'error', 'message' => 'reported_user_id and reason are required']);
    exit();
}

$db   = getDB();
$stmt = $db->prepare(
    "INSERT INTO reports (reporter_id, reported_user_id, reason, description) VALUES (?, ?, ?, ?)"
);
$stmt->bind_param('iiss', $userId, $reportedId, $reason, $description);
$stmt->execute();
$stmt->close();
$db->close();

echo json_encode(['status' => 'success', 'message' => 'Report submitted']);
