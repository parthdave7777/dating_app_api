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
    // 1. Record the report
    $stmt = $db->prepare("INSERT INTO reports (reporter_id, reported_user_id, reason, description) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("iiss", $userId, $targetId, $reason, $description);
    $stmt->execute();

    // 2. Also block and unmatch automatically when reported
    $stmt = $db->prepare("INSERT IGNORE INTO blocks (blocker_id, blocked_user_id) VALUES (?, ?)");
    $stmt->bind_param("ii", $userId, $targetId);
    $stmt->execute();

    // 3. Remove all messages (MUST do this before deleting the match record)
    $stmt = $db->prepare("DELETE FROM messages WHERE match_id IN (SELECT id FROM matches WHERE (user1_id = ? AND user2_id = ?) OR (user1_id = ? AND user2_id = ?))");
    $stmt->bind_param("iiii", $userId, $targetId, $targetId, $userId);
    $stmt->execute();

    // 4. Remove any existing match between them
    $stmt = $db->prepare("DELETE FROM matches WHERE (user1_id = ? AND user2_id = ?) OR (user1_id = ? AND user2_id = ?)");
    $stmt->bind_param("iiii", $userId, $targetId, $targetId, $userId);
    $stmt->execute();

    // 5. Remove any likes/swipes
    $stmt = $db->prepare("DELETE FROM swipes WHERE (swiper_id = ? AND swiped_id = ?) OR (swiper_id = ? AND swiped_id = ?)");
    $stmt->bind_param("iiii", $userId, $targetId, $targetId, $userId);
    $stmt->execute();

    echo json_encode(['status' => 'success', 'message' => 'Report submitted successfully']);
} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
?>
