<?php
/**
 * credits/reward_ad.php
 * Endpoint to grant credits after a user watches a Rewarded Ad.
 */
require_once __DIR__ . '/../config.php';

$userId = getAuthUserId();
$db = getDB();

// 1. Basic Security: Check for a "client_secret" from the app
// In a real production app, you'd use AdMob "Server-Side Verification" (SSV)
$body = json_decode(file_get_contents('php://input'), true);
$rewardSecret = $body['reward_secret'] ?? '';

// This should match what we put in the Flutter code
if ($rewardSecret !== 'DATE_ADS_2026_SECURE') {
    http_response_code(403);
    die(json_encode(['status' => 'error', 'message' => 'Unauthorized request source']));
}

// 2. Settings: How many credits per ad?
$creditsPerAd = 20; 
$dailyLimit = 5;

// 3. Rate Limiting: Max 5 ads per day
$today = date('Y-m-d');
$limitCheck = $db->prepare("SELECT COUNT(*) as count FROM credit_logs WHERE user_id = ? AND reason = 'Rewarded Ad' AND DATE(created_at) = ?");
$limitCheck->bind_param('is', $userId, $today);
$limitCheck->execute();
$currentCount = $limitCheck->get_result()->fetch_assoc()['count'];

if ($currentCount >= $dailyLimit) {
    http_response_code(429);
    die(json_encode(['status' => 'error', 'message' => 'Daily limit reached. Come back tomorrow!']));
}

// 4. Grant Credits
$db->begin_transaction();
try {
    // Update user balance
    $update = $db->prepare("UPDATE users SET premium_credits = premium_credits + ? WHERE id = ?");
    $update->bind_param('ii', $creditsPerAd, $userId);
    $update->execute();

    // Log the transaction
    $reason = 'Rewarded Ad';
    $log = $db->prepare("INSERT INTO credit_logs (user_id, amount, reason, is_purchase) VALUES (?, ?, ?, 0)");
    $log->bind_param('iis', $userId, $creditsPerAd, $reason);
    $log->execute();

    $db->commit();
    
    // Get new balance (Total of standard + premium)
    $newBalance = getUserCredits($db, $userId);

    // NITRO CACHE CLEANUP
    clearProfileCache($userId);

    echo json_encode([
        'status' => 'success',
        'message' => "Rewarded $creditsPerAd credits!",
        'new_balance' => $newBalance,
        'remaining_ads' => $dailyLimit - ($currentCount + 1)
    ]);

} catch (Exception $e) {
    $db->rollback();
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Database error']);
}
