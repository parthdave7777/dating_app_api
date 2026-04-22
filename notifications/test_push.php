<?php
/**
 * notifications/test_push.php
 * Run this in your browser to see why notifications are failing.
 */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/send_push.php';

$db = getDB();
$userId = 1; // Change this to your test user ID

echo "<h3>FCM Diagnostic Tool</h3>";

// 1. Check Service Account File
echo "Checking service account JSON... ";
if (file_exists(FCM_SERVICE_ACCOUNT_PATH)) {
    echo "<b style='color:green'>Found!</b><br>";
} else {
    echo "<b style='color:red'>NOT FOUND!</b> Path looked at: " . FCM_SERVICE_ACCOUNT_PATH . "<br>";
}

// 2. Try to get Access Token
echo "Requesting Google OAuth2 Token... ";
$token = getFcmAccessToken();
if ($token) {
    echo "<b style='color:green'>Success!</b> (Token length: " . strlen($token) . ")<br>";
} else {
    echo "<b style='color:red'>FAILED.</b> Check your PHP error logs or ensure the JSON file is valid.<br>";
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
    echo "<br><b>Check your PHP error log (error_log) for the result.</b> If you didn't receive it, the issue might be on the Flutter side handler.";
}

$db->close();
?>
