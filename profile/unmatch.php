<?php
// profile/unmatch.php
require_once __DIR__ . '/../config.php';

header('Content-Type: application/json');

$userId = getAuthUserId();
if (!$userId) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit();
}

$targetId = isset($_POST['target_id']) ? (int)$_POST['target_id'] : 0;

if (!$targetId) {
    echo json_encode(['status' => 'error', 'message' => 'Target user ID required']);
    exit();
}

$db = getDB();

// 1. Delete from matches
$stmt1 = $db->prepare("DELETE FROM matches WHERE (user1_id = ? AND user2_id = ?) OR (user1_id = ? AND user2_id = ?)");
$stmt1->bind_param('iiii', $userId, $targetId, $targetId, $userId);
$stmt1->execute();
$stmt1->close();

// 2. Delete all messages
$stmt2 = $db->prepare("DELETE FROM messages WHERE (sender_id = ? AND receiver_id = ?) OR (sender_id = ? AND receiver_id = ?)");
$stmt2->bind_param('iiii', $userId, $targetId, $targetId, $userId);
$stmt2->execute();
$stmt2->close();

// 3. Delete swipes (likes) to ensure they are fully disconnected
$stmt3 = $db->prepare("DELETE FROM swipes WHERE (swiper_id = ? AND swiped_id = ?) OR (swiper_id = ? AND swiped_id = ?)");
$stmt3->bind_param('iiii', $userId, $targetId, $targetId, $userId);
$stmt3->execute();
$stmt3->close();

// Clear cache for both users
clearProfileCache($userId);
clearProfileCache($targetId);

echo json_encode([
    'status' => 'success',
    'message' => 'Unmatched successfully. All conversation records have been removed.'
]);

$db->close();
