<?php
require_once __DIR__ . '/config.php';
$db = getDB();

echo "--- Debugging User Discovery ---\n";

$res = $db->query("SELECT id, full_name, gender, city, show_in_discovery, profile_complete, latitude, longitude, age FROM users");
while($row = $res->fetch_assoc()) {
    echo "User [ID: {$row['id']}]: {$row['full_name']} | Gender: {$row['gender']} | City: {$row['city']} | Active: {$row['show_in_discovery']} | Complete: {$row['profile_complete']} | Lat/Lng: {$row['latitude']}, {$row['longitude']} | Age: {$row['age']}\n";
}

$db->close();
?>
