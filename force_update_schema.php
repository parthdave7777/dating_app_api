<?php
// force_update_schema.php — Aggressive schema synchronizer
require_once __DIR__ . '/config.php';
$db = getDB();

$columns = [
    'users' => [
        'discovery_min_age'  => "INT DEFAULT 18",
        'discovery_max_age'  => "INT DEFAULT 55",
        'discovery_max_dist' => "INT DEFAULT 50",
        'discovery_min_dist' => "INT DEFAULT 0",
        'global_discovery'   => "TINYINT(1) DEFAULT 1",
        'notif_matches'      => "TINYINT(1) DEFAULT 1",
        'notif_messages'     => "TINYINT(1) DEFAULT 1",
        'notif_likes'        => "TINYINT(1) DEFAULT 1",
        'notif_who_swiped'   => "TINYINT(1) DEFAULT 1",
        'notif_activity'     => "TINYINT(1) DEFAULT 1",
        'show_in_discovery'  => "TINYINT(1) DEFAULT 1",
        'is_verified'        => "TINYINT(1) DEFAULT 0",
        'profile_complete'   => "TINYINT(1) DEFAULT 0",
        'setup_completed'    => "TINYINT(1) DEFAULT 0",
        'job_title'          => "VARCHAR(100) DEFAULT NULL",
        'company'            => "VARCHAR(100) DEFAULT NULL",
        'education'          => "VARCHAR(100) DEFAULT NULL",
        'lifestyle_pets'     => "VARCHAR(50) DEFAULT NULL",
        'lifestyle_drinking' => "VARCHAR(50) DEFAULT NULL",
        'lifestyle_smoking'  => "VARCHAR(50) DEFAULT NULL",
        'lifestyle_workout'  => "VARCHAR(50) DEFAULT NULL",
        'lifestyle_diet'     => "VARCHAR(50) DEFAULT NULL",
        'lifestyle_schedule' => "VARCHAR(50) DEFAULT NULL"
    ]
];

$results = [];

foreach ($columns as $table => $fields) {
    foreach ($fields as $field => $definition) {
        // Check if column exists
        $check = $db->query("SHOW COLUMNS FROM `$table` LIKE '$field'");
        if ($check->num_rows == 0) {
            $db->query("ALTER TABLE `$table` ADD COLUMN `$field` $definition");
            $results[] = "ADDED: $table.$field";
        } else {
            $results[] = "EXISTS: $table.$field";
        }
    }
}

$db->close();
header('Content-Type: application/json');
echo json_encode([
    'status' => 'success',
    'modified' => count(array_filter($results, fn($r) => strpos($r, 'ADDED') === 0)),
    'log' => $results
]);
