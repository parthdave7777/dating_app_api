<?php
/**
 * scripts/optimize_db.php
 * Run this script to add missing indexes to your database.
 * Usage: php scripts/optimize_db.php
 */

require_once __DIR__ . '/../config.php';

$db = getDB();

$indexes = [
    'user_photos'   => ['idx_user_dp', '(user_id, is_dp)'],
    'notifications' => ['idx_user_created', '(user_id, created_at DESC)'],
    'profile_views' => ['idx_viewed_at', '(viewed_id, viewed_at DESC)'],
    'compliments'   => ['idx_receiver_created', '(receiver_id, created_at DESC)'],
    'users'         => ['idx_last_active', '(last_active)'],
    'swipes'        => ['idx_swiper_action', '(swiper_id, action)'],
    'matches'       => ['idx_user1_user2', '(user1_id, user2_id)'],
];

echo "--- DATING APP DB OPTIMIZER ---\n";

foreach ($indexes as $table => $data) {
    list($indexName, $definition) = $data;
    echo "Processing $table [$indexName]... ";
    
    // Check if index exists
    $checkSql = "SHOW INDEX FROM $table WHERE Key_name = '$indexName'";
    $res = $db->query($checkSql);
    
    if ($res && $res->num_rows > 0) {
        echo "ALREADY EXISTS. Skipping.\n";
        continue;
    }
    
    // Add index
    $alterSql = "ALTER TABLE $table ADD INDEX $indexName $definition";
    try {
        if ($db->query($alterSql)) {
            echo "SUCCESS.\n";
        } else {
            echo "FAILED: " . $db->error . "\n";
        }
    } catch (Exception $e) {
        if (strpos($e->getMessage(), 'Duplicate key name') !== false) {
            echo "ALREADY EXISTS (caught exception).\n";
        } else {
            echo "ERROR: " . $e->getMessage() . "\n";
        }
    }
}

echo "--- DB OPTIMIZATION COMPLETE ---\n";
