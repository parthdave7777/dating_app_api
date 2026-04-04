<?php
// fix_discovery.php
require_once __DIR__ . '/config.php';
$db = getDB();

echo "<h1>🚀 Force-Resetting Discovery Settings...</h1>";

// 1. Change the table default to 0 (OFF)
$db->query("ALTER TABLE users MODIFY COLUMN global_discovery TINYINT(1) DEFAULT 0");

// 2. Set every existing user to 0 (OFF)
$db->query("UPDATE users SET global_discovery = 0");

echo "<h2>🎉 SUCCESS! Everyone is now set to Local Discovery Only!</h2>";
echo "<h3>Visit this link after pushing to Render to make it official.</h3>";
?>
