<?php
// fix_db.php
require_once __DIR__ . '/config.php';
$db = getDB();

echo "<h1>🚀 Fixing Database Schema...</h1>";

// 1. Force add the missing columns if they don't exist
$queries = [
    "ALTER TABLE users ADD COLUMN IF NOT EXISTS state VARCHAR(100) AFTER city",
    "ALTER TABLE users ADD COLUMN IF NOT EXISTS country VARCHAR(100) AFTER state"
];

foreach ($queries as $q) {
    if ($db->query($q)) {
        echo "✅ Column Update Success: $q<br>";
    } else {
        echo "❌ Column Update Error: " . $db->error . "<br>";
    }
}

echo "<h2>🎉 READY! Now refresh your app and the profile will load!</h2>";
