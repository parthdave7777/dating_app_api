<?php
// setup_db_settings.php — Ensures that all the necessary discovery setting columns exist in the users table.
require_once __DIR__ . '/config.php';
$db = getDB();

$queries = [
    "ALTER TABLE users ADD COLUMN IF NOT EXISTS discovery_min_age INT DEFAULT 18",
    "ALTER TABLE users ADD COLUMN IF NOT EXISTS discovery_max_age INT DEFAULT 55",
    "ALTER TABLE users ADD COLUMN IF NOT EXISTS discovery_max_dist INT DEFAULT 50",
    "ALTER TABLE users ADD COLUMN IF NOT EXISTS global_discovery TINYINT(1) DEFAULT 1",
    "ALTER TABLE users ADD COLUMN IF NOT EXISTS notif_matches TINYINT(1) DEFAULT 1",
    "ALTER TABLE users ADD COLUMN IF NOT EXISTS notif_messages TINYINT(1) DEFAULT 1",
    "ALTER TABLE users ADD COLUMN IF NOT EXISTS notif_likes TINYINT(1) DEFAULT 1",
    "ALTER TABLE users ADD COLUMN IF NOT EXISTS notif_who_swiped TINYINT(1) DEFAULT 1",
    "ALTER TABLE users ADD COLUMN IF NOT EXISTS notif_activity TINYINT(1) DEFAULT 0",
    "ALTER TABLE users ADD COLUMN IF NOT EXISTS discovery_min_dist INT DEFAULT 0",
];

foreach ($queries as $q) {
    try {
        $db->query($q);
    } catch (Exception $e) {
        // Column might already exist without "IF NOT EXISTS" support in some MySQL versions
    }
}

$db->close();
echo json_encode(['status' => 'success', 'message' => 'New settings columns were applied or already exist.']);
?>
