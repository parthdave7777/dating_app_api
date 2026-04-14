<?php
// profile/swipe.php
require_once __DIR__ . '/../config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'Method not allowed']);
    exit();
}

$userId = getAuthUserId();
$body   = json_decode(file_get_contents('php://input'), true);

$swipedUserId = (int) ($body['swiped_user_id'] ?? 0);
$action       = trim($body['action'] ?? ''); // like | dislike | superlike | compliment

if (!$swipedUserId || !in_array($action, ['like', 'dislike', 'superlike', 'compliment'])) {
    echo json_encode(['status' => 'error', 'message' => 'swiped_user_id and valid action required']);
    exit();
}

$db = getDB();

// ─── 1. CREDIT DEDUCTION (Sync) ──────────────────────────────
$cost = 0;
if ($action === 'like')       $cost = CREDIT_COST_LIKE;
if ($action === 'superlike')  $cost = CREDIT_COST_SUPERLIKE;
if ($action === 'compliment') $cost = CREDIT_COST_COMPLIMENT;

if ($cost > 0) {
    if (!deductCredits($db, $userId, $cost, "Discovery: " . ucfirst($action))) {
        echo json_encode(['status' => 'error', 'message' => 'Insufficient credits', 'error_code' => 'INSUFFICIENT_CREDITS']);
        exit();
    }
}

// ─── 2. SAVE SWIPE (Sync) ────────────────────────────────────
$now = date('Y-m-d H:i:s');
$stmt = $db->prepare("INSERT INTO swipes (swiper_id, swiped_id, action, created_at) VALUES (?, ?, ?, ?) ON DUPLICATE KEY UPDATE action = ?, created_at = ?");
$stmt->bind_param('iissss', $userId, $swipedUserId, $action, $now, $action, $now);
$stmt->execute();
$stmt->close();

// ─── 3. RESPOND IMMEDIATELY (Ultra-Speed) ─────────────────────
// The user gets "Success" here. Everything below runs in background.
$newBalance = getUserCredits($db, $userId);
sendResponseAndContinue([
    'status'      => 'success',
    'is_match'    => false, // Handled in UI via background notification
    'match_id'    => null,
    'new_balance' => $newBalance
]);

// ─── 4. BACKGROUND PROCESSING ───────────────────────────────

// A. Spam check (Deferred)
$spamCheck = $db->prepare("SELECT COUNT(*) AS cnt FROM swipes WHERE swiper_id = ? AND created_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)");
$spamCheck->bind_param('i', $userId);
$spamCheck->execute();
$spamCount = $spamCheck->get_result()->fetch_assoc()['cnt'] ?? 0;
$spamCheck->close();

// B. ELO Update
$eloRes = $db->query("SELECT elo_score FROM users WHERE id = $swipedUserId")->fetch_assoc();
$theirElo = (int)($eloRes['elo_score'] ?? 1000);
if ($action === 'like')       $newElo = $theirElo + 10;
elseif ($action === 'superlike') $newElo = $theirElo + 25;
elseif ($action === 'dislike')   $newElo = $theirElo - 5;
else $newElo = $theirElo;
$db->query("UPDATE users SET elo_score = $newElo WHERE id = $swipedUserId");

// C. Match Detection & Notifications
if ($action === 'like' || $action === 'superlike') {
    $checkMatch = $db->prepare("SELECT id FROM swipes WHERE swiper_id = ? AND swiped_id = ? AND action IN ('like', 'superlike', 'compliment')");
    $checkMatch->bind_param('ii', $swipedUserId, $userId);
    $checkMatch->execute();
    $matchRes = $checkMatch->get_result();
    $checkMatch->close();

    require_once __DIR__ . '/../notifications/send_push.php';

    if ($matchRes->num_rows > 0) {
        $u1 = min($userId, $swipedUserId);
        $u2 = max($userId, $swipedUserId);
        $db->query("INSERT IGNORE INTO matches (user1_id, user2_id, created_at) VALUES ($u1, $u2, '$now')");
        $matchId = $db->insert_id ?: $db->query("SELECT id FROM matches WHERE user1_id=$u1 AND user2_id=$u2")->fetch_assoc()['id'];
        sendMatchNotification($db, $userId, $swipedUserId, (int)$matchId);
    } else {
        if ($action === 'superlike') sendSuperLikeNotification($db, $userId, $swipedUserId);
        else sendLikeNotification($db, $userId, $swipedUserId);
    }
}

$db->close();
exit();
