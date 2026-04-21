<?php
require_once __DIR__ . '/config.php';

$db = getDB();

echo "Starting optimizations...\n";

// 1. Create Indexes
$queries = [
    "ALTER TABLE messages ADD INDEX idx_unread (match_id, sender_id, is_read)",
    "ALTER TABLE messages ADD INDEX idx_paginate (match_id, id)",
    "ALTER TABLE users ADD INDEX idx_coords (latitude, longitude)",
    "ALTER TABLE swipes ADD INDEX idx_spam (swiper_id, created_at)"
];

foreach ($queries as $q) {
    if ($db->query($q)) {
        echo "Executed: $q\n";
    } else {
        echo "Skipped/Error (Might already exist): " . $db->error . " | $q\n";
    }
}

$db->close();
echo "Optimizations complete.\n";
?>
