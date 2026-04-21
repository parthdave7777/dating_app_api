<?php
require_once '../config.php';

$userId = getAuthUserId();
$data = json_decode(file_get_contents('php://input'), true);
$targetId = (int)($data['target_id'] ?? 0);
$reason = $data['reason'] ?? 'Other';
$description = $data['description'] ?? '';

if (!$targetId) {
    die(json_encode(['status' => 'error', 'message' => 'Target ID is required']));
}

$db = getDB();

try {
    $stmt = $db->prepare("INSERT INTO reports (reporter_id, reported_user_id, reason, description) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("iiss", $userId, $targetId, $reason, $description);
    $stmt->execute();

    // Also block and unmatch automatically when reported
    $stmt = $db->prepare("INSERT IGNORE INTO blocks (blocker_id, blocked_user_id) VALUES (?, ?)");
    $stmt->bind_param("ii", $userId, $targetId);
    $stmt->execute();

    $stmt = $db->prepare("DELETE FROM matches WHERE (user1_id = ? AND user2_id = ?) OR (user1_id = ? AND user2_id = ?)");
    $stmt->bind_param("iiii", $userId, $targetId, $targetId, $userId);
    $stmt->execute();

    $stmt = $db->prepare("DELETE FROM swipes WHERE (swiper_id = ? AND swiped_id = ?) OR (swiper_id = ? AND swiped_id = ?)");
    $stmt->bind_param("iiii", $userId, $targetId, $targetId, $userId);
    $stmt->execute();

    $stmt = $db->prepare("DELETE FROM messages WHERE (sender_id = ? AND receiver_id = ?) OR (sender_id = ? AND receiver_id = ?)");
    $stmt->bind_param("iiii", $userId, $targetId, $targetId, $userId);
    $stmt->execute();

    echo json_encode(['status' => 'success', 'message' => 'Report submitted successfully']);
} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
?>
