<?php
// fix_credits_v3.php
require_once __DIR__ . '/config.php';
$db = getDB();

echo "Starting premium credits update...\n";

// 1. Add premium_credits column to users table
$check = $db->query("SHOW COLUMNS FROM users LIKE 'premium_credits'");
if ($check->num_rows === 0) {
    echo "Adding column premium_credits...\n";
    $db->query("ALTER TABLE users ADD COLUMN premium_credits INT DEFAULT 0");
}

// 2. Add last_credit_refresh if missing
$check = $db->query("SHOW COLUMNS FROM users LIKE 'last_credit_refresh'");
if ($check->num_rows === 0) {
    echo "Adding column last_credit_refresh...\n";
    $db->query("ALTER TABLE users ADD COLUMN last_credit_refresh TIMESTAMP DEFAULT CURRENT_TIMESTAMP");
}

echo "Database fix complete!";
$db->close();
?>
