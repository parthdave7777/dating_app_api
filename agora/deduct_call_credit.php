<?php
/**
 * agora/deduct_call_credit.php
 * Called by the Flutter app every 60 seconds during an active call.
 */
header('Content-Type: application/json');
require_once __DIR__ . '/../config.php';

$userId  = getAuthUserId();
$body    = json_decode(file_get_contents('php://input'), true) ?? [];
$matchId = (int)($body['match_id'] ?? 0);

if ($matchId <= 0) {
    echo json_encode(['status' => 'error', 'message' => 'match_id required']);
    exit();
}

// Only charge the CALLER. Find the active call log.
$stmt = $db->prepare("SELECT caller_id FROM call_logs WHERE match_id = ? AND status = 'accepted' ORDER BY id DESC LIMIT 1");
$stmt->bind_param('i', $matchId);
$stmt->execute();
$log = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$log) {
    echo json_encode(['status' => 'error', 'message' => 'No active call session found']);
    exit();
}

$callerId = (int)$log['caller_id'];

// If the person calling this is NOT the caller, we don't deduct but return success (receiver talks for free)
if ($userId !== $callerId) {
    echo json_encode(['status' => 'success', 'note' => 'Receiver is free']);
    exit();
}

// Deduct credits for the caller for the next minute
if (deductCredits($db, $callerId, CREDIT_COST_CALL_MIN, "Video Call Minute Pulse: Match $matchId")) {
    echo json_encode([
        'status' => 'success',
        'new_balance' => getUserCredits($db, $userId)
    ]);
} else {
    echo json_encode([
        'status' => 'error',
        'insufficient_credits' => true,
        'message' => 'Insufficient credits to continue the call'
    ]);
}

$db->close();
