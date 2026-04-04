<?php
require_once __DIR__ . '/config.php';
$db = getDB();

echo "--- User 1 vs User 2 Status ---\n";

$res = $db->query("SELECT id, full_name, gender, profile_complete, show_in_discovery, latitude, longitude, age, interests FROM users WHERE id IN (1, 2)");
while ($row = $res->fetch_assoc()) {
    echo "User [ID: {$row['id']}]: {$row['full_name']} | Gender: {$row['gender']} | Complete: {$row['profile_complete']} | Active: {$row['show_in_discovery']} | Lat/Lng: {$row['latitude']}, {$row['longitude']} | Age: {$row['age']} | Interests: {$row['interests']}\n";
}

$db->close();
?>
