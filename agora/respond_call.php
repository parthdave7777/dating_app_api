<?php
/**
 * agora/respond_call.php
 *
 * Called by the CALLEE when they accept or decline an incoming call.
 * - accept  → generates a token for the callee and returns it
 * - decline → sends an FCM "call_declined" push to the caller
 *
 * POST /agora/respond_call.php
 * Headers: Authorization: Bearer <jwt>
 * Body:    { "match_id": 123, "action": "accept" | "decline" }
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../notifications/send_push.php';
require_once __DIR__ . '/Util.php';
require_once __DIR__ . '/AccessToken2.php';
require_once __DIR__ . '/RtcTokenBuilder2.php';

if (!defined('TOKEN_EXPIRY'))   define('TOKEN_EXPIRY',   3600);

// ── Auth ─────────────────────────────────────────────────────
$calleeId = getAuthUserId();

// ── Input ─────────────────────────────────────────────────────
$body    = json_decode(file_get_contents('php://input'), true) ?? [];
$matchId = (int)($body['match_id'] ?? 0);
$action  = trim($body['action'] ?? '');

if ($matchId <= 0 || !in_array($action, ['accept', 'decline'])) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'match_id and action (accept|decline) required']);
    exit();
}

$db = getDB();

// ── Verify match ──────────────────────────────────────────────
$stmt = $db->prepare(
    "SELECT id, user1_id, user2_id FROM matches WHERE id = ? AND (user1_id = ? OR user2_id = ?)"
);
$stmt->bind_param('iii', $matchId, $calleeId, $calleeId);
$stmt->execute();
$match = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$match) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Match not found']);
    exit();
}

$callerId    = ($match['user1_id'] == $calleeId) ? $match['user2_id'] : $match['user1_id'];
$channelName = 'match_' . $matchId;

// ── Handle decline ────────────────────────────────────────────
if ($action === 'decline') {
    $calleeStmt = $db->prepare("SELECT full_name FROM users WHERE id = ?");
    $calleeStmt->bind_param('i', $calleeId);
    $calleeStmt->execute();
    $callee = $calleeStmt->get_result()->fetch_assoc();
    $calleeStmt->close();

    $calleeName = $callee['full_name'] ?? 'User';

    sendPush(
        $db,
        $callerId,
        'call_declined',
        'Call Declined',
        $calleeName . ' declined your call.',
        [
            'match_id' => (string)$matchId,
            'channel'  => $channelName,
        ]
    );

    echo json_encode(['status' => 'success', 'action' => 'declined']);
    exit();
}

// ── Handle accept → generate token for callee ─────────────────
$calleeToken = RtcTokenBuilder2::buildTokenWithUid(
    AGORA_APP_ID,
    AGORA_APP_CERT,
    $channelName,
    $calleeId,
    RtcTokenBuilder2::ROLE_PUBLISHER,
    TOKEN_EXPIRY,
    TOKEN_EXPIRY
);

// ── Charge Initiator for the first minute ────────────────────
deductCredits($db, $callerId, CREDIT_COST_CALL_MIN, "Video Call Initial Minute: Match $matchId");

sendPush(
    $db,
    $callerId,
    'call_accepted',
    'Call Accepted',
    'Connecting your call…',
    [
        'match_id' => (string)$matchId,
        'channel'  => $channelName,
    ]
);

echo json_encode([
    'status'     => 'success',
    'action'     => 'accepted',
    'token'      => $calleeToken,
    'uid'        => $calleeId,
    'channel'    => $channelName,
    'app_id'     => AGORA_APP_ID,
    'expires_in' => TOKEN_EXPIRY,
]);
