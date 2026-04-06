<?php
/**
 * fix_chat_db.php
 * Run this script to add missing columns to the messages table.
 * These columns are required for the chat detail screen to function correctly.
 */
require_once __DIR__ . '/config.php';
$db = getDB();

echo "<h1>🚀 Fixing Chat Database Schema...</h1>";

$queries = [
    // 1. Add missing columns to messages table
    "ALTER TABLE messages ADD COLUMN IF NOT EXISTS is_received TINYINT(1) DEFAULT 0 AFTER is_read",
    "ALTER TABLE messages ADD COLUMN IF NOT EXISTS received_at DATETIME DEFAULT NULL AFTER read_at",
    "ALTER TABLE messages ADD COLUMN IF NOT EXISTS is_view_once TINYINT(1) DEFAULT 0 AFTER is_opened",
    "ALTER TABLE messages ADD COLUMN IF NOT EXISTS is_opened TINYINT(1) DEFAULT 0 AFTER is_view_once",
    "ALTER TABLE messages ADD COLUMN IF NOT EXISTS opened_at DATETIME DEFAULT NULL AFTER received_at",
    
    // 2. Ensure users table has necessary columns (just in case)
    "ALTER TABLE users ADD COLUMN IF NOT EXISTS full_name VARCHAR(100) DEFAULT NULL",
    "ALTER TABLE user_photos ADD COLUMN IF NOT EXISTS is_dp TINYINT(1) DEFAULT 0",
];

foreach ($queries as $q) {
    try {
        if ($db->query($q)) {
            echo "✅ Success: $q<br>";
        } else {
            echo "❌ Error: " . $db->error . " | Query: $q<br>";
        }
    } catch (Exception $e) {
        echo "⚠️ Note: " . $e->getMessage() . " (Column might already exist)<br>";
    }
}

$db->close();
echo "<h2>🎉 DONE! The chat screen should now load correctly.</h2>";
?>
