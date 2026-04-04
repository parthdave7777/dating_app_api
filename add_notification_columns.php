<?php
// add_notification_columns.php
require_once __DIR__ . '/config.php';
$db = getDB();

$cols = [
    'notif_matches' => 'TINYINT(1) DEFAULT 1',
    'notif_messages' => 'TINYINT(1) DEFAULT 1',
    'notif_likes' => 'TINYINT(1) DEFAULT 1',
    'notif_who_swiped' => 'TINYINT(1) DEFAULT 1'
];

foreach ($cols as $col => $type) {
    if (!$db->query("ALTER TABLE users ADD COLUMN IF NOT EXISTS $col $type")) {
        echo "Error adding $col: " . $db->error . "\n";
    } else {
        echo "Column $col verified/added.\n";
    }
}

$db->close();
echo "Schema update completed.\n";
?>
