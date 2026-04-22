<?php
require_once 'config.php';
$db = getDB();
$tables = ['users', 'messages', 'swipes', 'matches', 'notifications', 'profile_views', 'user_photos', 'user_posts'];
$stats = [];
foreach ($tables as $table) {
    try {
        $res = $db->query("SELECT COUNT(*) as cnt FROM $table");
        $stats[$table] = $res->fetch_assoc()['cnt'];
    } catch (Exception $e) {
        $stats[$table] = 'Error: ' . $e->getMessage();
    }
}
echo json_encode($stats, JSON_PRETTY_PRINT);
