<?php
require_once __DIR__ . '/../config.php';
$db = getDB();

echo "--- RAILWAY DB STATUS ---\n";

// Check if column exists
$res = $db->query("SHOW COLUMNS FROM users LIKE 'stealth_radius'");
if ($res->num_rows > 0) {
    $col = $res->fetch_assoc();
    echo "SUCCESS: Column 'stealth_radius' exists. Type: " . $col['Type'] . " Default: " . $col['Default'] . "\n";
} else {
    echo "ERROR: Column 'stealth_radius' IS MISSING. You must run the ALTER TABLE command in Railway.\n";
}

// Check current user's value
$userId = 1; // Testing with ID 1 or a real one if you know it
$userRes = $db->query("SELECT id, stealth_radius FROM users LIMIT 1");
if ($userRes && $u = $userRes->fetch_assoc()) {
    echo "TEST: User ID " . $u['id'] . " has stealth_radius = " . $u['stealth_radius'] . "\n";
}

$db->close();
?>
