<?php
require_once __DIR__ . '/../config.php';
$db = getDB();

// Add show_on_map to users table if it doesn't exist
$db->query("ALTER TABLE users ADD COLUMN show_on_map TINYINT(1) DEFAULT 1 AFTER stealth_radius");

echo "Success: show_on_map column added.";
$db->close();
?>
