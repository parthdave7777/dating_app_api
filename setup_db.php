<?php
// setup_db.php
// Visit this URL once (e.g. https://your-app.onrender.com/setup_db.php) to create all tables.
require_once __DIR__ . '/config.php';

echo "<h1>🚀 Database Setup Starting...</h1>";

$db = getDB();

// ✅ CLEAN INSTALL: Drop existing tables and recreate them properly
// This ensures all new columns like 'lifestyle', 'pets', etc. are added.
$db->query("SET FOREIGN_KEY_CHECKS = 0;");
$tables = ['notifications', 'blocks', 'reports', 'profile_views', 'messages', 'call_logs', 'matches', 'swipes', 'user_posts', 'user_photos', 'otp_codes', 'users'];
foreach ($tables as $table) {
    $db->query("DROP TABLE IF EXISTS `$table`;");
}
$db->query("SET FOREIGN_KEY_CHECKS = 1;");

$schema = file_get_contents(__DIR__ . '/schema_production.sql');

// Remove comments
$schema = preg_replace('/--.*$/m', '', $schema);

// Split into individual queries
$queries = explode(';', $schema);
$count = 0;

foreach ($queries as $query) {
    $q = trim($query);
    if (!empty($q)) {
        if ($db->query($q)) {
            $count++;
        } else {
            echo "<p style='color:red;'>❌ Error in query $count: " . $db->error . "</p>";
            echo "<pre>$q</pre>";
        }
    }
}

echo "<h2>✅ Success! $count queries executed.</h2>";
echo "<p>Your Aiven database is now ready with all tables.</p>";
echo "<p><strong>IMPORTANT: Delete this file (setup_db.php) once you are done for security!</strong></p>";

$db->close();
?>
