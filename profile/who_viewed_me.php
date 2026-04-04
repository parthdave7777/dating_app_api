<?php
// profile/who_viewed_me.php
require_once __DIR__ . '/../config.php';

$userId = getAuthUserId();
$db     = getDB();

$stmt = $db->prepare("
    SELECT pv.viewer_id, pv.viewed_at,
           u.full_name, u.age, u.city, u.is_verified,
           (SELECT photo_url FROM user_photos
            WHERE user_id = u.id AND is_dp = 1 LIMIT 1) AS dp_url
    FROM profile_views pv
    JOIN users u ON u.id = pv.viewer_id
    WHERE pv.viewed_id = ?
    ORDER BY pv.viewed_at DESC
    LIMIT 50
");
$stmt->bind_param('i', $userId);
$stmt->execute();
$result = $stmt->get_result();
$stmt->close();

$viewers = [];
while ($row = $result->fetch_assoc()) {
    $viewers[] = [
        'user_id'     => (int) $row['viewer_id'],
        'full_name'   =>       $row['full_name'],
        'age'         => (int) $row['age'],
        'city'        =>       $row['city'],
        'is_verified' => (bool)$row['is_verified'],
        'dp_url'      =>       $row['dp_url'],
        'viewed_at'   =>       $row['viewed_at'],
    ];
}

$db->close();
echo json_encode(['status' => 'success', 'viewers' => $viewers]);
