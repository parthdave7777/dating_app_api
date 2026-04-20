<?php
require_once __DIR__ . '/../config.php';
$db = getDB();

echo "Starting Database Optimization...\n";

// 1. Check and Add stealth_radius to users
$check = $db->query("SHOW COLUMNS FROM users LIKE 'stealth_radius'");
if ($check->num_rows == 0) {
    echo "Adding stealth_radius column...\n";
    $db->query("ALTER TABLE users ADD COLUMN stealth_radius INT DEFAULT 0");
} else {
    echo "stealth_radius column already exists.\n";
}

// 2. Add show_on_map to users
$check = $db->query("SHOW COLUMNS FROM users LIKE 'show_on_map'");
if ($check->num_rows == 0) {
    echo "Adding show_on_map column...\n";
    $db->query("ALTER TABLE users ADD COLUMN show_on_map TINYINT(1) DEFAULT 1");
}

// 3. Add view_history_count (optional but helpful for unblur logic)
// Already handled by profile_views table

// 4. Ensure Indexes exist for performance
echo "Verifying Indexes...\n";
$db->query("ALTER TABLE matches ADD INDEX IF NOT EXISTS idx_users (user1_id, user2_id)");
$db->query("ALTER TABLE profile_views ADD INDEX IF NOT EXISTS idx_viewer_viewed (viewer_id, viewed_id)");

echo "Optimization Complete!\n";
