<?php
/**
 * notifications/test_push.php
 * Run this in your browser to see why notifications are failing.
 */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/send_push.php';

$db = getDB();
$userId = 1; // Change this to your test user ID

// 1. Check Service Account JSON
$sa = getFcmServiceAccount();
echo "Service Account check: " . ($sa ? "<b style='color:green'>LOADED</b> (" . $sa['project_id'] . ")" : "<b style='color:red'>FAILED</b>") . "<br>";

// 2. Fetch recent active users
echo "<h4>Testing Push for User $userId</h4>";
$res = $db->query("SELECT id, full_name, fcm_token, notif_messages FROM users WHERE id = $userId");
$row = $res->fetch_assoc();

if ($row) {
    echo "Name: " . $row['full_name'] . "<br>";
    echo "FCM Token: " . ($row['fcm_token'] ? "Found" : "<b style='color:red'>MISSING</b>") . "<br>";
    echo "Chat Notifs Enabled: " . ($row['notif_messages'] != 0 ? "<b style='color:green'>YES</b>" : "<b style='color:red'>NO (Suppressed)</b>") . "<br>";
    
    if ($row['fcm_token']) {
        echo "Sending Test Push... ";
        $ok = sendPush($db, $userId, 'message', 'Diagnostic Test', 'Sent at ' . date('H:i:s'));
        echo $ok ? "<b style='color:green'>SUCCESS (FCM Accepted it)</b>" : "<b style='color:red'>FAILED (See error log)</b>";
    }
} else {
    echo "User $userId not found.";
}

$db->close();
?>
