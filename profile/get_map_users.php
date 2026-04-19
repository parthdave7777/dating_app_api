<?php
// profile/get_map_users.php — Optimized Map Discovery
require_once __DIR__ . '/../config.php';

$userId = getAuthUserId();
$db = getDB();

// 1. Fetch Me
$meStmt = $db->prepare("SELECT latitude, longitude, discovery_max_dist, gender FROM users WHERE id = ?");
$meStmt->bind_param('i', $userId);
$meStmt->execute();
$me = $meStmt->get_result()->fetch_assoc();
$meStmt->close();

if (!$me) {
    http_response_code(404);
    echo json_encode(['status' => 'error', 'message' => 'Profile not found']);
    exit();
}

$myLat = (float)($me['latitude'] ?? 0);
$myLng = (float)($me['longitude'] ?? 0);
$maxDist = (int)($me['discovery_max_dist'] ?? 50);

if ($myLat == 0 || $myLng == 0) {
    echo json_encode(['status' => 'success', 'users' => []]); // Silent empty if no location
    exit();
}

// 2. Logic: Number of profiles based on distance range
// 0-50: 5, 51-100: 10, >100: 20
$limit = 5;
if ($maxDist > 50)  $limit = 10;
if ($maxDist > 100) $limit = 20;

// 3. Gender Target
$myGender = strtolower($me['gender'] ?? '');
$targetGenderEsc = ($myGender === 'woman') ? 'man' : 'woman';

// 4. Distance SQL
$distSql = "6371 * acos(
    cos(radians($myLat)) * cos(radians(u.latitude))
    * cos(radians(u.longitude) - radians($myLng))
    + sin(radians($myLat)) * sin(radians(u.latitude))
)";

// 5. Query
$sql = "
    SELECT u.id, u.full_name, u.age, u.latitude, u.longitude, u.city,
           ($distSql) AS distance_km,
           up.photo_url as dp_url
    FROM users u
    LEFT JOIN user_photos up ON up.user_id = u.id AND up.is_dp = 1
    LEFT JOIN swipes sw ON sw.swiper_id = $userId AND sw.swiped_id = u.id
    LEFT JOIN blocks bl ON (bl.blocker_id = $userId AND bl.blocked_user_id = u.id)
                        OR (bl.blocker_id = u.id AND bl.blocked_user_id = $userId)
    WHERE u.id != $userId
      AND u.show_in_discovery = 1
      AND u.show_on_map = 1
      AND (
            (LOWER(u.gender) IN ('man','male','m')       AND '$targetGenderEsc' = 'man')
          OR (LOWER(u.gender) IN ('woman','female','w')   AND '$targetGenderEsc' = 'woman')
      )
      AND bl.blocker_id IS NULL
      AND sw.action IS NULL
      AND ($distSql) BETWEEN 0 AND $maxDist
      AND ($distSql) >= u.stealth_radius
      AND u.latitude != 0 AND u.longitude != 0
    ORDER BY RAND()
    LIMIT $limit
";

$res = $db->query($sql);
$users = [];
while ($row = $res->fetch_assoc()) {
    $row['dp_url'] = cloudinaryTransform($row['dp_url'], 'q_auto,f_auto,w_200,h_200,c_fill');
    $row['distance_km'] = round((float)$row['distance_km'], 1);
    $users[] = $row;
}

$db->close();

echo json_encode([
    'status' => 'success',
    'users' => $users,
    'metadata' => ['limit_applied' => $limit, 'range' => $maxDist]
]);
?>
