<?php
// force_update_schema.php
require_once 'config.php';
$db = getDB();

$columns = [
    "job_title VARCHAR(255) DEFAULT ''",
    "company VARCHAR(255) DEFAULT ''",
    "lifestyle_pets VARCHAR(255) DEFAULT ''",
    "lifestyle_smoking VARCHAR(255) DEFAULT ''",
    "lifestyle_drinking VARCHAR(255) DEFAULT ''",
    "lifestyle_workout VARCHAR(255) DEFAULT ''",
    "lifestyle_diet VARCHAR(255) DEFAULT ''",
    "lifestyle_schedule VARCHAR(255) DEFAULT ''",
    "relationship_goal VARCHAR(255) DEFAULT ''",
    "communication_style VARCHAR(255) DEFAULT ''",
    "setup_completed TINYINT(1) DEFAULT 0",
    "profile_complete TINYINT(1) DEFAULT 0",
    "city VARCHAR(255) DEFAULT ''",
    "latitude DECIMAL(10,8) DEFAULT 0",
    "longitude DECIMAL(11,8) DEFAULT 0"
];

foreach ($columns as $colDef) {
    $colName = explode(' ', $colDef)[0];
    try {
        $db->query("ALTER TABLE users ADD COLUMN $colName $colDef");
        echo "Successfully added $colName\n";
    } catch (Exception $e) {
        echo "Column $colName already exists or skip: " . $e->getMessage() . "\n";
    }
}

$db->close();
echo "Total Schema Update Complete!";
?>
