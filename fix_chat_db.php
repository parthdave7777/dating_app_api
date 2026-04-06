<?php
/**
 * fix_chat_db.php
 * Updated: Manual check for existing columns for compatibility.
 */
require_once __DIR__ . '/config.php';
$db = getDB();

echo "<h1>🚀 Fixing Chat Database Schema...</h1>";

function columnExists(mysqli $db, string $table, string $column): bool {
    $res = $db->query("SHOW COLUMNS FROM `$table` LIKE '$column'");
    return $res && $res->num_rows > 0;
}

$updates = [
    'messages' => [
        'is_received'  => "ALTER TABLE messages ADD COLUMN is_received TINYINT(1) DEFAULT 0 AFTER is_read",
        'received_at'  => "ALTER TABLE messages ADD COLUMN received_at DATETIME DEFAULT NULL AFTER read_at",
        'is_view_once' => "ALTER TABLE messages ADD COLUMN is_view_once TINYINT(1) DEFAULT 0 AFTER is_saved",
        'is_opened'    => "ALTER TABLE messages ADD COLUMN is_opened TINYINT(1) DEFAULT 0 AFTER is_view_once",
        'opened_at'    => "ALTER TABLE messages ADD COLUMN opened_at DATETIME DEFAULT NULL AFTER received_at",
        'deleted_at'   => "ALTER TABLE messages ADD COLUMN deleted_at DATETIME DEFAULT NULL AFTER opened_at",
    ],
    'users' => [
        'full_name' => "ALTER TABLE users ADD COLUMN full_name VARCHAR(100) DEFAULT NULL",
    ],
    'user_photos' => [
        'is_dp' => "ALTER TABLE user_photos ADD COLUMN is_dp TINYINT(1) DEFAULT 0",
    ]
];

foreach ($updates as $table => $cols) {
    foreach ($cols as $col => $query) {
        if (!columnExists($db, $table, $col)) {
            if ($db->query($query)) {
                echo "✅ Added: $table.$col<br>";
            } else {
                echo "❌ Error adding $col: " . $db->error . "<br>";
            }
        } else {
            echo "ℹ️ Exists: $table.$col<br>";
        }
    }
}

$db->close();
echo "<h2>🎉 DONE! The chat screen should now load correctly.</h2>";
