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
$action       = trim($body['action'] ?? ''); // like | dislike

if (!$swipedUserId || !in_array($action, ['like', 'dislike'])) {
    echo json_encode(['status' => 'error', 'message' => 'swiped_user_id and valid action required']);
    exit();
}
$db = getDB();

// Anti-spam: check if user swiped more than 100 times in the last hour
$spamCheck = $db->prepare(
    "SELECT COUNT(*) AS cnt FROM swipes 
     WHERE swiper_id = ? AND created_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)"
);
$spamCheck->bind_param('i', $userId);
$spamCheck->execute();
$spamRow = $spamCheck->get_result()->fetch_assoc();
$spamCheck->close();

if ((int)$spamRow['cnt'] >= 100) {
    $db->close();
    echo json_encode([
        'status' => 'error',
        'message' => 'Slow down! Take a break and come back soon.',
        'error_code' => 'RATE_LIMITED'
    ]);
    exit();
}


// Save swipe (ignore duplicate)
$stmt = $db->prepare(
    "INSERT IGNORE INTO swipes (swiper_id, swiped_id, action) VALUES (?, ?, ?)"
);
$stmt->bind_param('iis', $userId, $swipedUserId, $action);
$stmt->execute();
$stmt->close();

// ── Update last_active for swiper ─────────────────────────
$db->query("UPDATE users SET last_active = NOW() WHERE id = $userId");

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

// ELO change rules:
// Like/Superlike received → their score goes UP by 10
// Dislike received → their score goes DOWN by 5
// Superlike received → their score goes UP by 20 (stronger signal)
// Score floor is 100, no ceiling

if ($action === 'like') {
    $newTheirElo = max(100, $theirElo + 10);
} elseif ($action === 'dislike') {
    $newTheirElo = max(100, $theirElo - 5);
} else {
    $newTheirElo = $theirElo;
}

$eloUpdate = $db->prepare("UPDATE users SET elo_score = ? WHERE id = ?");
$eloUpdate->bind_param('ii', $newTheirElo, $swipedUserId);
$eloUpdate->execute();
$eloUpdate->close();
// ── End ELO Update ────────────────────────────────────────

$isMatch = false;
$matchId = null;

if ($action === 'like') {
    // 1. Check if it's a mutual match
    $checkStmt = $db->prepare(
        "SELECT id FROM swipes
         WHERE swiper_id = ? AND swiped_id = ? AND action = 'like'"
    );
    $checkStmt->bind_param('ii', $swipedUserId, $userId);
    $checkStmt->execute();
    $checkRes = $checkStmt->get_result();
    $checkStmt->close();

    if ($checkRes->num_rows > 0) {
        // It's a match!
        $u1 = min($userId, $swipedUserId);
        $u2 = max($userId, $swipedUserId);

        $matchStmt = $db->prepare("INSERT IGNORE INTO matches (user1_id, user2_id) VALUES (?, ?)");
        $matchStmt->bind_param('ii', $u1, $u2);
        $matchStmt->execute();
        $matchId = $db->insert_id ?: null;

        if (!$matchId) {
            $fetchStmt = $db->prepare("SELECT id FROM matches WHERE user1_id = ? AND user2_id = ?");
            $fetchStmt->bind_param('ii', $u1, $u2);
            $fetchStmt->execute();
            $mRow = $fetchStmt->get_result()->fetch_assoc();
            $fetchStmt->close();
            $matchId = $mRow['id'] ?? null;
        }
        $matchStmt->close();

        $isMatch = true;
        // Mutual match notification
        sendMatchNotification($db, $userId, $swipedUserId, (int)$matchId);
    } else {
        // Just a one-way like
        sendLikeNotification($db, $userId, $swipedUserId);
    }
}

$db->close();

echo json_encode([
    'status'   => 'success',
    'is_match' => $isMatch,
    'match_id' => $matchId,
]);

// ─── Helpers ──────────────────────────────────────────────────
function sendMatchNotification(mysqli $db, int $fromId, int $toId, int $matchId): void {
    require_once __DIR__ . '/../notifications/send_push.php';

    $info = getSenderInfo($db, $fromId);
    $name = $info['name'];
    sendPush($db, $toId, 'match', "Match Found! 💖", "You matched with $name. Start chatting or stand knowing each other!", [
        'match_id'     => (string) $matchId,
        'sender_id'    => (string) $fromId,
        'sender_name'  => $name,
        'sender_photo' => $info['photo'] ?? ''
    ]);
}

function sendLikeNotification(mysqli $db, int $fromId, int $toId): void {
    require_once __DIR__ . '/../notifications/send_push.php';

    $info = getSenderInfo($db, $fromId);
    $name = $info['name'];
    sendPush($db, $toId, 'like', "Someone Likes You! Spark ✨", "A new person has swiped right on you. Check them out!", [
        'swiper_id'    => (string) $fromId,
        'sender_id'    => (string) $fromId,   // ADD: matches Flutter's sender_id self-filter check
        'sender_name'  => $name,
        'sender_photo' => $info['photo'] ?? ''
    ]);
}

function getSenderInfo(mysqli $db, int $id): array {
    $stmt = $db->prepare("
        SELECT u.full_name, (SELECT photo_url FROM user_photos WHERE user_id = u.id AND is_dp = 1 LIMIT 1) as photo
        FROM users u WHERE u.id = ?
    ");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $res = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return [
        'name'  => $res['full_name'] ?? 'Someone',
        'photo' => cloudinaryTransform($res['photo'] ?? '', 'w_200,c_thumb,g_face,q_auto,f_auto')
    ];
}
