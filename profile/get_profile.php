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
} else {
    $targetId = $userId;
}

// SPEED OPT 1: COMBINED QUERY (User Data + Match Status + My Location)
$sql = "
    SELECT u.*, 
           m.id AS match_id,
           me.latitude AS my_lat, me.longitude AS my_lng
    FROM users u
    LEFT JOIN matches m ON (m.user1_id = $userId AND m.user2_id = u.id) 
                        OR (m.user1_id = u.id AND m.user2_id = $userId)
    LEFT JOIN users me ON me.id = $userId
    WHERE u.id = ?
";

$stmt = $db->prepare($sql);
$stmt->bind_param('i', $targetId);
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

// Calculate distance
$distance = null;
if ($userId !== $targetId && $myLat && $user['latitude']) {
    $distance = haversineKm($myLat, $myLng, (float)$user['latitude'], (float)$user['longitude']);
}

// Fetch photos
$photos = []; $photoUrls = [];
$photoResult = $db->query("SELECT photo_url, is_dp FROM user_photos WHERE user_id = $targetId ORDER BY is_dp DESC, created_at ASC");
while ($p = $photoResult->fetch_assoc()) {
    $url = cloudinaryTransform($p['photo_url'], 'q_auto,f_auto');
    if (in_array($url, $photoUrls)) continue;
    $photos[] = ['url' => $url, 'is_dp' => (bool)$p['is_dp']];
    $photoUrls[] = $url;
}

// Fetch posts
$posts = [];
$postRes = $db->query("SELECT id, photo_url, caption, created_at FROM user_posts WHERE user_id = $targetId ORDER BY created_at DESC");
while ($p = $postRes->fetch_assoc()) {
    $posts[] = $p;
}

// ─── RESPOND IMMEDIATELY (Ultra-Speed) ─────────────────────
// The user gets the profile data instantly.
sendResponseAndContinue([
    'status' => 'success',
    'data' => [
        'profile'  => $user,
        'photos'   => $photos,
        'posts'    => $posts,
        'is_match' => $isMatch,
        'match_id' => $matchId,
        'distance' => $distance
    ]
]);

// ─── BACKGROUND PROCESSING (View Notifications & Stats) ──────
if ($targetId !== $userId) {
    // 1. Record View (Atomic)
    $db->query("INSERT INTO profile_views (viewer_id, viewed_id)
                VALUES ($userId, $targetId)
                ON DUPLICATE KEY UPDATE viewed_at = NOW()");

    // 2. Notification (Throttled)
    require_once __DIR__ . '/../notifications/send_push.php';
    sendProfileViewNotification($db, $userId, $targetId);
}

$db->close();
exit();
