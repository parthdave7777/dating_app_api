<?php
// profile/get_users.php — Optimized "Radial Expansion" Discovery Algorithm
require_once __DIR__ . '/../config.php';

$userId = getAuthUserId();
$db = getDB();

// 1. Activity Pulse
$db->query("UPDATE users SET last_active = NOW() WHERE id = $userId");

// 2. Clear Expired Boosts
$db->query("UPDATE users SET is_new_user_boost = 0 WHERE new_user_boost_expires < NOW() AND is_new_user_boost = 1");

// 3. Fetch Current User Details
$meStmt = $db->prepare("SELECT age, gender, latitude, longitude, interests, city FROM users WHERE id = ?");
$meStmt->bind_param('i', $userId);
$meStmt->execute();
$me = $meStmt->get_result()->fetch_assoc();
$meStmt->close();

if (!$me) {
    http_response_code(404);
    echo json_encode(['status' => 'error', 'message' => 'Profile not found']);
    exit();
}

$myAge       = (int)($me['age'] ?? 20);
$myLat       = (float)($me['latitude'] ?? 0);
$myLng       = (float)($me['longitude'] ?? 0);
$myCity      = trim(strtolower($me['city'] ?? ''));
$myInterests = array_filter(array_map('trim', explode(',', strtolower($me['interests'] ?? ''))));
$hasCoords   = ($myLat != 0 && $myLng != 0);

$minAge = isset($_GET['min_age']) ? (int)$_GET['min_age'] : 18;
$maxAge = isset($_GET['max_age']) ? (int)$_GET['max_age'] : 100;
$minDist = isset($_GET['min_dist']) ? (int)$_GET['min_dist'] : 0;
$maxDist = isset($_GET['max_dist']) ? (int)$_GET['max_dist'] : 50;
$isGlobal = isset($_GET['global_discovery']) ? ($_GET['global_discovery'] === 'true' || $_GET['global_discovery'] === '1') : true;

// 4. Gender Normalization & Reciprocal Matching
$myGender = strtolower($me['gender'] ?? '');
if (in_array($myGender, ['male', 'man', 'm'])) $myGenderNormalized = 'man';
elseif (in_array($myGender, ['female', 'woman', 'f'])) $myGenderNormalized = 'woman';
else $myGenderNormalized = 'other';

$targetGender = ($myGenderNormalized === 'woman') ? 'man' : 'woman';

// 5. Build Discovery Pool (Mutual Interest)
// Candidates must want our gender, and we must want theirs.
$poolConditions = "
    u.id != $userId
    AND u.show_in_discovery = 1
    AND (
        (LOWER(u.gender) IN ('man','male','m') AND ? = 'man') OR
        (LOWER(u.gender) IN ('woman','female','w') AND ? = 'woman')
    )
    AND u.age >= ? AND u.age <= ?
    AND u.id NOT IN (
        SELECT blocked_user_id FROM blocks WHERE blocker_id = ?
        UNION
        SELECT blocker_id FROM blocks WHERE blocked_user_id = ?
        UNION
        SELECT user1_id FROM matches WHERE user2_id = ?
        UNION
        SELECT user2_id FROM matches WHERE user1_id = ?
    )
";

$candidateQuery = "
    SELECT u.id, u.full_name, u.age, u.gender, u.looking_for, u.bio, u.interests,
           u.city, u.latitude, u.longitude, 
           u.is_verified, u.elo_score, u.last_active, u.job_title, u.company, u.education,
           u.lifestyle_drinking, u.lifestyle_smoking, u.lifestyle_workout, u.lifestyle_pets,
           u.lifestyle_diet, u.lifestyle_schedule, u.communication_style, u.relationship_goal,
           COALESCE(
               (SELECT photo_url FROM user_photos WHERE user_id = u.id AND is_dp = 1 LIMIT 1),
               (SELECT photo_url FROM user_photos WHERE user_id = u.id LIMIT 1)
           ) AS dp_url
    FROM users u
    LEFT JOIN swipes s ON s.swiper_id = ? AND s.swiped_id = u.id
    WHERE $poolConditions
      AND (s.id IS NULL OR (s.action = 'dislike' AND s.created_at < DATE_SUB(NOW(), INTERVAL 7 DAY)))
    LIMIT 2000
