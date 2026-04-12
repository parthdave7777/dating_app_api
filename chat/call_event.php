<?php
/**
 * chat/call_event.php
 *
 * Injects a system message into the chat when a call event occurs.
 * Called from the Flutter app via ApiService.sendCallEvent().
 *
 * POST JSON body:
 *   match_id  (int)    — The match/conversation ID
 *   event     (string) — One of: call_started, call_ended, call_missed, call_declined
 *   duration  (int)    — Optional: seconds, used for call_ended events
 */

header('Content-Type: application/json');
require_once __DIR__ . '/../config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'Method not allowed']);
    exit();
}

$userId = getAuthUserId();

$input    = json_decode(file_get_contents('php://input'), true) ?? [];
$matchId  = (int) ($input['match_id'] ?? 0);
$event    = trim($input['event'] ?? '');
$duration = isset($input['duration']) ? (int) $input['duration'] : null;

$validEvents = ['call_started', 'call_ended', 'call_missed', 'call_declined'];
if ($matchId <= 0 || !in_array($event, $validEvents, true)) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid parameters']);
    exit();
}

$db = getDB();

// Verify user is part of this match
$authStmt = $db->prepare(
    "SELECT user1_id, user2_id FROM matches WHERE id = ? AND (user1_id = ? OR user2_id = ?)"
);
$authStmt->bind_param('iii', $matchId, $userId, $userId);
$authStmt->execute();
$match = $authStmt->get_result()->fetch_assoc();
$authStmt->close();

if (!$match) {
    echo json_encode(['status' => 'error', 'message' => 'Match not found or access denied']);
    $db->close();
    exit();
}

// Prevent double-logging duplicates (from both caller and receiver screens)
$dupCheck = $db->prepare("SELECT id FROM messages WHERE match_id = ? AND call_event = ? AND created_at > DATE_SUB(NOW(), INTERVAL 1 MINUTE)");
$dupCheck->bind_param('is', $matchId, $event);
$dupCheck->execute();
if ($dupCheck->get_result()->num_rows > 0) {
    echo json_encode(['status' => 'success', 'message' => 'Event already recorded recently']);
    $dupCheck->close();
    $db->close();
    exit();
}
$dupCheck->close();

// ── Credit Deduction for Calls ────────────────────────────────
if ($event === 'call_ended' && $duration > 5) { // More than 5 sec counts
    $minutes = ceil($duration / 60);
    $cost = $minutes * CREDIT_COST_CALL_MIN;
    deductCredits($db, $userId, $cost, "Video call: $minutes min");
}

// Map event to message type
// call_started → 'call_event', others map to their own type for better Flutter routing
$typeMap = [
    'call_started'  => 'call_event',
    'call_ended'    => 'call_ended',
    'call_missed'   => 'call_missed',
    'call_declined' => 'call_missed',  // treat declined same as missed for display
];
$msgType = $typeMap[$event] ?? 'call_event';

// Insert system row — message is NULL; call_event + type carry all meaning
// Use the authenticated user (caller) as sender_id so UI can align correctly
$senderId = $userId;
$stmt = $db->prepare(
    "INSERT INTO messages (match_id, sender_id, message, type, call_event, duration, created_at)
     VALUES (?, ?, NULL, ?, ?, ?, NOW())"
);
$stmt->bind_param('iissi', $matchId, $senderId, $msgType, $event, $duration);
$stmt->execute();
$messageId = $db->insert_id;
$stmt->close();

// Update match last-message preview for the chat list
$preview = match($event) {
    'call_started'  => '📹 Video call started',
    'call_ended'    => '📹 Video call ended',
    'call_missed'   => '📵 Missed video call',
    'call_declined' => '📵 Call declined',
    default         => '📹 Video call',
};
$upd = $db->prepare("UPDATE matches SET last_message = ?, last_message_time = NOW() WHERE id = ?");
$upd->bind_param('si', $preview, $matchId);
$upd->execute();
$upd->close();

// ── Send Push Notification for Missed Calls ──────────────────
if ($event === 'call_missed' || $event === 'call_declined') {
    require_once __DIR__ . '/../notifications/send_push.php';
    
    $recipientId = ((int)$match['user1_id'] === $userId)
        ? (int) $match['user2_id']
        : (int) $match['user1_id'];
        
    $senderName = 'Someone';
    $senderPhoto = '';
    $nameStmt   = $db->prepare("
        SELECT u.full_name, (SELECT photo_url FROM user_photos WHERE user_id = u.id AND is_dp = 1 LIMIT 1) as photo
        FROM users u WHERE u.id = ?
    ");
    $nameStmt->bind_param('i', $userId);
    $nameStmt->execute();
    $nameRow    = $nameStmt->get_result()->fetch_assoc();
    $nameStmt->close();
    $senderName  = $nameRow['full_name'] ?? 'Someone';
    $senderPhoto = $nameRow['photo'] ?? '';

    // Type is 'missed_call' so Notification Screen can filter it specifically
    sendPush($db, $recipientId, 'missed_call', "Missed Call 📵", "You have a missed video call from $senderName", [
        'match_id'     => (string)$matchId,
        'sender_id'    => (string)$userId,
        'sender_name'  => $senderName,
        'sender_photo' => $senderPhoto,
    ]);
}

$db->close();

echo json_encode([
    'status'  => 'success',
    'message' => [
        'id'         => $messageId,
        'match_id'   => $matchId,
        'sender_id'  => $userId, // now reflects the caller
        'message'    => null,
        'type'       => $msgType,
        'call_event' => $event,
        'duration'   => $duration,
        'created_at' => date('Y-m-d H:i:s'),
    ],
]);
