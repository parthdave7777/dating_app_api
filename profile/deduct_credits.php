<?php
// profile/deduct_credits.php
require_once __DIR__ . '/../config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    die(json_encode(['status' => 'error', 'message' => 'Method not allowed']));
}

$userId = getAuthUserId();
$body   = json_decode(file_get_contents('php://input'), true);
$action = trim($body['action'] ?? '');

$db = getDB();
$cost = 0;
$reason = "App action";

switch ($action) {
    case 'rewind':
        $cost = CREDIT_COST_REWIND;
        $reason = "Rewind swipe";
        break;

    case 'view_secret_profile':
        $cost = CREDIT_COST_VIEW_SECRET;
        $targetId = (int) ($body['target_id'] ?? 0);
        if (!$targetId) die(json_encode(['status' => 'error', 'message' => 'target_id required']));
        
        // Already unlocked check
        $check = $db->prepare("SELECT id FROM unlocked_profiles WHERE user_id = ? AND target_id = ?");
        $check->bind_param('ii', $userId, $targetId);
        $check->execute();
        if ($check->get_result()->num_rows > 0) {
            die(json_encode(['status' => 'success', 'message' => 'Already unlocked', 'new_balance' => getUserCredits($db, $userId)]));
        }
        $check->close();

        if (deductCredits($db, $userId, $cost, "Unlocked profile ID: $targetId")) {
             $ins = $db->prepare("INSERT IGNORE INTO unlocked_profiles (user_id, target_id, type) VALUES (?, ?, 'view')");
             $ins->bind_param('ii', $userId, $targetId);
             $ins->execute();
             $ins->close();
             die(json_encode(['status' => 'success', 'new_balance' => getUserCredits($db, $userId)]));
        } else {
            die(json_encode(['status' => 'error', 'message' => 'Insufficient credits', 'error_code' => 'INSUFFICIENT_CREDITS']));
        }
        break;

    case 'video_call_min':
        $cost = CREDIT_COST_CALL_MIN;
        $reason = "Video call (1 min)";
        break;

    default:
        die(json_encode(['status' => 'error', 'message' => 'Invalid action']));
}

if (deductCredits($db, $userId, $cost, $reason)) {
    echo json_encode(['status' => 'success', 'new_balance' => getUserCredits($db, $userId)]);
} else {
    echo json_encode(['status' => 'error', 'message' => 'Insufficient credits', 'error_code' => 'INSUFFICIENT_CREDITS']);
}

// NITRO CACHE CLEANUP
clearProfileCache($userId);

$db->close();
