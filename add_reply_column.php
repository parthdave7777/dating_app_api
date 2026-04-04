<?php
// add_reply_column.php
require_once __DIR__ . '/config.php';
$db = getDB();

$db->query("ALTER TABLE messages ADD COLUMN IF NOT EXISTS reply_to_id INT DEFAULT NULL");
$db->query("ALTER TABLE messages ADD FOREIGN KEY (reply_to_id) REFERENCES messages(id) ON DELETE SET NULL");

$db->close();
echo "reply_to_id added successfully\n";
?>
