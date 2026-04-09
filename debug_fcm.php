<?php
require_once 'c:/xampp/htdocs/dating_api/config.php';
$db = getDB();

echo "--- FCM TOKEN STATUS ---\n";
$res = $db->query("SELECT id, full_name, fcm_token FROM users WHERE fcm_token IS NOT NULL");
if ($res->num_rows == 0) {
    echo "No users have a registered FCM token in the database.\n";
    echo "Make sure the app is running on a real device and you granted notification permissions.\n";
} else {
    while($row = $res->fetch_assoc()) {
        echo "User #" . $row['id'] . " (" . $row['full_name'] . "): " . substr($row['fcm_token'], 0, 20) . "...\n";
    }
}
$db->close();
?>
