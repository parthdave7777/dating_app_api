<?php
// profile/swipe.php
require_once __DIR__ . '/../config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'Method not allowed']);
    exit();
}

$userId = getAuthUserId();
$body   = json_decode(file_get_contents('php://input'), true);

$swipedUserId = (int)   ($body['swiped_user_id'] ?? 0);
$action       = strtolower(trim($body['action'] ?? '')); 

if (!$swipedUserId || !in_array($action, ['like', 'dislike', 'superlike', 'compliment', 'rewind'])) {
    echo json_encode(['status' => 'error', 'message' => 'swiped_user_id and valid action required']);
    exit();
}
$db = getDB();

// ─── REWIND LOGIC ──────────────────────────────────────────────
if ($action === 'rewind') {
    $db->begin_transaction();
    try {
        if (!deductCredits($db, $userId, CREDIT_COST_REWIND, "Rewind interaction with ID: $swipedUserId")) {
            throw new Exception("INSUFFICIENT_CREDITS");
        }

        $del = $db->prepare("DELETE FROM swipes WHERE swiper_id = ? AND swiped_id = ?");
        $del->bind_param('ii', $userId, $swipedUserId);
        $del->execute();
        $del->close();
        
        $db->commit();
        echo json_encode(['status' => 'success', 'message' => 'Action rewound', 'new_balance' => getUserCredits($db, $userId)]);
    } catch (Exception $e) {
        $db->rollback();
        if ($e->getMessage() === "INSUFFICIENT_CREDITS") {
            echo json_encode(['status' => 'error', 'message' => 'Insufficient credits for rewind', 'error_code' => 'INSUFFICIENT_CREDITS']);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Rewind failed: ' . $e->getMessage()]);
        }
    }
    exit();
}

// Anti-spam: check if user swiped more than 100 times in the last hour using Redis
$redis = getRedis();
if ($redis) {
    $spamKey = "swipe_cnt_$userId";
    $count = $redis->incr($spamKey);
    if ($count == 1) $redis->expire($spamKey, 3600);
    if ($count > 100) {
        echo json_encode(['status' => 'error', 'message' => 'Take a break! You are swiping too fast.', 'error_code' => 'RATE_LIMITED']);
        exit();
    }
} else {
    // Fallback to DB check if Redis is offline
    $spamCheck = $db->prepare("SELECT COUNT(*) AS cnt FROM swipes WHERE swiper_id = ? AND created_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)");
    $spamCheck->bind_param('i', $userId);
    $spamCheck->execute();
    $spamRes = $spamCheck->get_result()->fetch_assoc();
    $spamCheck->close();
    if ($spamRes['cnt'] > 100) {
        echo json_encode(['status' => 'error', 'message' => 'Rate limit reached.', 'error_code' => 'RATE_LIMITED']);
        exit();
    }
}

// ── CREDIT DEDUCTION ──────────────────────────────────────────
$cost = 0;
if ($action === 'like')       $cost = CREDIT_COST_LIKE;
if ($action === 'superlike')  $cost = CREDIT_COST_SUPERLIKE;
if ($action === 'compliment') $cost = CREDIT_COST_COMPLIMENT;

