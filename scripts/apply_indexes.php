<?php
require_once __DIR__ . '/../config.php';

$db = getDB();

echo "Adding indexes to messages table...\n";

$queries = [
    "ALTER TABLE messages ADD INDEX idx_match_created (match_id, created_at)",
    "ALTER TABLE messages ADD INDEX idx_unread_count (match_id, is_read, sender_id)"
];

foreach ($queries as $sql) {
    try {
        if ($db->query($sql)) {
            echo "SUCCESS: $sql<br>\n";
        }
    } catch (Exception $e) {
        if (strpos($e->getMessage(), 'Duplicate key name') !== false) {
            echo "SKIPPED: Index already exists.<br>\n";
        } else {
            echo "ERROR: " . $e->getMessage() . "<br>\n";
        }
    }
}

$db->close();
echo "Done.\n";
