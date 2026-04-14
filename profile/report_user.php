<?php
require_once '../config.php';

$userId = getAuthUserId();
$targetId = (int)($_POST['target_id'] ?? 0);
$reason = $_POST['reason'] ?? 'Other';
$description = $_POST['description'] ?? '';

if (!$targetId) {
    die(json_encode(['status' => 'error', 'message' => 'Target ID is required']));
}

$db = getDB();

try {
    $stmt = $db->prepare("INSERT INTO reports (reporter_id, reported_user_id, reason, description) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("iiss", $userId, $targetId, $reason, $description);
    $stmt->execute();

    echo json_encode(['status' => 'success', 'message' => 'Report submitted successfully']);
} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
?>
