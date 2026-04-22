<?php
/**
 * credits/acknowledge.php
 * Standalone retry script and helper for acknowledging Google Play purchases.
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/google_play_auth.php';

/**
 * Performs the actual acknowledgment call to Google
 */
function acknowledgeGooglePurchase(mysqli $db, string $packageName, string $productId, string $purchaseToken, int $userId): bool {
    try {
        $accessToken = getGooglePlayAccessToken();
        $url = "https://androidpublisher.googleapis.com/androidpublisher/v3/applications/$packageName/purchases/products/$productId/tokens/$purchaseToken:acknowledge";

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_HTTPHEADER     => ["Authorization: Bearer $accessToken", "Content-Type: application/json"],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 10
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode === 200 || $httpCode === 204) {
            $upd = $db->prepare("UPDATE purchase_tokens SET acknowledged = 1 WHERE purchase_token = ?");
            $upd->bind_param('s', $purchaseToken);
            $upd->execute();
            $upd->close();
            return true;
        } else {
            // Log for retry
            $errStmt = $db->prepare("INSERT INTO billing_errors (user_id, purchase_token, error_stage, error_msg) VALUES (?, ?, 'acknowledge', ?)");
            $errMsg = "HTTP $httpCode: $response";
            $errStmt->bind_param('iss', $userId, $purchaseToken, $errMsg);
            $errStmt->execute();
            return false;
        }
    } catch (Exception $e) {
        return false;
    }
}

// IF RUN AS CRON
if (basename(__FILE__) == basename($_SERVER['PHP_SELF'])) {
    $db = getDB();
    $packageName = getenv('GOOGLE_PLAY_PACKAGE_NAME') ?: "com.datingapp.package";

    // Retry unacknowledged tokens
    $res = $db->query("SELECT user_id, purchase_token, product_id FROM purchase_tokens WHERE acknowledged = 0 AND created_at > DATE_SUB(NOW(), INTERVAL 3 DAY)");
    $count = 0;
    while ($row = $res->fetch_assoc()) {
        if (acknowledgeGooglePurchase($db, $packageName, $row['product_id'], $row['purchase_token'], $row['user_id'])) {
            $count++;
        }
    }
    echo "Acknowledged $count purchases.";
}
