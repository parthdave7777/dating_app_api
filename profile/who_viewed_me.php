<?php
// profile/who_viewed_me.php
require_once __DIR__ . '/../config.php';

$userId = getAuthUserId();
$db     = getDB();

$stmt = $db->prepare("
    SELECT pv.viewer_id, pv.viewed_at,
           u.full_name, u.age, u.city, u.is_verified,
           (SELECT photo_url FROM user_photos
            WHERE user_id = u.id AND is_dp = 1 LIMIT 1) AS dp_url,
           (SELECT COUNT(*) FROM unlocked_profiles WHERE user_id = ? AND target_id = pv.viewer_id) as is_unlocked,
           (SELECT COUNT(*) FROM matches WHERE (user1_id = ? AND user2_id = u.id) OR (user1_id = u.id AND user2_id = ?)) as is_match
    FROM profile_views pv
    JOIN users u ON u.id = pv.viewer_id
    WHERE pv.viewed_id = ?
    ORDER BY pv.viewed_at DESC
    LIMIT 50
");
$stmt->bind_param('iiii', $userId, $userId, $userId, $userId);
$stmt->execute();
$result = $stmt->get_result();
$stmt->close();

$viewers = [];
while ($row = $result->fetch_assoc()) {
    $isUnlocked = ($row['is_unlocked'] > 0 || $row['is_match'] > 0);
    $viewers[] = [
        'user_id'     => (int) $row['viewer_id'],
        'full_name'   =>       $row['full_name'],
        'age'         => (int) $row['age'],
        'city'        =>       $row['city'],
        'is_verified' => (bool)$row['is_verified'],
        'dp_url'      =>       $row['dp_url'],
        'viewed_at'   =>       $row['viewed_at'],
        'is_blurred'  =>       !$isUnlocked,
    ];
}

$db->close();
echo json_encode(['status' => 'success', 'viewers' => $viewers]);
