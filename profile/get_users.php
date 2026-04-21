<?php
// profile/get_users.php — Optimized Discovery (Distance-first, client-side filters)
require_once __DIR__ . '/../config.php';

$userId = getAuthUserId();
$db = getDB();
autoSyncUserMeta($userId, $db);

// Clear Expired Boosts (cheap indexed update)
$db->query("UPDATE users SET is_new_user_boost = 0 
            WHERE is_new_user_boost = 1 AND new_user_boost_expires < NOW()");

// 3. Fetch Current User
$meStmt = $db->prepare("
    SELECT age, gender, latitude, longitude, city,
           discovery_min_age, discovery_max_age,
           discovery_min_dist, discovery_max_dist, global_discovery, stealth_radius
    FROM users WHERE id = ?
");
$meStmt->bind_param('i', $userId);
$meStmt->execute();
$me = $meStmt->get_result()->fetch_assoc();
$meStmt->close();

if (!$me) {
    http_response_code(404);
    echo json_encode(['status' => 'error', 'message' => 'Profile not found']);
    exit();
}

$myAge  = (int)($me['age'] ?? 20);
$myLat  = (float)($me['latitude'] ?? 0);
$myLng  = (float)($me['longitude'] ?? 0);
$myCity = trim(strtolower($me['city'] ?? ''));
$myStealthRadius = (int)($me['stealth_radius'] ?? 0);
$hasCoords = ($myLat != 0 && $myLng != 0);

// If NOT global discovery, we MUST have coords. If missing, return empty.
if (!$isGlobal && !$hasCoords) {
    echo json_encode([
        'status' => 'success',
        'users' => [],
        'metadata' => ['message' => 'Location required for local discovery']
    ]);
    exit();
}

// Discovery settings — GET params override stored prefs
$minAge  = isset($_GET['min_age'])  ? (int)$_GET['min_age']  : (int)($me['discovery_min_age']  ?? 18);
$maxAge  = isset($_GET['max_age'])  ? (int)$_GET['max_age']  : (int)($me['discovery_max_age']  ?? 100);
$minDist = isset($_GET['min_dist']) ? (int)$_GET['min_dist'] : (int)($me['discovery_min_dist'] ?? 0);
$maxDist = isset($_GET['max_dist']) ? (int)$_GET['max_dist'] : (int)($me['discovery_max_dist'] ?? 50);
$isGlobal = isset($_GET['global_discovery'])
    ? ($_GET['global_discovery'] === 'true' || $_GET['global_discovery'] === '1')
    : (bool)($me['global_discovery'] ?? false);

// Pagination
$page  = max(1, (int)($_GET['page'] ?? 1));
$limit = 30;
$offset = ($page - 1) * $limit;

// 4. Delete disliked swipes older than 2 days (keep this logic, runs async-ish)
$db->query("DELETE FROM swipes 
            WHERE swiper_id = $userId 
              AND action = 'dislike' 
              AND created_at < DATE_SUB(NOW(), INTERVAL 2 DAY)");

// 5. Gender Normalization
$myGender = strtolower($me['gender'] ?? '');
if (in_array($myGender, ['male', 'man', 'm']))        $myGenderNormalized = 'man';
elseif (in_array($myGender, ['female', 'woman', 'f'])) $myGenderNormalized = 'woman';
else                                                   $myGenderNormalized = 'other';

$targetGender    = ($myGenderNormalized === 'woman') ? 'man' : 'woman';
$targetGenderEsc = $db->real_escape_string($targetGender);

// 6. Distance SQL expression
$distSql = $hasCoords
    ? "6371 * acos(
           cos(radians($myLat)) * cos(radians(u.latitude))
           * cos(radians(u.longitude) - radians($myLng))
           + sin(radians($myLat)) * sin(radians(u.latitude))
       )"
    : "0";

// 7. Bounding-box pre-filter (cheap lat/lng range check before trig)
$boundsCondition = "";
if ($hasCoords && !$isGlobal) {
    $latRange = $maxDist / 111.0;
    $lngRange = $maxDist / (111.0 * max(cos(deg2rad($myLat)), 0.0001));
    $minLat = $myLat - $latRange; $maxLat = $myLat + $latRange;
    $minLng = $myLng - $lngRange; $maxLng = $myLng + $lngRange;
    $boundsCondition = "AND u.latitude  BETWEEN $minLat AND $maxLat
                        AND u.longitude BETWEEN $minLng AND $maxLng
                        AND u.latitude != 0 AND u.longitude != 0";
    // Exact distance filter applied after fetch (avoids double trig in WHERE + SELECT)
}

// 8. Main candidate query
$globalCondition = $isGlobal ? "AND ($distSql) >= 500" : "";

$sql = "
    SELECT
        u.id, u.full_name, u.age, u.gender, u.looking_for, u.bio,
        u.interests, u.city, u.latitude, u.longitude,
        u.is_verified, u.elo_score, u.last_active,
        u.job_title, u.company, u.education,
        u.lifestyle_drinking, u.lifestyle_smoking, u.lifestyle_workout,
        u.lifestyle_pets, u.lifestyle_diet, u.lifestyle_schedule,
        u.communication_style, u.relationship_goal,
        ($distSql) AS distance_km,
        sw.action AS previous_action
    FROM users u
    LEFT JOIN swipes  sw  ON sw.swiper_id  = $userId AND sw.swiped_id    = u.id
    LEFT JOIN blocks  bl  ON (bl.blocker_id = $userId AND bl.blocked_user_id = u.id)
                          OR (bl.blocker_id = u.id   AND bl.blocked_user_id = $userId)
    LEFT JOIN matches mt  ON (mt.user1_id   = $userId AND mt.user2_id       = u.id)
                          OR (mt.user1_id   = u.id   AND mt.user2_id        = $userId)
    WHERE u.id              != $userId
      AND u.show_in_discovery = 1
      AND u.age              BETWEEN ? AND ?
      AND (
            (LOWER(u.gender) IN ('man','male','m')       AND '$targetGenderEsc' = 'man')
          OR (LOWER(u.gender) IN ('woman','female','w')   AND '$targetGenderEsc' = 'woman')
      )
      AND bl.blocker_id IS NULL
      AND mt.user1_id   IS NULL
      AND (sw.action IS NULL OR sw.action = 'dislike')
      AND ($distSql) >= u.stealth_radius
      $boundsCondition
      $globalCondition
    ORDER BY " . ($isGlobal ? "u.last_active DESC" : "distance_km ASC") . "
    LIMIT $limit OFFSET $offset
";

$stmt = $db->prepare($sql);
$stmt->bind_param('ii', $minAge, $maxAge);
$stmt->execute();
$result = $stmt->get_result();
$stmt->close();

$rows = [];
while ($row = $result->fetch_assoc()) {
    $d = (float)$row['distance_km'];
    
    // Strict filters
    if ($isGlobal) {
        if ($d < 500) continue; // Only show 500km+ in global mode
    } else if ($hasCoords) {
        if ($d < $minDist || $d > $maxDist) continue;
    }
    
    // Mark recycled rows (previously disliked, now re-surfaced after 2-day window)
    $row['is_recycled'] = ($row['previous_action'] === 'dislike');
    $rows[] = $row;
}

// 9. Batch-fetch all photos in ONE query (not N queries)
$finalIds = array_column($rows, 'id');
$allPhotos = [];
$dpMap     = [];

if (!empty($finalIds)) {
    $idList   = implode(',', array_map('intval', $finalIds));
    $photoRes = $db->query("
        SELECT user_id, photo_url, is_dp
        FROM user_photos
        WHERE user_id IN ($idList)
        ORDER BY user_id, is_dp DESC, created_at ASC
    ");
    while ($p = $photoRes->fetch_assoc()) {
        $uid = (int)$p['user_id'];
        $url = cloudinaryTransform($p['photo_url'], 'q_auto,f_auto,w_600');
        
        // Only add unique URLs for each user
        if (!isset($allPhotos[$uid])) $allPhotos[$uid] = [];
        if (!in_array($url, $allPhotos[$uid])) {
            $allPhotos[$uid][] = $url;
        }

        if ($p['is_dp'] && !isset($dpMap[$uid])) {
            $dpMap[$uid] = $url;
        }
    }
}

// 10. Build response
$finalUsers = [];
foreach ($rows as $row) {
    $uid = (int)$row['id'];
    $photos = $allPhotos[$uid] ?? [];
    $dp     = $dpMap[$uid] ?? ($photos[0] ?? '');

    $finalUsers[] = [
        'id'                  => $uid,
        'full_name'           => $row['full_name'],
        'age'                 => (int)$row['age'],
        'gender'              => $row['gender'] ?? '',
        'bio'                 => $row['bio'] ?? '',
        'interests'           => !empty($row['interests'])
                                    ? (is_array($row['interests'])
                                        ? $row['interests']
                                        : array_map('trim', explode(',', $row['interests'])))
                                    : [],
        'city'                => $row['city'] ?? '',
        'latitude'            => (float)($row['latitude'] ?? 0),
        'longitude'           => (float)($row['longitude'] ?? 0),
        'is_verified'         => (bool)($row['is_verified'] ?? 0),
        'dp_url'              => $dp,
        'photos'              => $photos,
        'distance_km'         => round((float)$row['distance_km'], 1),
        'is_recycled'         => (bool)$row['is_recycled'],
        'elo_score'           => (int)($row['elo_score'] ?? 1000),
        'job_title'           => $row['job_title'] ?? '',
        'company'             => $row['company'] ?? '',
        'education'           => $row['education'] ?? '',
        'lifestyle_drinking'  => $row['lifestyle_drinking'] ?? '',
        'lifestyle_smoking'   => $row['lifestyle_smoking'] ?? '',
        'lifestyle_workout'   => $row['lifestyle_workout'] ?? '',
        'lifestyle_pets'      => $row['lifestyle_pets'] ?? '',
        'lifestyle_diet'      => $row['lifestyle_diet'] ?? '',
        'lifestyle_schedule'  => $row['lifestyle_schedule'] ?? '',
        'communication_style' => $row['communication_style'] ?? '',
        'relationship_goal'   => $row['relationship_goal'] ?? '',
        'is_active_now'       => (strtotime($row['last_active']) > (time() - 300)),
    ];
}

$db->close();

echo json_encode([
    'status'   => 'success',
    'users'    => $finalUsers,
    'metadata' => [
        'count'    => count($finalUsers),
        'page'     => $page,
        'engine'   => 'Distance-First-v2',
    ]
]);

// ─── Haversine (kept for any future server-side use) ─────────────────────────
function haversineKm(float $lat1, float $lon1, float $lat2, float $lon2): float {
    $R    = 6371;
    $dLat = deg2rad($lat2 - $lat1);
    $dLon = deg2rad($lon2 - $lon1);
    $a    = sin($dLat/2) ** 2
          + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLon/2) ** 2;
    return round($R * 2 * atan2(sqrt($a), sqrt(1 - $a)), 2);
}