";

$stmt = $db->prepare($candidateQuery);
// 4. minAge, 5. maxAge, 6. userId (blocks blocker), 7. userId (blocks blocked), 8. userId (matches 1), 9. userId (matches 2)
$stmt->bind_param('issiiiiii', $userId, $targetGender, $targetGender, $minAge, $maxAge, $userId, $userId, $userId, $userId);
$stmt->execute();
$candidateResult = $stmt->get_result();
$stmt->close();
if ($candidateResult->num_rows === 0) {
    error_log("DISCOVERY: No candidates found for User ID $userId. Gender: $targetGender, Age: $minAge-$maxAge");
}

$candidates = [];
while ($row = $candidateResult->fetch_assoc()) {
    $scoredItem = runScoring($row, $myLat, $myLng, $myAge, $myInterests, $hasCoords, $isGlobal);
    
    if ($hasCoords && !empty($row['latitude'])) {
        if (!$isGlobal) {
            // Local mode: apply slider bounds
            if ($scoredItem['distance_km'] < $minDist || $scoredItem['distance_km'] > $maxDist) {
                continue;
            }
        }
        // Global mode: no distance filter — show everyone worldwide
    }

    $candidates[] = $scoredItem;
}

// 8. Sorting (Strict descending by total_score)
usort($candidates, function($a, $b) {
    return $b['total_score'] <=> $a['total_score'];
});

$scored = $candidates; // Alias for compatibility with rest of script

// 6. Scoring Function (The "Radial Expansion" Engine)
function runScoring($row, $myLat, $myLng, $myAge, $myInterests, $hasCoords, $isGlobal): array {
    $totalScore = 10000000; // Base Score
    $distanceKm = 0.0;

    // --- PRIMARY FACTOR: Radial Distance ---
    if ($hasCoords && !empty($row['latitude']) && !empty($row['longitude'])) {
        $distanceKm = haversineKm($myLat, $myLng, (float)$row['latitude'], (float)$row['longitude']);
        
        if ($isGlobal) {
            if ($distanceKm > 500) {
                // GLOBAL BONUS: If they are far away (>500km), give them a massive lead
                // Instead of subtracting, we add a huge bonus for the "Discovery" feel
                $totalScore += 20000000; 
            } else {
                // In Global mode, near users aren't penalized as heavily, 
                // but far ones (>500km) will still sit above them.
                $totalScore -= ($distanceKm * 1000); 
            }
        } else {
            // ULTIMATE PROXIMITY RANKING (Local Mode): Someone 2km away ALWAYS beats someone 10km away.
            // 1,000,000 penalty per KM ensures distance is the absolute king of discovery.
            $totalScore -= ($distanceKm * 1000000); 
        }
    } else {
        $totalScore -= 15000000; // Even bigger penalty for no location
    }

    // --- SECONDARY FACTOR: Age Proximity ---
    $ageDiff = abs($myAge - (int)$row['age']);
    $ageBonus = max(0, 2000000 - ($ageDiff * 100000));
    $totalScore += $ageBonus;

    // --- TERTIARY FACTORS: Interests & Penalties ---
    
    // Interests Overlap (+50,000 per common item)
    if (!empty($myInterests) && !empty($row['interests'])) {
        $theirInterests = array_filter(array_map('trim', explode(',', strtolower($row['interests']))));
        $overlap = count(array_intersect($myInterests, $theirInterests));
        $totalScore += ($overlap * 50000);
    }

    // Recycling Penalty (-5,000,000)
    $isRecycled = ($row['previous_action'] === 'dislike');
    if ($isRecycled) {
        $totalScore -= 5000000;
    }

    // Photo Penalty (-1,000,000 if no DP)
    $hasPhoto = !empty($row['dp_url']);
    if (!$hasPhoto) {
        $totalScore -= 1000000;
    }

    return [
        'row'         => $row,
        'distance_km' => $distanceKm,
        'total_score' => $totalScore,
        'is_recycled' => $isRecycled
    ];
}

