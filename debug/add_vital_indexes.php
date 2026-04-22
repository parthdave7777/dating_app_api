<?php
require_once 'config.php';
$db = getDB();

echo "Running vital optimizations...\n";

$queries = [
    // 1. Coordinates indexes (The #1 reason discovery is slow)
    "CREATE INDEX idx_users_lat_lng ON users (latitude, longitude)",
    "CREATE INDEX idx_users_geo ON users (latitude, longitude, show_in_discovery, gender, age)",

    // 2. Swipes indexes (For fast matching and discovery excludes)
    "CREATE INDEX idx_swipes_lookup ON swipes (swiper_id, action)",
    
    // 3. Messages indexes (For count and read status)
    "CREATE INDEX idx_messages_unread ON messages (match_id, sender_id, is_read)",
    
    // 4. Notifications index
    "CREATE INDEX idx_notif_created ON notifications (created_at)"
];

foreach ($queries as $q) {
    echo "Executing: $q ... ";
    try {
        $db->query($q);
        echo "OK\n";
    } catch (Exception $e) {
        echo "Failed or already exists: " . $e->getMessage() . "\n";
    }
}

echo "Optimizations complete!\n";
