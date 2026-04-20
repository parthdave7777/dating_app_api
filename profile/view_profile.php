<?php
// profile/view_profile.php — record a profile view
require_once __DIR__ . '/../config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'Method not allowed']);
    exit();
}

$userId = getAuthUserId();
$body   = json_decode(file_get_contents('php://input'), true);
$viewedId = (int) ($body['viewed_user_id'] ?? 0);

if (!$viewedId || $viewedId === $userId) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid viewed_user_id']);
    exit();
}

$db = getDB();
error_log("ViewProfile: User $userId viewing $viewedId");

// 1. Check if they are matched (Matches view for free)
$matchCheck = $db->prepare("
    SELECT id FROM matches 
    WHERE (user1_id = ? AND user2_id = ?) 
       OR (user1_id = ? AND user2_id = ?)
    LIMIT 1
");
$matchCheck->bind_param('iiii', $userId, $viewedId, $viewedId, $userId);
$matchCheck->execute();
$isMatched = $matchCheck->get_result()->num_rows > 0;
$matchCheck->close();

// 2. Check if already viewed (to determine if we should charge)
$viewCheck = $db->prepare("SELECT id FROM profile_views WHERE viewer_id = ? AND viewed_id = ?");
$viewCheck->bind_param('ii', $userId, $viewedId);
$viewCheck->execute();
$alreadyViewed = $viewCheck->get_result()->num_rows > 0;
$viewCheck->close();

error_log("ViewProfile check: isMatched=" . ($isMatched ? '1' : '0') . " alreadyViewed=" . ($alreadyViewed ? '1' : '0'));

$newBalance = null;
$db->begin_transaction();

try {
    if (!$isMatched && !$alreadyViewed) {
        if (!deductCredits($db, $userId, CREDIT_COST_PROFILE_VIEW, "Viewed Profile ID: $viewedId")) {
            throw new Exception("INSUFFICIENT_CREDITS");
        }
    }

    // 3. Upsert view record
    $stmt = $db->prepare(
        "INSERT INTO profile_views (viewer_id, viewed_id)
         VALUES (?, ?)
         ON DUPLICATE KEY UPDATE viewed_at = NOW()"
    );
    $stmt->bind_param('ii', $userId, $viewedId);
    $stmt->execute();
    $stmt->close();
    
    $db->commit();
    $newBalance = getUserCredits($db, $userId);
} catch (Exception $e) {
    $db->rollback();
    if ($e->getMessage() === "INSUFFICIENT_CREDITS") {
        echo json_encode(['status' => 'error', 'message' => 'Insufficient credits to view profile', 'error_code' => 'INSUFFICIENT_CREDITS']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Transaction failed: ' . $e->getMessage()]);
    }
    exit();
}

// ── Send Notification (with cooldown) ─────────────────────────
require_once __DIR__ . '/../notifications/send_push.php';

// Check if a notification for this viewer -> viewed was sent in the last 1 hour
// to avoid spamming the user if they view the profile multiple times in a session.
$notifCheck = $db->prepare(
    "SELECT id FROM notifications 
     WHERE user_id = ? AND type = 'profile_view' AND data LIKE ? 
     AND created_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)"
);
$likePattern = '%"sender_id":"' . $userId . '"%';
$notifCheck->bind_param('is', $viewedId, $likePattern);
$notifCheck->execute();
$hasRecent = $notifCheck->get_result()->num_rows > 0;
$notifCheck->close();

if (!$hasRecent) {
    // Helper to get viewer info
    $infoStmt = $db->prepare("
        SELECT u.full_name, (SELECT photo_url FROM user_photos WHERE user_id = u.id AND is_dp = 1 LIMIT 1) as photo
        FROM users u WHERE u.id = ?
    ");
    $infoStmt->bind_param('i', $userId);
    $infoStmt->execute();
    $infoRow = $infoStmt->get_result()->fetch_assoc();
    $infoStmt->close();
    
    $viewerName  = $infoRow['full_name'] ?? 'Someone';
    $viewerPhoto = $infoRow['photo'] ?? '';
    
    sendPush($db, $viewedId, 'profile_view', "Profile Viewed! 👀", "$viewerName just viewed your profile.", [
        'sender_id'    => (string)$userId,
        'sender_name'  => $viewerName,
        'sender_photo' => $viewerPhoto,
    ]);
}

$db->close();
echo json_encode(['status' => 'success', 'new_balance' => $newBalance]);