// 9. Batch Fetch Photos for the top candidates (Optimization)
$topCandidates = array_slice($scored, 0, 150); // Fetch photos for top 150
$finalIds = array_map(fn($s) => (int)$s['row']['id'], $topCandidates);

$allPhotos = [];
if (!empty($finalIds)) {
    $idList = implode(',', $finalIds);
    $photoRes = $db->query("SELECT user_id, photo_url FROM user_photos WHERE user_id IN ($idList) ORDER BY is_dp DESC, created_at ASC");
    while ($p = $photoRes->fetch_assoc()) {
        $uid = (int)$p['user_id'];
        $allPhotos[$uid][] = cloudinaryTransform($p['photo_url'], 'q_auto,f_auto,w_600');
    }
}

// 10. Construct Final JSON Structure
$finalUsers = [];
foreach ($topCandidates as $s) {
    $row = $s['row'];
    $uid = (int)$row['id'];
    
    $finalUsers[] = [
        'id'             => $uid,
        'full_name'      => $row['full_name'],
        'age'            => (int)$row['age'],
        'gender'         => $row['gender'] ?? '',
        'bio'            => $row['bio'] ?? '',
        'interests'      => !empty($row['interests']) ? (is_array($row['interests']) ? $row['interests'] : explode(',', (string)$row['interests'])) : [],
        'city'           => $row['city'] ?? '',
        'latitude'       => (float)($row['latitude'] ?? 0),
        'longitude'      => (float)($row['longitude'] ?? 0),
        'is_verified'    => (bool)($row['is_verified'] ?? 0),
        'dp_url'         => $row['dp_url'] ? cloudinaryTransform($row['dp_url'], 'q_auto,f_auto,w_600') : '',
        'photos'         => $allPhotos[$uid] ?? [],
        'distance_km'    => (float)$s['distance_km'],
        'total_score'    => (int)$s['total_score'],
        'is_recycled'    => (bool)$s['is_recycled'],
        'elo_score'      => (int)($row['elo_score'] ?? 1000),
        'job_title'      => $row['job_title'] ?? '',
        'company'        => $row['company'] ?? '',
        'education'      => $row['education'] ?? '',
        'lifestyle_drinking' => $row['lifestyle_drinking'] ?? '',
        'lifestyle_smoking'  => $row['lifestyle_smoking'] ?? '',
        'lifestyle_workout'  => $row['lifestyle_workout'] ?? '',
        'lifestyle_pets'     => $row['lifestyle_pets'] ?? '',
        'lifestyle_diet'     => $row['lifestyle_diet'] ?? '',
        'lifestyle_schedule' => $row['lifestyle_schedule'] ?? '',
        'communication_style' => $row['communication_style'] ?? '',
        'relationship_goal' => $row['relationship_goal'] ?? '',
        'is_active_now'  => (strtotime($row['last_active']) > (time() - 300)) // Active in last 5 mins
    ];
}

$db->close();

echo json_encode([
    'status'   => 'success',
    'users'    => $finalUsers,
    'metadata' => [
        'count'       => count($finalUsers),
        'engine'      => 'Radial-Expansion-v1',
        'is_recycled' => (count($finalUsers) < 10)
    ]
]);

// --- Distance Calculation (Haversine) ---
function haversineKm(float $lat1, float $lon1, float $lat2, float $lon2): float {
    $R = 6371; // Earth Radius
    $dLat = deg2rad($lat2 - $lat1);
    $dLon = deg2rad($lon2 - $lon1);
    $a = sin($dLat/2) * sin($dLat/2) + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLon/2) * sin($dLon/2);
    $c = 2 * atan2(sqrt($a), sqrt(1-$a));
    return round($R * $c, 2);
}
?>
