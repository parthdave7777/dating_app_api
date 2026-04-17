<?php
// test_push.php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/notifications/send_push.php';

// Allow passing user_id in URL for easy testing
$userId = (int)($_GET['user_id'] ?? 0);

echo "<h1>📡 Nitro Push Tester</h1>";

if (!$userId) {
    echo "<p style='color:orange;'>Please provide a user_id in the URL. <br> Example: <b>test_push.php?user_id=1</b></p>";
    exit();
}

$db = getDB();
echo "<p>Testing push for User ID: $userId</p>";

// Force clear cache to ensure we get fresh data
clearProfileCache($userId);

echo "<h3>Step 1: Check Database Data</h3>";
$res = $db->query("SELECT fcm_token, notif_matches FROM users WHERE id = $userId");
$row = $res->fetch_assoc();

if (!$row) {
    echo "<p style='color:red;'>❌ ERROR: User $userId not found in database.</p>";
    exit();
}

if (!$row['fcm_token']) {
    echo "<p style='color:red;'>❌ ERROR: No FCM Token for you. Open the app and grant permissions!</p>";
} else {
    echo "<p style='color:green;'>✅ FCM Token found: " . substr($row['fcm_token'], 0, 20) . "...</p>";
}

echo "<h3>Step 2: Try Send Notification</h3>";
try {
    // We send a push to the specified ID
    sendPush($db, $userId, 'match', "Nitro Test! 🚀", "If you see this, Redis notifications are working.");
    echo "<p style='color:green;'>✅ sendPush() executed. Check your phone!</p>";
} catch (Exception $e) {
    echo "<p style='color:red;'>❌ EXCEPTION: " . $e->getMessage() . "</p>";
}

echo "<h3>Step 3: Redis Check</h3>";
$redis = getRedis();
if ($redis) {
    $token = $redis->get('fcm_access_token');
    if ($token) {
        echo "<p style='color:green;'>✅ Redis has a Google Access Token stored!</p>";
    } else {
        echo "<p style='color:orange;'>⚠️ Redis does NOT have a Google Token yet (It will get one on the first attempt).</p>";
    }
} else {
    echo "<p style='color:red;'>❌ Redis Connection Failed.</p>";
}
echo "<hr><p>Check your Railway 'Application Logs' for detailed [FCM] debug messages.</p>";
