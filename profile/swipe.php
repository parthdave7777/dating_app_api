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
$action       = trim($body['action'] ?? '');

if (!$swipedUserId || !in_array($action, ['like', 'dislike', 'superlike', 'compliment'])) {
    echo json_encode(['status' => 'error', 'message' => 'swiped_user_id and valid action required']);
    exit();
}

$db = getDB();

// 🚀 SPEED OPT 1: COMBINED SELECT (Credits + Spam + Their Info)
// One trip to get all state.
$stmt = $db->prepare("
    SELECT 
        (SELECT credits + premium_credits FROM users WHERE id = ?) AS my_credits,
        (SELECT COUNT(*) FROM swipes WHERE swiper_id = ? AND created_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)) AS hourly_swipes,
        (SELECT elo_score FROM users WHERE id = ?) AS their_elo
");
$stmt->bind_param('iii', $userId, $userId, $swipedUserId);
$stmt->execute();
$state = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$state) {
    echo json_encode(['status' => 'error', 'message' => 'Profile verification failed']);
    exit();
}

// Validation Logic
$cost = ($action === 'like') ? CREDIT_COST_LIKE : (($action === 'superlike') ? CREDIT_COST_SUPERLIKE : (($action === 'compliment') ? CREDIT_COST_COMPLIMENT : 0));
if ($state['my_credits'] < $cost) {
    echo json_encode(['status' => 'error', 'message' => 'Insufficient credits', 'error_code' => 'INSUFFICIENT_CREDITS']);
    exit();
}
if ($state['hourly_swipes'] >= 100) {
    echo json_encode(['status' => 'error', 'message' => 'Rate limited', 'error_code' => 'RATE_LIMITED']);
    exit();
}

// 🚀 SPEED OPT 2: COMBINED UPDATE/INSERT (Credit + Swipe + ELO + Active)
// We use a transaction or multi-statement but for reliability we'll do them fast.
$now = date('Y-m-d H:i:s');
$db->begin_transaction();
try {
    // 1. Deduct Credits
    if ($cost > 0) {
        $db->query("UPDATE users SET credits = credits - $cost WHERE id = $userId AND credits >= $cost");
        if ($db->affected_rows === 0 && $cost > 0) {
            // Try premium credits
            $db->query("UPDATE users SET premium_credits = premium_credits - $cost WHERE id = $userId AND premium_credits >= $cost");
        }
    }
    
    // 2. Insert Swipe
    $stmt = $db->prepare("INSERT INTO swipes (swiper_id, swiped_id, action, created_at) VALUES (?, ?, ?, ?) ON DUPLICATE KEY UPDATE action = ?, created_at = ?");
    $stmt->bind_param('iissss', $userId, $swipedUserId, $action, $now, $action, $now);
    $stmt->execute();
    
    // 3. ELO Update
    $theirElo = (int)$state['their_elo'];
    if ($action === 'like')       $newElo = $theirElo + 10;
    elseif ($action === 'superlike') $newElo = $theirElo + 25;
    elseif ($action === 'dislike')   $newElo = $theirElo - 5;
    else $newElo = $theirElo;
    $db->query("UPDATE users SET elo_score = $newElo, last_active = '$now' WHERE id = $swipedUserId");
    $db->query("UPDATE users SET last_active = '$now' WHERE id = $userId");
    
    $db->commit();
} catch (Exception $e) {
    $db->rollback();
    echo json_encode(['status' => 'error', 'message' => 'Transaction failed']);
    exit();
}

// 🚀 SPEED OPT 3: MATCH CHECK & RESPOND
$isMatch = false;
$matchId = null;
if ($action === 'like' || $action === 'superlike') {
    $res = $db->query("SELECT id FROM swipes WHERE swiper_id = $swipedUserId AND swiped_id = $userId AND action IN ('like', 'superlike', 'compliment')");
    $matchRes = $res->fetch_assoc();
    if ($matchRes) {
        $isMatch = true;
        $u1 = min($userId, $swipedUserId);
        $u2 = max($userId, $swipedUserId);
        $db->query("INSERT IGNORE INTO matches (user1_id, user2_id) VALUES ($u1, $u2)");
        $matchId = $db->insert_id ?: $db->query("SELECT id FROM matches WHERE user1_id=$u1 AND user2_id=$u2")->fetch_assoc()['id'];
    }
}

// Respond
sendResponseAndContinue([
    'status'      => 'success',
    'is_match'    => $isMatch,
    'match_id'    => (int)$matchId ?: null,
    'new_balance' => getUserCredits($db, $userId)
]);

// ─── BACKGROUND NOTIFICATIONS ───────────────────────────────
if ($isMatch || $action === 'like' || $action === 'superlike') {
    require_once __DIR__ . '/../notifications/send_push.php';
    if ($isMatch) sendMatchNotification($db, $userId, $swipedUserId, (int)$matchId);
    elseif ($action === 'superlike') sendSuperLikeNotification($db, $userId, $swipedUserId);
    else sendLikeNotification($db, $userId, $swipedUserId);
}

$db->close();
exit();
