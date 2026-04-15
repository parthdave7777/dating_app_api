<?php
require_once __DIR__ . '/config.php';
$db = getDB();
$db->query("UPDATE users SET notif_activity = 1");
$db->query("ALTER TABLE users MODIFY notif_activity tinyint(1) DEFAULT 1");
echo json_encode(['status' => 'success', 'message' => 'Notification defaults updated to ON']);
$db->close();
?>
