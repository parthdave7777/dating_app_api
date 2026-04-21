<?php
require_once __DIR__ . '/../config.php';
$userId = getAuthUserId();
$db = getDB();
header('Content-Type: application/json');

$meStmt = $db->prepare("SELECT latitude, longitude, gender, discovery_min_dist, discovery_max_dist, discovery_min_age, discovery_max_age, global_discovery, stealth_radius FROM users WHERE id = ?");
$meStmt->bind_param('i', $userId);
$meStmt->execute();
$me = $meStmt->get_result()->fetch_assoc();
$meStmt->close();

if (!$me) {
    echo json_encode(['status' => 'error', 'message' => 'User not found']);
    exit();
}

$myLat    = (float)($me['latitude'] ?? 0);
$myLng    = (float)($me['longitude'] ?? 0);
$myStealthRadius = (int)($me['stealth_radius'] ?? 0);
$minDist   = (int)($me['discovery_min_dist'] ?? 0);
$maxDist   = (int)($me['discovery_max_dist'] ?? 50);
$minAge    = (int)($me['discovery_min_age'] ?? 18);
$maxAge    = (int)($me['discovery_max_age'] ?? 100);
$isGlobal  = (int)($me['global_discovery'] ?? 0) === 1;
$hasCoords = ($myLat != 0 && $myLng != 0);

if (!$hasCoords) {
    echo json_encode(['status' => 'success', 'users' => [], 'message' => 'Your location is missing']);
    exit();
}

// Gender filtering logic
$myGender = strtolower($me['gender'] ?? '');
if (in_array($myGender, ['male', 'man', 'm']))        $myGenderNormalized = 'man';
elseif (in_array($myGender, ['female', 'woman', 'f'])) $myGenderNormalized = 'woman';
else                                                   $myGenderNormalized = 'other';

$targetGender    = ($myGenderNormalized === 'woman') ? 'man' : 'woman';
$targetGenderEsc = $db->real_escape_string($targetGender);

// Map specific SQL: Includes matches, higher limit (150)
$distSql = "6371 * acos(
    LEAST(1.0, GREATEST(-1.0, 
        cos(radians($myLat)) * cos(radians(u.latitude))
        * cos(radians(u.longitude) - radians($myLng))
        + sin(radians($myLat)) * sin(radians(u.latitude))
    ))
)";

$limit = 150; 

$sql = "
    SELECT
        d.*,
        (m.id IS NOT NULL) AS is_match,
        (pv.viewer_id IS NOT NULL) AS viewed_before
    FROM (
        SELECT
            u.id, u.full_name, u.age, u.gender, u.latitude, u.longitude,
            u.is_verified, u.stealth_radius,
            (u.last_active > DATE_SUB(NOW(), INTERVAL 15 MINUTE)) as is_online,
            ($distSql) AS distance_km
        FROM users u
        LEFT JOIN blocks bl ON (bl.blocker_id = $userId AND bl.blocked_user_id = u.id)
                            OR (bl.blocker_id = u.id   AND bl.blocked_user_id = $userId)
        WHERE u.id != $userId
          AND COALESCE(u.show_on_map, 1) = 1
          AND bl.blocker_id IS NULL
          AND u.latitude != 0 AND u.longitude != 0
          AND (
                (LOWER(u.gender) IN ('man','male','m')       AND '$targetGenderEsc' = 'man')
              OR (LOWER(u.gender) IN ('woman','female','w')   AND '$targetGenderEsc' = 'woman')
          )
    ) d
    LEFT JOIN matches m ON (m.user1_id = $userId AND m.user2_id = d.id) OR (m.user1_id = d.id AND m.user2_id = $userId)
    LEFT JOIN profile_views pv ON (pv.viewer_id = $userId AND pv.viewed_id = d.id)
    WHERE d.distance_km >= COALESCE(d.stealth_radius, 0)
      AND " . ($isGlobal ? "1=1" : "d.distance_km <= $maxDist") . "
      AND d.distance_km >= $minDist
      AND (d.age >= $minAge AND d.age <= $maxAge)
    ORDER BY d.distance_km ASC
    LIMIT $limit
";

$result = $db->query($sql);
if (!$result) {
    echo json_encode(['status' => 'error', 'message' => 'Query failed: ' . $db->error]);
    exit();
}

$users = [];
$userIds = [];

while ($row = $result->fetch_assoc()) {
    $row['id'] = (int)$row['id'];
    $row['distance_km'] = round((float)$row['distance_km'], 2);
    $row['is_match'] = isset($row['is_match']) && (int)$row['is_match'] === 1;
    $row['viewed_before'] = isset($row['viewed_before']) && (int)$row['viewed_before'] === 1;
    $users[] = $row;
    $userIds[] = $row['id'];
}

// Fetch photos
if (!empty($userIds)) {
    $idList = implode(',', $userIds);
    $photoRes = $db->query("SELECT user_id, photo_url FROM user_photos WHERE user_id IN ($idList) AND is_dp = 1");
    $photos = [];
    if ($photoRes) {
        while($p = $photoRes->fetch_assoc()) {
            $photos[$p['user_id']] = cloudinaryTransform($p['photo_url'], 'q_auto,f_auto,w_200');
        }
    }
    
    foreach ($users as &$u) {
        $u['dp_url'] = $photos[$u['id']] ?? '';
    }
}

// Get current user credits for UI update using existing $db
$uStmt = $db->prepare("SELECT (COALESCE(credits, 0) + COALESCE(premium_credits, 0)) as total FROM users WHERE id = ?");
$uStmt->bind_param('i', $userId);
$uStmt->execute();
$rowC = $uStmt->get_result()->fetch_assoc();
$totalCredits = $rowC['total'] ?? 0;
$uStmt->close();

$db->close();

echo json_encode([
    'status' => 'success', 
    'users' => $users, 
    'credits' => (int)$totalCredits
]);
