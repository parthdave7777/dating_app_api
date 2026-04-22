<?php
/**
 * credits/verify.php
 * REPLACES credits/purchase.php
 * 
 * SERVER-SIDE GOOGLE PLAY VERIFICATION
 * Required Env: 
 *   GOOGLE_PLAY_PACKAGE_NAME
 *   GOOGLE_SERVICE_ACCOUNT_JSON
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/google_play_auth.php';

// 1. Authenticate User
$userId = getAuthUserId();
$db = getDB();

// 2. Parse Payload
$body = json_decode(file_get_contents('php://input'), true);
$purchaseToken = $body['purchase_token'] ?? '';
$productId     = $body['product_id'] ?? '';

if (empty($purchaseToken) || empty($productId)) {
    http_response_code(400);
    die(json_encode(['status' => 'error', 'message' => 'Missing purchaseToken or productId']));
}

// 3. Server-Side Product Map (Rule 3)
$productMap = [
    'credits_100'  => 100,
    'credits_500'  => 500,
    'credits_1200' => 1200,
    'credits_3000' => 3000,
];

if (!isset($productMap[$productId])) {
    http_response_code(400);
    die(json_encode(['status' => 'error', 'message' => 'Invalid productId']));
}

$creditsToGrant = $productMap[$productId];

// 4. Idempotency Check (Redis first)
$redis = getRedis();
$tokenHash = hash('sha256', $purchaseToken);
if ($redis && $redis->get("used_token:$tokenHash")) {
    $credits = getUserCredits($db, $userId);
    echo json_encode(['status' => 'success', 'already_processed' => true, 'new_balance' => $credits]);
    exit();
}

// 5. MySQL Idempotency Check
$checkStmt = $db->prepare("SELECT credits_granted FROM purchase_tokens WHERE purchase_token = ?");
$checkStmt->bind_param('s', $purchaseToken);
$checkStmt->execute();
if ($checkStmt->get_result()->num_rows > 0) {
    if ($redis) $redis->setex("used_token:$tokenHash", 7776000, 1);
    $credits = getUserCredits($db, $userId);
    echo json_encode(['status' => 'success', 'already_processed' => true, 'new_balance' => $credits]);
    exit();
}
$checkStmt->close();

// 6. Google Play Developer API Verification
try {
    $accessToken = getGooglePlayAccessToken();
    $packageName = getenv('GOOGLE_PLAY_PACKAGE_NAME') ?: "com.datingapp.package"; // Replace with your real ID or env
    
    $url = "https://androidpublisher.googleapis.com/androidpublisher/v3/applications/$packageName/purchases/products/$productId/tokens/$purchaseToken";

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_HTTPHEADER     => ["Authorization: Bearer $accessToken"],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 10
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $googleData = json_decode($response, true);
    curl_close($ch);

    if ($httpCode !== 200 || !isset($googleData['purchaseState']) || $googleData['purchaseState'] != 1) {
        // Log Error
        $errStmt = $db->prepare("INSERT INTO billing_errors (user_id, purchase_token, error_stage, error_msg) VALUES (?, ?, 'verify', ?)");
        $errMsg = ($httpCode !== 200) ? "HTTP $httpCode: $response" : "Invalid purchaseState: " . $googleData['purchaseState'];
        $errStmt->bind_param('iss', $userId, $purchaseToken, $errMsg);
        $errStmt->execute();
        
        http_response_code(400);
        die(json_encode(['status' => 'error', 'message' => 'Verification failed', 'details' => $googleData]));
    }

    $orderId = $googleData['orderId'] ?? '';

    // 7. Atomic DB Transaction
    $db->begin_transaction();
    try {
        // Log purchase_token
        $insToken = $db->prepare("INSERT INTO purchase_tokens (user_id, purchase_token, product_id, credits_granted, order_id, purchase_state, raw_response) VALUES (?, ?, ?, ?, ?, 1, ?)");
        $rawResp = json_encode($googleData);
        $insToken->bind_param('ississ', $userId, $purchaseToken, $productId, $creditsToGrant, $orderId, $rawResp);
        if (!$insToken->execute()) {
            throw new Exception("Token insertion failed (likely duplicate).");
        }
        $insToken->close();

        // Update Premium Credits
        $updUser = $db->prepare("UPDATE users SET premium_credits = premium_credits + ? WHERE id = ?");
        $updUser->bind_param('ii', $creditsToGrant, $userId);
        $updUser->execute();
        $updUser->close();

        // Credit Log
        $logReason = "Play purchase: $productId";
        $insLog = $db->prepare("INSERT INTO credit_logs (user_id, amount, reason, is_purchase) VALUES (?, ?, ?, 1)");
        $insLog->bind_param('iis', $userId, $creditsToGrant, $logReason);
        $insLog->execute();
        $insLog->close();

        $db->commit();
    } catch (Exception $e) {
        $db->rollback();
        // If it was a duplicate key error, handle it as already processed
        if ($db->errno === 1062) {
             $credits = getUserCredits($db, $userId);
             echo json_encode(['status' => 'success', 'already_processed' => true, 'new_balance' => $credits]);
             exit();
        }
        throw $e;
    }

    // 8. Update Redis for 90 days
    if ($redis) {
        $redis->setex("used_token:$tokenHash", 7776000, 1);
    }

    // 9. Acknowledge Purchase (Rule 6)
    // We do this after crediting so the user is happy even if Google API is slow/down
    dispatchAsync([
        'action_type'    => 'acknowledge_purchase',
        'purchase_token' => $purchaseToken,
        'product_id'     => $productId,
        'package_name'   => $packageName,
        'user_id'        => $userId
    ]);

    $finalBalance = getUserCredits($db, $userId);
    echo json_encode([
        'status'        => 'success',
        'new_balance'   => $finalBalance,
        'credits_added' => $creditsToGrant,
        'product_id'    => $productId,
        'order_id'      => $orderId
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
