<?php
require_once 'config.php';
$db = getDB();
$users = [1, 684];
$results = [];
foreach ($users as $id) {
    try {
        $res = $db->query("SELECT id, full_name, fcm_token, notif_messages, notif_matches, notif_likes FROM users WHERE id = $id");
        $results[$id] = $res->fetch_assoc();
    } catch(Exception $e) { $results[$id] = $e->getMessage(); }
}
file_put_contents('debug_notifs_out.txt', json_encode($results, JSON_PRETTY_PRINT));
echo "Done";
