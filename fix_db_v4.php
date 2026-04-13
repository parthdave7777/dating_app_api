<?php
// fix_database_v4.php
require_once __DIR__ . '/config.php';
$db = getDB();

echo "Starting Final Database Synchronization...\n\n";

// 1. Check/Add Users Columns
$userColumns = [
    'credits' => "INT DEFAULT 100",
    'premium_credits' => "INT DEFAULT 0",
    'last_credit_refresh' => "TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP",
    'notif_who_swiped' => "TINYINT(1) DEFAULT 1",
    'state' => "VARCHAR(100) DEFAULT NULL",
    'country' => "VARCHAR(100) DEFAULT NULL",
    'is_new_user_boost' => "TINYINT(1) DEFAULT 1",
    'new_user_boost_expires' => "DATETIME DEFAULT NULL"
];

foreach ($userColumns as $col => $def) {
    $check = $db->query("SHOW COLUMNS FROM users LIKE '$col'");
    if ($check->num_rows === 0) {
        echo "Adding column users.$col...\n";
        $db->query("ALTER TABLE users ADD COLUMN $col $def");
    } else {
        echo "OK: users.$col exists.\n";
    }
}

// 2. Check/Add Messages Columns
$msgColumns = [
    'is_received' => "TINYINT(1) DEFAULT 0",
    'is_view_once' => "TINYINT(1) DEFAULT 0",
    'is_opened' => "TINYINT(1) DEFAULT 0",
    'received_at' => "DATETIME DEFAULT NULL",
    'opened_at' => "DATETIME DEFAULT NULL",
    'deleted_at' => "DATETIME DEFAULT NULL"
];

foreach ($msgColumns as $col => $def) {
    $check = $db->query("SHOW COLUMNS FROM messages LIKE '$col'");
    if ($check->num_rows === 0) {
        echo "Adding column messages.$col...\n";
        $db->query("ALTER TABLE messages ADD COLUMN $col $def");
    } else {
        echo "OK: messages.$col exists.\n";
    }
}

// 2.5 Check/Add User Photos Columns
$photoColumns = [
    'is_selfie' => "TINYINT(1) DEFAULT 0"
];

foreach ($photoColumns as $col => $def) {
    $check = $db->query("SHOW COLUMNS FROM user_photos LIKE '$col'");
    if ($check->num_rows === 0) {
        echo "Adding column user_photos.$col...\n";
        $db->query("ALTER TABLE user_photos ADD COLUMN $col $def");
    } else {
        echo "OK: user_photos.$col exists.\n";
    }
}

// 3. Check/Add Missing Tables
$tables = [
    'compliments' => "CREATE TABLE `compliments` (
        `id` int NOT NULL AUTO_INCREMENT,
        `sender_id` int NOT NULL,
        `receiver_id` int NOT NULL,
        `message` text NOT NULL,
        `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        CONSTRAINT `compliments_ibfk_1` FOREIGN KEY (`sender_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
        CONSTRAINT `compliments_ibfk_2` FOREIGN KEY (`receiver_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
    
    'unlocked_profiles' => "CREATE TABLE `unlocked_profiles` (
        `id` int NOT NULL AUTO_INCREMENT,
        `user_id` int NOT NULL,
        `target_id` int NOT NULL,
        `type` varchar(50) DEFAULT NULL,
        `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        UNIQUE KEY `unq_unlock` (`user_id`,`target_id`,`type`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
    
    'credit_logs' => "CREATE TABLE `credit_logs` (
        `id` int NOT NULL AUTO_INCREMENT,
        `user_id` int NOT NULL,
        `amount` int NOT NULL,
        `reason` varchar(255) DEFAULT NULL,
        `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        CONSTRAINT `credit_logs_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

    'swipes' => "CREATE TABLE `swipes` (
        `id` int NOT NULL AUTO_INCREMENT,
        `swiper_id` int NOT NULL,
        `swiped_id` int NOT NULL,
        `action` enum('like','dislike','superlike') NOT NULL,
        `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        UNIQUE KEY `unique_swipe` (`swiper_id`,`swiped_id`),
        CONSTRAINT `swipes_ibfk_1` FOREIGN KEY (`swiper_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
        CONSTRAINT `swipes_ibfk_2` FOREIGN KEY (`swiped_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
];

foreach ($tables as $name => $sql) {
    $check = $db->query("SHOW TABLES LIKE '$name'");
    if ($check->num_rows === 0) {
        echo "Creating table $name...\n";
        $db->query($sql);
    } else {
        echo "OK: Table $name exists.\n";
    }
}

echo "\nDatabase Synchronization Complete!\n";
$db->close();
?>
