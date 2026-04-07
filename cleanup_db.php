<?php
// cleanup_db.php
require_once __DIR__ . '/config.php';

echo "<h1>🚀 Database Cleanup Starting...</h1>";

$db = getDB();

// LIST OF COLUMNS TO BE REMOVED (THE DEAD ONES)
$deadColumns = [
    'height',               // Not shown in profile or search
    'state',                // Unified into 'city' string manually
    'country',              // Unified into 'city' string manually
    'verification_status',  // We use 'is_verified' bit instead
    'is_new_user_boost',    // Not used in current ranking logic
    'new_user_boost_expires' // Not used in current ranking logic
];

foreach ($deadColumns as $col) {
    try {
        if ($db->query("ALTER TABLE users DROP COLUMN `$col`")) {
            echo "<p style='color:green;'>✅ Column <b>$col</b> dropped successfully.</p>";
        } else {
            echo "<p style='color:orange;'>ℹ️ Column <b>$col</b> does not exist or already removed.</p>";
        }
    } catch (Exception $e) {
        echo "<p style='color:orange;'>ℹ️ Skip <b>$col</b>: " . $e->getMessage() . "</p>";
    }
}

echo "<h2>✅ Success! Your database is now clean and optimized.</h2>";
echo "<p><strong>IMPORTANT: Delete this file (cleanup_db.php) once you are done for security!</strong></p>";

$db->close();
?>
