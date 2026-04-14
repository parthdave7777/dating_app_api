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

// 🚀 ULTRA-SPEED: SINGLE TRIP QUERY
// We fetch Profile + Photos + Posts + Match + Viewer Location in ONE single trip to the DB.
// This is the fastest possible way to load a profile when your DB is far away.
$sql = "
    SELECT 
        u.*, 
        m.id AS match_id,
        me.latitude AS my_lat, me.longitude AS my_lng,
        (
            SELECT JSON_ARRAYAGG(JSON_OBJECT('url', photo_url, 'is_dp', is_dp))
            FROM user_photos 
            WHERE user_id = u.id
        ) AS photos_json,
        (
            SELECT JSON_ARRAYAGG(JSON_OBJECT('id', id, 'photo_url', photo_url, 'caption', caption, 'created_at', created_at))
            FROM user_posts
            WHERE user_id = u.id
        ) AS posts_json
    FROM users u
    LEFT JOIN matches m ON (m.user1_id = $userId AND m.user2_id = u.id) 
                        OR (m.user1_id = u.id AND m.user2_id = $userId)
    LEFT JOIN users me ON me.id = $userId
    WHERE u.id = ?
";

$stmt = $db->prepare($sql);
$stmt->bind_param('i', $targetId);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$row) {
    echo json_encode(['status' => 'error', 'message' => 'User not found']);
    exit();
}

// Parse JSON results
$photos = json_decode($row['photos_json'] ?? '[]', true);
$posts  = json_decode($row['posts_json']  ?? '[]', true);

// Deduplicate and Transform photos (Cloudinary)
$finalPhotos = []; $seenUrls = [];
foreach ($photos as $p) {
    $url = cloudinaryTransform($p['url'], 'q_auto,f_auto');
    if (in_array($url, $seenUrls)) continue;
    $finalPhotos[] = ['url' => $url, 'is_dp' => (bool)$p['is_dp']];
    $seenUrls[] = $url;
}

// Distance calculation
$distance = null;
if ($userId !== $targetId && $row['my_lat'] && $row['latitude']) {
    $distance = haversineKm((float)$row['my_lat'], (float)$row['my_lng'], (float)$row['latitude'], (float)$row['longitude']);
}

// ─── RESPOND IMMEDIATELY ─────────────────────
sendResponseAndContinue([
    'status' => 'success',
    'data' => [
        'profile'  => $row,
        'photos'   => $finalPhotos,
        'posts'    => $posts,
        'is_match' => !empty($row['match_id']),
        'match_id' => $row['match_id'] ? (int)$row['match_id'] : null,
        'distance' => $distance
    ]
]);

// ─── BACKGROUND PROCESSING ─────────────────────
if ($targetId !== $userId) {
    // Record View and Send Notification in background
    $db->query("INSERT INTO profile_views (viewer_id, viewed_id) VALUES ($userId, $targetId) ON DUPLICATE KEY UPDATE viewed_at = NOW()");
    require_once __DIR__ . '/../notifications/send_push.php';
    sendProfileViewNotification($db, $userId, $targetId);
}

$db->close();
exit();
