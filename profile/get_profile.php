<?php
// profile/get_profile.php
require_once __DIR__ . '/../config.php';

$userId = getAuthUserId();
$db     = getDB();

if (isset($_GET['target_id']) && (int)$_GET['target_id'] !== $userId) {
    $targetId = (int) $_GET['target_id'];
    // FIX: column is 'viewed_id' not 'viewed_user_id'
    $db->query("INSERT INTO profile_views (viewer_id, viewed_id)
                VALUES ($userId, $targetId)
                ON DUPLICATE KEY UPDATE viewed_at = NOW()");
} else {
    $targetId = $userId;
}

$stmt = $db->prepare("
    SELECT id, phone_number, full_name, age, gender, looking_for, bio,
           interests, height, education, job_title, company,
           lifestyle_pets, lifestyle_drinking, lifestyle_smoking, lifestyle_workout, 
           lifestyle_diet, lifestyle_schedule, communication_style, relationship_goal,
           latitude, longitude, city, state, country, is_verified, profile_complete, setup_completed,
           discovery_min_age, discovery_max_age, discovery_max_dist, global_discovery,
           notif_matches, notif_messages, notif_likes, notif_activity
    FROM users WHERE id = ?
");
$stmt->bind_param('i', $targetId);
$stmt->execute();
$result = $stmt->get_result();
$stmt->close();

if ($result->num_rows === 0) {
    echo json_encode(['status' => 'error', 'message' => 'User not found']);
    exit();
}

$user = $result->fetch_assoc();

// Fetch photos — return as objects with url + is_dp so Flutter can use p['url']
$photoStmt = $db->prepare(
    "SELECT photo_url, is_dp FROM user_photos WHERE user_id = ? ORDER BY is_dp DESC, created_at ASC"
);
$photoStmt->bind_param('i', $targetId);
$photoStmt->execute();
$photoResult = $photoStmt->get_result();
$photoStmt->close();

$photos    = [];
$photoUrls = []; // flat list of URLs
$dpUrl     = null;
$firstUrl  = null;
while ($photo = $photoResult->fetch_assoc()) {
    $photoUrls[] = $photo['photo_url'];
    $optimizedUrl = cloudinaryTransform($photo['photo_url'], 'q_auto,f_auto');
    $photos[]    = ['url' => $optimizedUrl, 'is_dp' => (bool)$photo['is_dp']];
    if ($firstUrl === null) $firstUrl = $optimizedUrl;
    if ($photo['is_dp']) $dpUrl = $optimizedUrl;
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

// Distance
$distance = null;
if ($userId !== $targetId) {
    $locStmt = $db->prepare("SELECT latitude, longitude FROM users WHERE id = ?");
    $locStmt->bind_param('i', $userId);
    $locStmt->execute();
    $locRow = $locStmt->get_result()->fetch_assoc();
    $locStmt->close();

    if ($locRow && $locRow['latitude'] && $user['latitude']) {
        $distance = haversineKm(
            (float) $locRow['latitude'],  (float) $locRow['longitude'],
            (float) $user['latitude'],    (float) $user['longitude']
        );
    }
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
        'city'              =>        $user['city'],
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
        'global_discovery'   => (bool) ($user['global_discovery']   ?? 1),
        'notif_matches'      => (bool) ($user['notif_matches']      ?? 1),
        'notif_messages'     => (bool) ($user['notif_messages']     ?? 1),
        'notif_likes'        => (bool) ($user['notif_likes']        ?? 1),
        'notif_activity'     => (bool) ($user['notif_activity']     ?? 1),
    ],
]);

function haversineKm(float $lat1, float $lon1, float $lat2, float $lon2): float {
    $R  = 6371;
    $dL = deg2rad($lat2 - $lat1);
    $dN = deg2rad($lon2 - $lon1);
    $a  = sin($dL/2)*sin($dL/2) + cos(deg2rad($lat1))*cos(deg2rad($lat2))*sin($dN/2)*sin($dN/2);
    return round($R * 2 * atan2(sqrt($a), sqrt(1-$a)), 1);
}
