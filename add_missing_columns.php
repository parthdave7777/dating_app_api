<?php
// add_missing_columns.php
require_once 'config.php';
$db = getDB();

$columns = [
    "lifestyle_pets",
    "lifestyle_smoking",
    "lifestyle_drinking",
    "lifestyle_workout",
    "lifestyle_diet",
    "lifestyle_schedule",
    "relationship_goal",
    "communication_style"
];

foreach ($columns as $col) {
    $db->query("ALTER TABLE users ADD COLUMN IF NOT EXISTS $col VARCHAR(255) DEFAULT ''");
    echo "Processing $col...\n";
}

$db->close();
echo "Done! All lifestyle columns are now in the database.";
?>
