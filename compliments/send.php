<?php
// compliments/send.php
require_once __DIR__ . '/../config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'Method not allowed']);
    exit();
}

$userId = getAuthUserId();
$body   = json_decode(file_get_contents('php://input'), true);

$receiverId = (int) ($body['receiver_id'] ?? 0);
$message    = trim($body['message'] ?? '');

if (!$receiverId || empty($message)) {
    echo json_encode(['status' => 'error', 'message' => 'receiver_id and message required']);
    exit();
}

$db = getDB();

// ─── 1. CREDIT DEDUCTION (Sync) ──────────────────────────────
if (!deductCredits($db, $userId, CREDIT_COST_COMPLIMENT, "Sent Compliment")) {
    echo json_encode(['status' => 'error', 'message' => 'Insufficient credits', 'error_code' => 'INSUFFICIENT_CREDITS']);
    exit();
}

// ─── 2. ATOMIC CHECKS & INSERT (Sync) ────────────────────────
$check = $db->query("SELECT id FROM compliments WHERE sender_id = $userId AND receiver_id = $receiverId");
if ($check->num_rows > 0) {
    echo json_encode(['status' => 'error', 'message' => 'Compliment already sent']);
    exit();
}

$stmt = $db->prepare("INSERT INTO compliments (sender_id, receiver_id, message) VALUES (?, ?, ?)");
$stmt->bind_param('iis', $userId, $receiverId, $message);
$stmt->execute();
$stmt->close();

// Record in swipes table
$db->query("INSERT INTO swipes (swiper_id, swiped_id, action) VALUES ($userId, $receiverId, 'compliment') ON DUPLICATE KEY UPDATE action='compliment', created_at=NOW()");

// ─── 3. RESPOND IMMEDIATELY (Ultra-Speed) ─────────────────────
$newBalance = getUserCredits($db, $userId);
sendResponseAndContinue([
    'status'      => 'success',
    'is_match'    => false, 
    'match_id'    => null,
    'new_balance' => $newBalance
]);

// ─── 4. BACKGROUND PROCESSING ───────────────────────────────

// A. ELO (+15)
$db->query("UPDATE users SET elo_score = elo_score + 15 WHERE id = $receiverId");

// B. Match Processing
$checkMatch = $db->prepare("SELECT id FROM swipes WHERE swiper_id = ? AND swiped_id = ? AND action IN ('like', 'superlike', 'compliment')");
$checkMatch->bind_param('ii', $receiverId, $userId);
$checkMatch->execute();
$matchRes = $checkMatch->get_result();
$checkMatch->close();

require_once __DIR__ . '/../notifications/send_push.php';

if ($matchRes->num_rows > 0) {
    $u1 = min($userId, $receiverId);
    $u2 = max($userId, $receiverId);
    $db->query("INSERT IGNORE INTO matches (user1_id, user2_id) VALUES ($u1, $u2)");
    $matchId = $db->insert_id ?: $db->query("SELECT id FROM matches WHERE user1_id=$u1 AND user2_id=$u2")->fetch_assoc()['id'];
    sendMatchNotification($db, $userId, $receiverId, (int)$matchId);
} else {
    sendComplimentNotification($db, $userId, $receiverId, $message);
}

$db->close();
exit();
