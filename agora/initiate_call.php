<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

header('Content-Type: application/json');

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../notifications/send_push.php';
require_once __DIR__ . '/Util.php';
require_once __DIR__ . '/AccessToken2.php';
require_once __DIR__ . '/RtcTokenBuilder2.php';

if (!defined('AGORA_APP_ID'))   define('AGORA_APP_ID',   '');
if (!defined('AGORA_APP_CERT')) define('AGORA_APP_CERT', '');
if (!defined('TOKEN_EXPIRY'))   define('TOKEN_EXPIRY',   3600);

$callerId = getAuthUserId();

$body    = json_decode(file_get_contents('php://input'), true) ?? [];
$matchId = (int)($body['match_id'] ?? 0);

error_log("[CALL] initiate_call: callerId=$callerId matchId=$matchId");

if ($matchId <= 0) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'match_id required']);
    exit();
}

$db = getDB();

$stmt = $db->prepare(
    "SELECT id, user1_id, user2_id FROM matches WHERE id = ? AND (user1_id = ? OR user2_id = ?)"
);
$stmt->bind_param('iii', $matchId, $callerId, $callerId);
$stmt->execute();
$match = $stmt->get_result()->fetch_assoc();
$stmt->close();

error_log("[CALL] match found: " . json_encode($match));

if (!$match) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Match not found or access denied']);
    exit();
}

$calleeId    = ($match['user1_id'] == $callerId) ? $match['user2_id'] : $match['user1_id'];
$channelName = 'match_' . $matchId;

error_log("[CALL] calleeId=$calleeId channel=$channelName");

// ── Get caller info ───────────────────────────────────────────
$callerStmt = $db->prepare("SELECT full_name FROM users WHERE id = ?");
$callerStmt->bind_param('i', $callerId);
$callerStmt->execute();
$caller = $callerStmt->get_result()->fetch_assoc();
$callerStmt->close();
$callerName = $caller['full_name'] ?? 'Someone';

// ── Get caller profile photo ──────────────────────────────────
$photoStmt = $db->prepare(
    "SELECT photo_url FROM user_photos WHERE user_id = ? AND is_dp = 1 LIMIT 1"
);
$photoStmt->bind_param('i', $callerId);
$photoStmt->execute();
$photoRow = $photoStmt->get_result()->fetch_assoc();
$photoStmt->close();
$callerPhoto = !empty($photoRow['photo_url']) ? $photoRow['photo_url'] : '';

error_log("[CALL] callerName=$callerName callerPhoto=$callerPhoto");

// ── Generate caller's Agora token ─────────────────────────────
error_log("[CALL] generating token...");
$callerToken = RtcTokenBuilder2::buildTokenWithUid(
    AGORA_APP_ID,
    AGORA_APP_CERT,
    $channelName,
    $callerId,
    RtcTokenBuilder2::ROLE_PUBLISHER,
    TOKEN_EXPIRY,
    TOKEN_EXPIRY
);
error_log("[CALL] token generated OK");

// ── Send FCM push to callee ───────────────────────────────────
error_log("[CALL] sending FCM push...");
sendPush(
    $db,
    $calleeId,
    'incoming_call',
    '📹 Incoming Video Call',
    $callerName . ' is calling you…',
    [
        'match_id'     => (string)$matchId,
        'channel'      => $channelName,
        'caller_id'    => (string)$callerId,
        'caller_name'  => $callerName,
        'caller_photo' => $callerPhoto,
        'app_id'       => AGORA_APP_ID,
        // BUG FIX: Include sender_id so the Flutter app can filter out
        // self-notifications when the caller uses two devices.
        'sender_id'    => (string)$callerId,
    ]
);
error_log("[CALL] FCM done");

// ── Create initial call log entry ────────────────────────────
$logStmt = $db->prepare(
    "INSERT INTO call_logs (match_id, caller_id, callee_id, status) VALUES (?, ?, ?, 'ringing')"
);
$logStmt->bind_param('iii', $matchId, $callerId, $calleeId);
$logStmt->execute();
$callLogId = $db->insert_id;
$logStmt->close();

$db->close();

echo json_encode([
    'status'      => 'success',
    'token'       => $callerToken,
    'uid'         => $callerId,
    'channel'     => $channelName,
    'app_id'      => AGORA_APP_ID,
    'callee_id'   => $calleeId,
    'call_log_id' => $callLogId,   // New: return the ID so the app can track it
    'expires_in'  => TOKEN_EXPIRY,
]);
