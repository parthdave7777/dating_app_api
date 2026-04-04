<?php
require_once __DIR__ . '/config.php';
$db = getDB();

header('Content-Type: text/plain');

echo "--- DELETING ALL DATA FROM DATABASE ---\n";

// Disable foreign key checks for truncation
$db->query("SET FOREIGN_KEY_CHECKS = 0");

$tables = [
    'blocks',
    'reports',
    'notifications',
    'profile_views',
    'messages',
    'matches',
    'swipes',
    'user_posts',
    'user_photos',
    'otp_codes',
    'users'
];

foreach ($tables as $table) {
    if ($db->query("TRUNCATE TABLE $table")) {
        echo "[SUCCESS] Truncated $table\n";
    } else {
        echo "[ERROR] Truncating $table: " . $db->error . "\n";
    }
}

$db->query("SET FOREIGN_KEY_CHECKS = 1");

echo "\n--- DATABASE IS NOW COMPLETELY EMPTY ---\n";
echo "You can now perform a clean test from the app.\n";
?>
