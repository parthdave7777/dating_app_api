<?php
// test_push.php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/notifications/send_push.php';

$userId = getAuthUserId();
if (!$userId) die("Unauthorized. Please login in the app first.");

$db = getDB();

echo "<h1>📡 Nitro Push Tester</h1>";
echo "<p>Testing push for User ID: $userId</p>";

// Force clear cache to ensure we get fresh data
clearProfileCache($userId);

echo "<h3>Step 1: Check Database</h3>";
$res = $db->query("SELECT fcm_token, notif_matches FROM users WHERE id = $userId");
$row = $res->fetch_assoc();
if (!$row['fcm_token']) {
    echo "<p style='color:red;'>❌ ERROR: No FCM Token found in DB for you. Open the app and grant permissions!</p>";
} else {
    echo "<p style='color:green;'>✅ FCM Token found: " . substr($row['fcm_token'], 0, 20) . "...</p>";
}

echo "<h3>Step 2: Try Send</h3>";
try {
    // We send a push to OURSELVES to test
    sendPush($db, $userId, 'match', "Nitro Test! 🚀", "If you see this, Redis notifications are working.");
    echo "<p style='color:green;'>✅ sendPush() executed. Check your phone!</p>";
} catch (Exception $e) {
    echo "<p style='color:red;'>❌ EXCEPTION: " . $e->getMessage() . "</p>";
}

echo "<h3>Step 3: Check Logs</h3>";
echo "<p>Please check your Railway 'Application Logs' (Logs tab in dashboard) to see any [FCM] errors.</p>";
