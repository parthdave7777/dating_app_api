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

// 1. One complement only check
$checkStmt = $db->prepare("SELECT id FROM compliments WHERE sender_id = ? AND receiver_id = ?");
$checkStmt->bind_param('ii', $userId, $receiverId);
$checkStmt->execute();
if ($checkStmt->get_result()->num_rows > 0) {
    $checkStmt->close();
    $db->close();
    echo json_encode(['status' => 'error', 'message' => 'You have already sent a compliment to this user']);
    exit();
}
$checkStmt->close();

// 2. Insert compliment
$stmt = $db->prepare("INSERT INTO compliments (sender_id, receiver_id, message) VALUES (?, ?, ?)");
$stmt->bind_param('iis', $userId, $receiverId, $message);
$stmt->execute();
$stmt->close();

// 3. Increment ELO (+15 for compliment)
$db->query("UPDATE users SET elo_score = elo_score + 15 WHERE id = $receiverId");

// 4. Record as 'compliment' in swipes table
$swipeStmt = $db->prepare("
    INSERT INTO swipes (swiper_id, swiped_id, action) VALUES (?, ?, 'compliment')
    ON DUPLICATE KEY UPDATE action = VALUES(action), created_at = NOW()
");
$swipeStmt->bind_param('ii', $userId, $receiverId);
$swipeStmt->execute();
$swipeStmt->close();

// 5. Match Processing
$isMatch = false;
$matchId = null;

$matchCheck = $db->prepare("SELECT id FROM swipes WHERE swiper_id = ? AND swiped_id = ? AND action IN ('like', 'superlike', 'compliment')");
$matchCheck->bind_param('ii', $receiverId, $userId);
$matchCheck->execute();
$matchRes = $matchCheck->get_result();
$matchCheck->close();

if ($matchRes->num_rows > 0) {
    $isMatch = true;
    $u1 = min($userId, $receiverId);
    $u2 = max($userId, $receiverId);
    $stmt = $db->prepare("INSERT IGNORE INTO matches (user1_id, user2_id) VALUES (?, ?)");
    $stmt->bind_param('ii', $u1, $u2);
    $stmt->execute();
    $matchId = $db->insert_id;
    if (!$matchId) {
        $getM = $db->prepare("SELECT id FROM matches WHERE user1_id = ? AND user2_id = ?");
        $getM->bind_param('ii', $u1, $u2);
        $getM->execute();
        $matchId = $getM->get_result()->fetch_assoc()['id'] ?? null;
        $getM->close();
    }
    $stmt->close();
}

// 6. Send Notifications
require_once __DIR__ . '/../notifications/send_push.php';

if ($isMatch) {
    sendMatchNotification($db, $userId, $receiverId, (int)$matchId);
} else {
    sendComplimentNotification($db, $userId, $receiverId, $message);
}

$db->close();

echo json_encode([
    'status' => 'success',
    'is_match' => $isMatch,
    'match_id' => $matchId
]);
