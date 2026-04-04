<?php
// sweeper.php — The One-Click Project Cleaner
require_once __DIR__ . '/config.php';

$filesToDelete = [
    'fix_discovery.php',
    'fix_db.php',
    'fix_db_migration.php',
    'setup_db_settings.php',
    'add_notification_columns.php',
    'add_reply_column.php',
    'debug_db.php',
    'debug_discovery.php',
    'debug_one_two.php',
    'debug_swipes.php',
    'debug_verified.php',
    'deep_debug.php',
    'check_db.php',
    'create_test_accounts.php',
    'seed_data.php',
    'seed_massive.php',
    'reset_and_seed.php',
    'setup_dummy_data.php',
    'truncate_all.php'
];

echo "<h1>🧹 Starting Ultimate Cleanup...</h1>";

foreach ($filesToDelete as $f) {
    if (file_exists(__DIR__ . '/' . $f)) {
        unlink(__DIR__ . '/' . $f);
        echo "✅ Deleted: <strong>$f</strong><br>";
    }
}

// Suicide: The sweeper deletes itself after finishing
echo "<h3>Finalizing cleanup...</h3>";
unlink(__FILE__); 

echo "<h2>🎉 CLEANUP COMPLETE! Your project is now 100% professional.</h2>";
?>
