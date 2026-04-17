<?php
// test_push.php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/notifications/send_push.php';

$userId = (int)($_GET['user_id'] ?? 0);
$testType = $_GET['test'] ?? 'match';

echo "<h1>📡 Nitro Push Tester</h1>";

if (!$userId) {
    echo "<p style='color:orange;'>Please provide a user_id. <br> Example: <b>test_push.php?user_id=1&test=call</b></p>";
    exit();
}

$db = getDB();
echo "<p>Testing <b>$testType</b> push for User ID: $userId</p>";

clearProfileCache($userId);

echo "<h3>Step 1: Check Database Data</h3>";
$res = $db->query("SELECT fcm_token FROM users WHERE id = $userId");
$row = $res->fetch_assoc();
if (!$row['fcm_token']) {
    echo "<p style='color:red;'>❌ ERROR: No FCM Token in DB.</p>";
} else {
    echo "<p style='color:green;'>✅ FCM Token found.</p>";
}

echo "<h3>Step 2: Try Send Notification</h3>";
try {
    if ($testType === 'call') {
        $pushRes = sendPush($db, $userId, 'incoming_call', '📹 Incoming Call', 'Nitro Test Call...', [
            'match_id'     => '1',
            'channel'      => 'test_channel',
            'caller_id'    => '99',
            'caller_name'  => 'Nitro Admin',
            'caller_photo' => '',
            'app_id'       => AGORA_APP_ID
        ]);
    } else {
        $pushRes = sendPush($db, $userId, 'match', "Nitro Test! 🚀", "General match push test.");
    }
    
    if ($pushRes) {
        echo "<p style='color:green;'>✅ sendPush() returned TRUE. Check your phone!</p>";
    } else {
        echo "<p style='color:red;'>❌ sendPush() returned FALSE. Check Railway Logs!</p>";
    }
} catch (Exception $e) {
    echo "<p style='color:red;'>❌ EXCEPTION: " . $e->getMessage() . "</p>";
}

echo "<h3>Step 3: Redis Check</h3>";
$redis = getRedis();
$token = $redis ? $redis->get('fcm_access_token') : null;
echo $token ? "<p style='color:green;'>✅ Google Token in Cache.</p>" : "<p style='color:red;'>❌ No Google Token in Cache.</p>";
