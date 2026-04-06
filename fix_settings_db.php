<?php
require_once __DIR__ . '/config.php';

$db = getDB();

// Ensure all discovery setting columns exist in the users table
$columns = [
    'discovery_min_age'  => "INT DEFAULT 18",
    'discovery_max_age'  => "INT DEFAULT 100",
    'discovery_min_dist' => "INT DEFAULT 0",
    'discovery_max_dist' => "INT DEFAULT 50",
    'global_discovery'   => "TINYINT DEFAULT 0",
    'show_in_discovery'  => "TINYINT DEFAULT 1"
];

echo "Updating users table columns...<br>";

foreach ($columns as $col => $type) {
    // Check if column exists
    $result = $db->query("SHOW COLUMNS FROM users LIKE '$col'");
    if ($result->num_rows == 0) {
        echo "Adding column: $col... ";
        if ($db->query("ALTER TABLE users ADD COLUMN $col $type")) {
            echo "DONE!<br>";
        } else {
            echo "FAILED: " . $db->error . "<br>";
        }
    } else {
        echo "Column $col already exists.<br>";
    }
}

$db->close();
echo "<br><b>DATABASE UPDATED SUCCESSFULLY!</b>";
?>