$db->begin_transaction();
try {
    if ($cost > 0) {
        if (!deductCredits($db, $userId, $cost, "Discovery: " . ucfirst($action) . " on User ID: $swipedUserId")) {
            throw new Exception("INSUFFICIENT_CREDITS");
        }
    }

    // Save swipe (ignore duplicate)
    $now = date('Y-m-d H:i:s');
    $stmt = $db->prepare(
        "INSERT INTO swipes (swiper_id, swiped_id, action, created_at) VALUES (?, ?, ?, ?)
         ON DUPLICATE KEY UPDATE action = ?, created_at = ?"
    );
    $stmt->bind_param('iissss', $userId, $swipedUserId, $action, $now, $action, $now);
    $stmt->execute();
    $stmt->close();
    
    $db->commit();
} catch (Exception $e) {
    $db->rollback();
    if ($e->getMessage() === "INSUFFICIENT_CREDITS") {
        echo json_encode(['status' => 'error', 'message' => 'Insufficient credits. Wait for daily refresh or upgrade.', 'error_code' => 'INSUFFICIENT_CREDITS']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Action failed: ' . $e->getMessage()]);
    }
    exit();
}

// ── Update last_active for swiper ─────────────────────────
$db->query("UPDATE users SET last_active = '$now' WHERE id = $userId");

// ── ELO Score Update ──────────────────────────────────────
// Get both users' current ELO scores
$eloStmt = $db->prepare("SELECT id, elo_score FROM users WHERE id IN (?, ?)");
$eloStmt->bind_param('ii', $userId, $swipedUserId);
$eloStmt->execute();
$eloResult = $eloStmt->get_result();
$eloStmt->close();

$eloMap = [];
while ($row = $eloResult->fetch_assoc()) {
    $eloMap[(int)$row['id']] = (int)$row['elo_score'];
}

$myElo    = $eloMap[$userId]    ?? 1000;
$theirElo = $eloMap[$swipedUserId] ?? 1000;

if ($action === 'like') {
    $newTheirElo = max(100, $theirElo + 10);
} elseif ($action === 'superlike') {
    $newTheirElo = max(100, $theirElo + 25);
} elseif ($action === 'dislike') {
    $newTheirElo = max(100, $theirElo - 5);
} else {
    $newTheirElo = $theirElo;
}

$eloUpdate = $db->prepare("UPDATE users SET elo_score = ? WHERE id = ?");
$eloUpdate->bind_param('ii', $newTheirElo, $swipedUserId);
$eloUpdate->execute();
$eloUpdate->close();

$isMatch = false;
$matchId = null;

if ($action === 'like' || $action === 'superlike') {
    // 1. Check if it's a mutual match
    // Mutual if other user has swiped LIKE, SUPERLIKE, or COMPLIMENT on you
    $checkStmt = $db->prepare(
        "SELECT id FROM swipes
         WHERE swiper_id = ? AND swiped_id = ? AND action IN ('like', 'superlike', 'compliment')"
    );
    $checkStmt->bind_param('ii', $swipedUserId, $userId);
    $checkStmt->execute();
    $checkRes = $checkStmt->get_result();
    $checkStmt->close();

    if ($checkRes->num_rows > 0) {
        // It's a match!
        $u1 = min($userId, $swipedUserId);
        $u2 = max($userId, $swipedUserId);

        $matchStmt = $db->prepare("INSERT IGNORE INTO matches (user1_id, user2_id, created_at) VALUES (?, ?, ?)");
        $matchStmt->bind_param('iis', $u1, $u2, $now);
        $matchStmt->execute();
        $matchId = $db->insert_id;
        if (!$matchId) {
            $mGet = $db->prepare("SELECT id FROM matches WHERE user1_id=? AND user2_id=?");
            $mGet->bind_param('ii', $u1, $u2);
            $mGet->execute();
            $matchId = $mGet->get_result()->fetch_assoc()['id'] ?? null;
            $mGet->close();
        }
        $matchStmt->close();

        $isMatch = true;
        
        require_once __DIR__ . '/../notifications/send_push.php';
        sendMatchNotification($db, $userId, $swipedUserId, (int)$matchId);

    } else {
        // Not a match (yet), just a regular like/superlike
        require_once __DIR__ . '/../notifications/send_push.php';
        if ($action === 'superlike') {
            sendSuperLikeNotification($db, $userId, $swipedUserId);
        } else {
            sendLikeNotification($db, $userId, $swipedUserId);
        }
    }
}

$newBalance = getUserCredits($db, $userId);

// NITRO CACHE CLEANUP
clearProfileCache($userId);
clearProfileCache($swipedUserId);

$db->close();

echo json_encode([
    'status'   => 'success',
    'is_match' => $isMatch,
    'match_id' => $matchId,
    'new_balance' => $newBalance
]);
?>
