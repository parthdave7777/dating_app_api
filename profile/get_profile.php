<?php
// profile/get_profile.php
require_once __DIR__ . '/../config.php';

$userId = getAuthUserId();
if (!$userId) {
    header('Content-Type: application/json');
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit();
}
$db = getDB();

if (isset($_GET['target_id']) && (int)$_GET['target_id'] !== $userId) {
    $targetId = (int) $_GET['target_id'];
    
    // SPEED OPT 1: Record View and Notification Logic
    // We combine the view check and insert into an UPSERT.
    $db->query("INSERT INTO profile_views (viewer_id, viewed_id)
                VALUES ($userId, $targetId)
                ON DUPLICATE KEY UPDATE viewed_at = NOW()");

    // Since we just updated 'viewed_at' above, we check if there's a need for a push.
    // We'll trust the sendProfileViewNotification logic which handles its own throttling.
    require_once __DIR__ . '/../notifications/send_push.php';
    sendProfileViewNotification($db, $userId, $targetId);
} else {
    $targetId = $userId;
}

// SPEED OPT 2: COMBINED QUERY (User Data + Match Status + My Location)
$stmt = $db->prepare("
    SELECT u.*, 
           m.id AS match_id,
           me.latitude AS my_lat, me.longitude AS my_lng
    FROM users u
    LEFT JOIN matches m ON (m.user1_id = ? AND m.user2_id = u.id) 
                        OR (m.user1_id = u.id AND m.user2_id = ?)
    LEFT JOIN users me ON me.id = ?
    WHERE u.id = ?
");

if (!$stmt) {
    echo json_encode(['status' => 'error', 'message' => 'Query preparation failed: ' . $db->error]);
    exit();
}

$stmt->bind_param('iiii', $userId, $userId, $userId, $targetId);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$user) {
    echo json_encode(['status' => 'error', 'message' => 'User not found']);
    exit();
}

$isMatch = !empty($user['match_id']);
$matchId = $isMatch ? (int)$user['match_id'] : null;
$myLat   = (float)($user['my_lat'] ?? 0);
$myLng   = (float)($user['my_lng'] ?? 0);

// Fetch photos — deduplicate by URL to handle previous setup bug
$photoStmt = $db->prepare(
    "SELECT id, photo_url, is_dp FROM user_photos WHERE user_id = ? ORDER BY is_dp DESC, created_at ASC"
);
$photoStmt->bind_param('i', $targetId);
$photoStmt->execute();
$photoResult = $photoStmt->get_result();
$photoStmt->close();

$photos      = [];
$photoUrls   = []; 
$dpUrl       = null;
$firstUrl    = null;
$seenUrls    = [];

while ($photo = $photoResult->fetch_assoc()) {
    $rawUrl = $photo['photo_url'];
    $optimizedUrl = cloudinaryTransform($rawUrl, 'q_auto,f_auto');

    // ONLY skip if we've seen this EXACT URL before
    if (in_array($rawUrl, $seenUrls)) continue;
    $seenUrls[] = $rawUrl;

    $isOurDP = (bool)$photo['is_dp'];
    
    if ($isOurDP && $dpUrl !== null) $isOurDP = false; 

    $photos[] = [
        'url'   => $optimizedUrl, 
        'is_dp' => $isOurDP
    ];
    $photoUrls[] = $rawUrl;

    if ($firstUrl === null) $firstUrl = $optimizedUrl;
    if ($isOurDP) $dpUrl = $optimizedUrl;
}
// Fallback: if no photo is marked as DP, use the first one
if ($dpUrl === null && $firstUrl !== null) $dpUrl = $firstUrl;

// Fetch posts
$postStmt = $db->prepare(
    "SELECT id, photo_url, caption, created_at FROM user_posts WHERE user_id = ? ORDER BY created_at DESC"
);
$postStmt->bind_param('i', $targetId);
$postStmt->execute();
$postResult = $postStmt->get_result();
$postStmt->close();

$posts = [];
while ($post = $postResult->fetch_assoc()) {
    $posts[] = $post;
}

// SPEED OPT 3: Distance calculation using pre-fetched coordinates
$distance = null;
if ($userId !== $targetId && $myLat && $user['latitude']) {
    $distance = haversineKm($myLat, $myLng, (float)$user['latitude'], (float)$user['longitude']);
}

$db->close();

echo json_encode([
    'status'  => 'success',
    'profile' => [
        'id'                => (int)  $user['id'],
        'phone_number'      =>        $user['phone_number'],
        'full_name'         =>        $user['full_name'],
        'age'               => (int)  $user['age'],
        'gender'            =>        $user['gender'],
        'looking_for'       =>        $user['looking_for'],
        'bio'               =>        $user['bio'],
        'interests'         =>        $user['interests'] ? explode(',', $user['interests']) : [],
        'height'            =>        $user['height'],
        'education'         =>        $user['education'],
        'job_title'         =>        $user['job_title'],
        'company'           =>        $user['company'],
        'lifestyle_pets'    =>        $user['lifestyle_pets'],
        'lifestyle_drinking'=>        $user['lifestyle_drinking'],
        'lifestyle_smoking' =>        $user['lifestyle_smoking'],
        'lifestyle_workout' =>        $user['lifestyle_workout'],
        'lifestyle_diet'    =>        $user['lifestyle_diet'],
        'lifestyle_schedule'=>        $user['lifestyle_schedule'],
        'communication_style'=>       $user['communication_style'],
        'relationship_goal' =>        $user['relationship_goal'],
        'city'              =>        $user['city'] ?? '',
        'state'             =>        $user['state'] ?? '',
        'country'           =>        $user['country'] ?? '',
        'latitude'          => (float)($user['latitude'] ?? 0),
        'longitude'         => (float)($user['longitude'] ?? 0),
        'is_verified'       => (bool) $user['is_verified'],
        'profile_complete'  => (bool) $user['profile_complete'],
        'setup_completed'   => (bool) $user['setup_completed'],
        'photos'            =>        $photos,      // array of {url, is_dp}
        'photo_urls'        =>        $photoUrls,   // flat string array (legacy)
        'dp_url'            =>        $dpUrl,
        'posts'             =>        $posts,
        'distance_km'       =>        $distance,
        'discovery_min_age'  => (int)  ($user['discovery_min_age']  ?? 18),
        'discovery_max_age'  => (int)  ($user['discovery_max_age']  ?? 55),
        'discovery_max_dist' => (int)  ($user['discovery_max_dist'] ?? 50),
        'discovery_min_dist' => (int)  ($user['discovery_min_dist'] ?? 0),
        'global_discovery'   => (bool) ($user['global_discovery']   ?? 1),
        'is_match'           => (bool) $isMatch,
        'match_id'           => $matchId,
        'notif_matches'      => (bool) ($user['notif_matches']      ?? 1),
        'notif_messages'     => (bool) ($user['notif_messages']     ?? 1),
        'notif_likes'        => (bool) ($user['notif_likes']        ?? 1),
        'notif_who_swiped'   => (bool) ($user['notif_who_swiped']   ?? 1),
        'notif_activity'     => (bool) ($user['notif_activity']     ?? 1),
        'credits'            => (int)  ($user['credits']            ?? 0),
        'premium_credits'    => (int)  ($user['premium_credits']    ?? 0),
    ],
]);

function haversineKm(float $lat1, float $lon1, float $lat2, float $lon2): float {
    $R  = 6371;
    $dL = deg2rad($lat2 - $lat1);
    $dN = deg2rad($lon2 - $lon1);
    $a  = sin($dL/2)*sin($dL/2) + cos(deg2rad($lat1))*cos(deg2rad($lat2))*sin($dN/2)*sin($dN/2);
    return round($R * 2 * atan2(sqrt($a), sqrt(1-$a)), 1);
}
