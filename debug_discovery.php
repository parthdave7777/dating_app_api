<?php
// debug_discovery.php
require_once __DIR__ . '/config.php';
$db = getDB();
echo "<h1>🔍 Discovery Debugger</h1>";

$userId = getAuthUserId();
$res = $db->query("SELECT * FROM users WHERE id = $userId");
$me = $res->fetch_assoc();

echo "<strong>YOU:</strong> Lat: " . ($me['latitude'] ?: 'NULL') . " Lon: " . ($me['longitude'] ?: 'NULL') . "<br>";

$others = $db->query("SELECT id, full_name, profile_complete, gender, age, latitude FROM users WHERE id != $userId");

echo "<h3>Other Users Found (" . $others->num_rows . "):</h3>";
while ($u = $others->fetch_assoc()) {
    echo "- <strong>" . $u['full_name'] . "</strong>: ";
    echo ($u['profile_complete'] == 1 ? "✅ Complete" : "❌ INCOMPLETE");
    echo " | Gender: " . $u['gender'];
    echo " | Age: " . $u['age'];
    echo " | Lat: " . ($u['latitude'] ?: 'NULL');
    echo "<br>";
}
?>
