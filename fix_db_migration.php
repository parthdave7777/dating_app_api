<?php
// fix_db_migration.php
// Visit this file in your browser: http://localhost/dating_api/fix_db_migration.php
require_once __DIR__ . '/config.php';

$db = getDB();

$queries = [
    // Ensure all profile columns exist
    "ALTER TABLE users ADD COLUMN IF NOT EXISTS relationship_goal VARCHAR(50) DEFAULT NULL",
    "ALTER TABLE users ADD COLUMN IF NOT EXISTS lifestyle_drinking VARCHAR(50) DEFAULT NULL",
    "ALTER TABLE users ADD COLUMN IF NOT EXISTS lifestyle_smoking VARCHAR(50) DEFAULT NULL",
    "ALTER TABLE users ADD COLUMN IF NOT EXISTS lifestyle_workout VARCHAR(50) DEFAULT NULL",
    "ALTER TABLE users ADD COLUMN IF NOT EXISTS lifestyle_pets VARCHAR(50) DEFAULT NULL",
    "ALTER TABLE users ADD COLUMN IF NOT EXISTS lifestyle_diet VARCHAR(50) DEFAULT NULL",
    "ALTER TABLE users ADD COLUMN IF NOT EXISTS lifestyle_schedule VARCHAR(50) DEFAULT NULL",
    "ALTER TABLE users ADD COLUMN IF NOT EXISTS communication_style VARCHAR(50) DEFAULT NULL",
    "ALTER TABLE users ADD COLUMN IF NOT EXISTS setup_completed TINYINT(1) DEFAULT 0",
    "ALTER TABLE users ADD COLUMN IF NOT EXISTS profile_complete TINYINT(1) DEFAULT 0",
    "ALTER TABLE users ADD COLUMN IF NOT EXISTS verification_status TINYINT(1) DEFAULT 0",
    "ALTER TABLE users ADD COLUMN IF NOT EXISTS latitude DECIMAL(10,7) DEFAULT NULL",
    "ALTER TABLE users ADD COLUMN IF NOT EXISTS longitude DECIMAL(10,7) DEFAULT NULL",
    "ALTER TABLE users ADD COLUMN IF NOT EXISTS city VARCHAR(100) DEFAULT NULL",
    
    // Ensure user_photos has is_verified
    "ALTER TABLE user_photos ADD COLUMN IF NOT EXISTS is_verified TINYINT(1) DEFAULT 0"
];

echo "<h2>Starting Database Migration Fix...</h2>";
echo "<ul>";
foreach ($queries as $q) {
    echo "<li>Executing: <code>$q</code> ... ";
    if ($db->query($q)) {
        echo "<b style='color:green'>SUCCESS</b>";
    } else {
        echo "<b style='color:red'>FAILED</b> (" . $db->error . ")";
    }
    echo "</li>";
}
echo "</ul>";

$db->close();
echo "<h3>Done! Try setting up your profile now.</h3>";
?>
