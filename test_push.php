<?php
/**
 * test_push.php
 * Diagnostic Tool (Root Version)
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/notifications/send_push.php';

$db = getDB();
$userId = 1; // Testing User 1

echo "<h3>FCM Diagnostic Tool (Root Version)</h3>";

// 1. Check Service Account Status
echo "Checking credentials... ";
$sa = getFcmServiceAccount();
$envJson = getenv('FCM_SERVICE_ACCOUNT_JSON');

if (!empty($envJson)) {
    echo "<b style='color:green'>Found in Environment Variables!</b> ✅<br>";
} elseif ($sa) {
    echo "<b style='color:green'>Found in local File!</b> ✅<br>";
} else {
    echo "<b style='color:red'>NOT FOUND!</b> No JSON file found AND no FCM_SERVICE_ACCOUNT_JSON environment variable detected on Render.<br>";
}

// 2. Try to get Access Token
echo "Requesting Google OAuth2 Token... ";
$token = getFcmAccessToken();
if ($token) {
    echo "<b style='color:green'>Success!</b> (Token length: " . strlen($token) . ")<br>";
} else {
    echo "<b style='color:red'>FAILED.</b> Check your PHP error logs.<br>";
}

// 3. Check Target User Token
echo "Checking User $userId FCM Token... ";
$res = $db->query("SELECT fcm_token FROM users WHERE id = $userId");
$row = $res->fetch_assoc();
if ($row && !empty($row['fcm_token'])) {
    echo "<b style='color:green'>Found!</b> Token starts with: " . substr($row['fcm_token'], 0, 15) . "...<br>";
} else {
    echo "<b style='color:red'>MISSING.</b> User $userId has no FCM token in database.<br>";
}

// 4. Attempt a real Push
if ($token && !empty($row['fcm_token'])) {
    echo "Attempting to send real push to User $userId...<br>";
    sendPush($db, $userId, 'test', 'Test Alert', 'If you see this, push is working!');
    echo "<br><b>Check your PHP error log (error_log) for the result.</b>";
}

$db->close();
?>
