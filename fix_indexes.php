<?php
// fix_indexes.php
require_once __DIR__ . '/config.php';
$db = getDB();

echo "Adding indexes to users table for discovery filters...\n";

$queries = [
    "ALTER TABLE users ADD INDEX idx_rel_goal (relationship_goal)",
    "ALTER TABLE users ADD INDEX idx_smoke (lifestyle_smoking)",
    "ALTER TABLE users ADD INDEX idx_drink (lifestyle_drinking)",
    "ALTER TABLE users ADD INDEX idx_age (age)",
    "ALTER TABLE users ADD INDEX idx_gender (gender)"
];

foreach ($queries as $q) {
    try {
        if ($db->query($q)) {
            echo "Success: $q\n";
        } else {
            echo "Failed (might already exist): " . $db->error . "\n";
        }
    } catch (Exception $e) {
        echo "Error: " . $e->getMessage() . "\n";
    }
}

$db->close();
echo "Done.\n";
