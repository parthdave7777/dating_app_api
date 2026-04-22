<?php
/**
 * agora/end_call.php
 * Called by EITHER party when the call actually ends (hang-up / timeout / missed).
 * POST body: { match_id, call_log_id, duration_sec, reason: "ended"|"missed"|"cancelled" }
 */
header('Content-Type: application/json');
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../notifications/send_push.php';
require_once __DIR__ . '/Util.php';

$userId      = getAuthUserId();
$body        = json_decode(file_get_contents('php://input'), true) ?? [];
$matchId     = (int)($body['match_id']     ?? 0);
$callLogId   = (int)($body['call_log_id']  ?? 0);
$durationSec = (int)($body['duration_sec'] ?? 0);
$reason      = trim($body['reason'] ?? 'ended');

if ($matchId <= 0) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'match_id required']);
    exit();
}

$db = getDB();

// ── Find the call_log ─────────────────────────────────────────
$log = null;
if ($callLogId > 0) {
    $s = $db->prepare(
        "SELECT * FROM call_logs WHERE id = ? AND (caller_id = ? OR callee_id = ?)"
    );
    $s->bind_param('iii', $callLogId, $userId, $userId);
    $s->execute();
    $log = $s->get_result()->fetch_assoc();
    $s->close();
}

// Fallback: find the most recent ringing/accepted log for this match
if (!$log) {
    $s = $db->prepare(
        "SELECT * FROM call_logs
         WHERE match_id = ? AND (caller_id = ? OR callee_id = ?)
           AND status IN ('ringing','accepted')
         ORDER BY id DESC LIMIT 1"
    );
    $s->bind_param('iii', $matchId, $userId, $userId);
    $s->execute();
    $log = $s->get_result()->fetch_assoc();
    $s->close();
}

if (!$log) {
    // No log found – nothing to do
    echo json_encode(['status' => 'ok', 'note' => 'no active call_log found']);
    $db->close();
    exit();
}

$callLogId   = (int)$log['id'];
$callerId    = (int)$log['caller_id'];
$calleeId    = (int)$log['callee_id'];

// Determine final status
$finalStatus = 'ended';
if ($reason === 'missed' || $reason === 'cancelled' || $log['status'] === 'ringing') {
    $finalStatus = 'missed';
}

// ── Update call_log ───────────────────────────────────────────
$s = $db->prepare(
    "UPDATE call_logs SET status=?, ended_at=NOW(), duration_sec=? WHERE id=?"
);
$s->bind_param('sii', $finalStatus, $durationSec, $callLogId);
$s->execute();
$s->close();

// ── Insert call-event message in chat ─────────────────────────
$msgType = ($finalStatus === 'missed') ? 'call_missed' : 'call_ended';
$event   = ($finalStatus === 'missed') ? 'call_missed' : 'call_ended';

// Check if we already inserted a message for this log to avoid duplicates
$existing = null;
if ($callLogId > 0) {
    $s = $db->prepare("SELECT message_id FROM call_logs WHERE id=?");
    $s->bind_param('i', $callLogId);
    $s->execute();
    $existing = $s->get_result()->fetch_assoc();
    $s->close();
}

$msgId = 0;
if (empty($existing['message_id'])) {
    // message is NULL — the type and call_event columns carry all semantic meaning
    $s = $db->prepare(
        "INSERT INTO messages (match_id, sender_id, message, type, call_event, duration)
         VALUES (?, ?, NULL, ?, ?, ?)"
    );
    $s->bind_param('iissi', $matchId, $callerId, $msgType, $event, $durationSec);
    $s->execute();
    $msgId = $db->insert_id;
    $s->close();

    // Link message back to call_log
    $s = $db->prepare("UPDATE call_logs SET message_id=? WHERE id=?");
    $s->bind_param('ii', $msgId, $callLogId);
    $s->execute();
    $s->close();
}

// ── Push notification to the other party ─────────────────────
$otherId = ($userId === $callerId) ? $calleeId : $callerId;

if ($finalStatus === 'missed') {
    // Get caller info (name + photo) so the notification screen can show them
    $callerStmt = $db->prepare("
        SELECT u.full_name,
               (SELECT photo_url FROM user_photos WHERE user_id = u.id AND is_dp = 1 LIMIT 1) AS photo
        FROM users u WHERE u.id = ?
    ");
    $callerStmt->bind_param('i', $callerId);
    $callerStmt->execute();
    $callerRow = $callerStmt->get_result()->fetch_assoc() ?? [];
    $callerStmt->close();

    $callerName  = $callerRow['full_name'] ?? 'Someone';
    $callerPhoto = $callerRow['photo']     ?? '';

    // Use type 'missed_call' so send_push.php persists it to the notifications
    // table and notification_screen.dart shows it with photo + name
    sendPush($db, $calleeId, 'missed_call', '📵 Missed Video Call', 
        "You missed a video call from $callerName",
        [
            'match_id'     => (string)$matchId,
            'sender_id'    => (string)$callerId,
            'caller_id'    => (string)$callerId,
            'caller_name'  => $callerName,
            'caller_photo' => $callerPhoto,
        ]
    );
}


$db->close();

echo json_encode([
    'status'     => 'success',
    'final'      => $finalStatus,
    'duration'   => $durationSec,
    'message_id' => $msgId,
]);

function _fmtDur(int $sec): string {
    if ($sec < 60) return "{$sec}s";
    $m = intdiv($sec, 60); $s = $sec % 60;
    return $s > 0 ? "{$m}m {$s}s" : "{$m}m";
}
