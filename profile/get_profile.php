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

// 🚀 SPEED OPT: Consolidated 3-Trip pattern (Safe & Reliable)
// Trip 1: User data + Match + My Location
$stmt = $db->prepare("
    SELECT u.*, m.id AS match_id, me.latitude AS my_lat, me.longitude AS my_lng
    FROM users u
    LEFT JOIN matches m ON (m.user1_id = ? AND m.user2_id = u.id) 
                        OR (m.user1_id = u.id AND m.user2_id = ?)
    LEFT JOIN users me ON me.id = ?
    WHERE u.id = ?
");
$stmt->bind_param('iiii', $userId, $userId, $userId, $targetId);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$row) {
    echo json_encode(['status' => 'error', 'message' => 'User not found']);
    exit();
}

// Trip 2: Photos
$photos = [];
$resP = $db->query("SELECT photo_url, is_dp FROM user_photos WHERE user_id = $targetId ORDER BY is_dp DESC, created_at ASC");
while ($p = $resP->fetch_assoc()) {
    $photos[] = [
        'url' => cloudinaryTransform($p['photo_url'], 'q_auto,f_auto'),
        'is_dp' => (bool)$p['is_dp']
    ];
}

// Trip 3: Posts
$posts = [];
$resPost = $db->query("SELECT id, photo_url, caption, created_at FROM user_posts WHERE user_id = $targetId ORDER BY created_at DESC");
while ($p = $resPost->fetch_assoc()) {
    $posts[] = $p;
}

// Distance calc
$distance = null;
if ($userId !== $targetId && $row['my_lat'] && $row['latitude']) {
    $distance = haversineKm((float)$row['my_lat'], (float)$row['my_lng'], (float)$row['latitude'], (float)$row['longitude']);
}

// ─── RESPOND ─────────────────────
// Note: Backgrounding (sendResponseAndContinue) might be unstable on Render, 
// using standard echo for reliability in this debug pass.
header('Content-Type: application/json');
echo json_encode([
    'status' => 'success',
    'data' => [
        'profile'  => $row,
        'photos'   => $photos,
        'posts'    => $posts,
        'is_match' => !empty($row['match_id']),
        'match_id' => $row['match_id'] ? (int)$row['match_id'] : null,
        'distance' => $distance
    ]
]);

// ─── BACKGROUND (Notifications) ───
if ($targetId !== $userId) {
    $db->query("INSERT INTO profile_views (viewer_id, viewed_id) VALUES ($userId, $targetId) ON DUPLICATE KEY UPDATE viewed_at = NOW()");
    require_once __DIR__ . '/../notifications/send_push.php';
    sendProfileViewNotification($db, $userId, $targetId);
}

$db->close();
exit();
