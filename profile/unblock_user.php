<?php
require_once '../config.php';

$userId = getAuthUserId();
$data = json_decode(file_get_contents('php://input'), true);
$targetId = (int)($data['target_id'] ?? 0);

if (!$targetId) {
    die(json_encode(['status' => 'error', 'message' => 'Target ID is required']));
}

$db = getDB();

try {
    $stmt = $db->prepare("DELETE FROM blocks WHERE blocker_id = ? AND blocked_user_id = ?");
    $stmt->bind_param("ii", $userId, $targetId);
    $stmt->execute();

    echo json_encode(['status' => 'success', 'message' => 'User unblocked successfully']);
} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
?>
