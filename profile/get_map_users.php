<?php
// profile/get_map_users.php — Dedicated Map Fetch (Higher limit, includes matches)
require_once __DIR__ . '/../config.php';

$userId = getAuthUserId();
$db = getDB();

$meStmt = $db->prepare("SELECT latitude, longitude, discovery_max_dist FROM users WHERE id = ?");
$meStmt->bind_param('i', $userId);
$meStmt->execute();
$me = $meStmt->get_result()->fetch_assoc();
$meStmt->close();

$myLat    = (float)($me['latitude'] ?? 0);
$myLng    = (float)($me['longitude'] ?? 0);
$maxDist  = (int)($me['discovery_max_dist'] ?? 50);
$hasCoords = ($myLat != 0 && $myLng != 0);

if (!$hasCoords) {
    echo json_encode(['status' => 'success', 'users' => []]);
    exit();
}

// Map specific SQL: Includes matches, higher limit (150)
$distSql = "6371 * acos(
    cos(radians($myLat)) * cos(radians(u.latitude))
    * cos(radians(u.longitude) - radians($myLng))
    + sin(radians($myLat)) * sin(radians(u.latitude))
)";

$limit = 150; 

$sql = "
    SELECT
        u.id, u.full_name, u.age, u.gender, u.latitude, u.longitude,
        u.is_verified, (strtotime(u.last_active) > (time() - 300)) as is_online,
        ($distSql) AS distance_km,
        EXISTS(SELECT 1 FROM matches WHERE (user1_id = $userId AND user2_id = u.id) OR (user1_id = u.id AND user2_id = $userId)) as is_match
    FROM users u
    LEFT JOIN blocks bl ON (bl.blocker_id = $userId AND bl.blocked_user_id = u.id)
                        OR (bl.blocker_id = u.id   AND bl.blocked_user_id = $userId)
    WHERE u.id != $userId
      AND u.show_on_map = 1
      AND bl.blocker_id IS NULL
      AND u.latitude != 0 AND u.longitude != 0
      AND ($distSql) <= $maxDist
      AND ($distSql) >= u.stealth_radius
    ORDER BY distance_km ASC
    LIMIT $limit
";

$result = $db->query($sql);
$users = [];
$userIds = [];

while ($row = $result->fetch_assoc()) {
    $row['id'] = (int)$row['id'];
    $row['distance_km'] = round((float)$row['distance_km'], 2);
    $row['is_match'] = (bool)$row['is_match'];
    $users[] = $row;
    $userIds[] = $row['id'];
}

// Fetch photos
if (!empty($userIds)) {
    $idList = implode(',', $userIds);
    $photoRes = $db->query("SELECT user_id, photo_url FROM user_photos WHERE user_id IN ($idList) AND is_dp = 1");
    $photos = [];
    while($p = $photoRes->fetch_assoc()) {
        $photos[$p['user_id']] = cloudinaryTransform($p['photo_url'], 'q_auto,f_auto,w_200');
    }
    
    foreach ($users as &$u) {
        $u['dp_url'] = $photos[$u['id']] ?? '';
    }
}

$db->close();
echo json_encode(['status' => 'success', 'users' => $users]);
