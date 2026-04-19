<?php
require_once __DIR__ . '/../config.php';
$db = getDB();

// Add stealth_radius to users table if it doesn't exist
$db->query("ALTER TABLE users ADD COLUMN stealth_radius INT DEFAULT 0 AFTER global_discovery");

echo "Success: stealth_radius column added or already exists.";
$db->close();
?>
