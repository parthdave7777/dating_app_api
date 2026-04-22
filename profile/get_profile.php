<?php
// profile/get_profile.php
require_once __DIR__ . '/../config.php';

$userId = getAuthUserId();
$db     = getDB();

if (isset($_GET['target_id']) && (int)$_GET['target_id'] !== $userId) {
    $targetId = (int) $_GET['target_id'];
    
    // 1. Notification Logic (Keep this in DB, very fast indexed read)
    $viewCheck = $db->prepare("SELECT viewed_at FROM profile_views WHERE viewer_id = ? AND viewed_id = ?");
    $viewCheck->bind_param('ii', $userId, $targetId);
    $viewCheck->execute();
    $viewRes = $viewCheck->get_result()->fetch_assoc();
    $viewCheck->close();

    $shouldNotify = true;
    if ($viewRes && (time() - strtotime($viewRes['viewed_at']) < (6 * 3600))) {
        $shouldNotify = false;
    }

    $db->query("INSERT INTO profile_views (viewer_id, viewed_id) VALUES ($userId, $targetId) ON DUPLICATE KEY UPDATE viewed_at = NOW()");

    if ($shouldNotify) {
        dispatchAsync([
            'action_type'  => 'social_push',
            'sub_type'     => 'profile_view',
            'recipient_id' => $targetId,
            'sender_id'    => $userId
        ]);
    }

    // 2. Block Check
    $blockStmt = $db->prepare("SELECT 1 FROM blocks WHERE (blocker_id = ? AND blocked_user_id = ?) OR (blocker_id = ? AND blocked_user_id = ?)");
    $blockStmt->bind_param('iiii', $userId, $targetId, $targetId, $userId);
    $blockStmt->execute();
    if ($blockStmt->get_result()->num_rows > 0) {
        $blockStmt->close();
        http_response_code(403);
        echo json_encode(['status' => 'error', 'message' => 'Profile is unavailable']);
        exit();
    }
    $blockStmt->close();
} else {
    $targetId = $userId;
}

// 2. Fetch Profile from NITRO (Redis) Cache
$profileData = getCachedProfileData($db, $targetId);
if (!$profileData) {
    echo json_encode(['status' => 'error', 'message' => 'User not found']);
    exit();
}

$user   = $profileData['user'];
$photos = $profileData['photos'];
$dpUrl  = $profileData['dp_url'];
$posts  = $profileData['posts'];

    $isMatch = false;
    $matchId = null;
    $hasSwiped = false;
    $distance = null;

    if ($userId !== $targetId) {
        // Multi-purpose "Companion Query"
        $compSql = "
            SELECT 
                (SELECT id FROM matches WHERE (user1_id = ? AND user2_id = ?) OR (user1_id = ? AND user2_id = ?) LIMIT 1) as match_id,
                (SELECT id FROM swipes WHERE swiper_id = ? AND swiped_id = ? LIMIT 1) as swipe_id,
                u.latitude, u.longitude
            FROM users u WHERE u.id = ?
        ";
        $u1 = min($userId, $targetId); $u2 = max($userId, $targetId);
        $cStmt = $db->prepare($compSql);
        $cStmt->bind_param('iiiiiii', $u1, $u2, $u1, $u2, $userId, $targetId, $userId);
        $cStmt->execute();
        $cRes = $cStmt->get_result()->fetch_assoc();
        $cStmt->close();

        if ($cRes) {
            $matchId = $cRes['match_id'] ? (int)$cRes['match_id'] : null;
            $isMatch = ($matchId !== null);
            $hasSwiped = ($cRes['swipe_id'] !== null);
            
            if ($cRes['latitude'] && $user['latitude']) {
                $distance = haversineKm((float)$cRes['latitude'], (float)$cRes['longitude'], (float)$user['latitude'], (float)$user['longitude']);
            }
        }
    }

$db->close();

header('Content-Type: application/json');
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
        'photos'            =>        $photos,
        'dp_url'            =>        $dpUrl,
        'posts'             =>        $posts,
        'distance_km'       =>        $distance,
        'discovery_min_age'  => (int)  ($user['discovery_min_age']  ?? 18),
        'discovery_max_age'  => (int)  ($user['discovery_max_age']  ?? 55),
        'discovery_max_dist' => (int)  ($user['discovery_max_dist'] ?? 50),
        'discovery_min_dist' => (int)  ($user['discovery_min_dist'] ?? 0),
        'global_discovery'   => (bool) ($user['global_discovery']   ?? 1),
        'stealth_radius'     => (int)  ($user['stealth_radius']     ?? 0),
        'is_match'           => (bool) $isMatch,
        'match_id'           => $matchId,
        'has_swiped'         => (bool) $hasSwiped,
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
