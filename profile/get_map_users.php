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
        u.id, u.full_name, u.age, u.gender, u.latitude, u.longitude,
        u.is_verified, (u.last_active > DATE_SUB(NOW(), INTERVAL 15 MINUTE)) as is_online,
        ($distSql) AS distance_km,
        EXISTS(SELECT 1 FROM matches WHERE (user1_id = $userId AND user2_id = u.id) OR (user1_id = u.id AND user2_id = $userId)) as is_match,
        EXISTS(SELECT 1 FROM profile_views WHERE viewer_id = $userId AND viewed_id = u.id) as viewed_before
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
      AND ($distSql) >= COALESCE(u.stealth_radius, 0)
      AND ($isGlobal ? "1=1" : "($distSql) <= $maxDist")
      AND ($distSql) >= $minDist
      AND (u.age >= $minAge AND u.age <= $maxAge)
    ORDER BY distance_km ASC
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
