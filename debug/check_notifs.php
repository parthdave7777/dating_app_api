<?php
require_once 'config.php';
$db = getDB();
$users = [1, 684];
$results = [];
foreach ($users as $id) {
    $res = $db->query("SELECT id, full_name, fcm_token, notif_messages, notif_matches, notif_likes FROM users WHERE id = $id");
    $results[$id] = $res->fetch_assoc();
}
echo json_encode($results, JSON_PRETTY_PRINT);
