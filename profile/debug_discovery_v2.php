<?php
// debug_discovery_v2.php
// Manual script to verify discovery logic without Flutter.
// Access this via browser: http://localhost/dating_api/profile/debug_discovery_v2.php?user_id=27

require_once __DIR__ . '/../config.php';

$userId = isset($_GET['user_id']) ? (int)$_GET['user_id'] : 0;
if ($userId === 0) {
    die("Please provide a user_id via URL parameter, e.g., ?user_id=27");
}

$db = getDB();

// 1. Check if user exists
$meRes = $db->query("SELECT id, full_name, gender, latitude, longitude, profile_complete, show_in_discovery FROM users WHERE id = $userId");
$me = $meRes->fetch_assoc();

if (!$me) {
    die("User ID $userId not found in database.");
}

echo "<h1>Discovery Debugger</h1>";
echo "<b>Current User:</b> " . $me['full_name'] . " (ID: $userId)<br>";
echo "<b>Gender:</b> " . $me['gender'] . "<br>";
echo "<b>Status:</b> Profile Complete: " . $me['profile_complete'] . " | Show in Discovery: " . $me['show_in_discovery'] . "<br>";
echo "<b>Location:</b> Lat: " . $me['latitude'] . " | Lng: " . $me['longitude'] . "<br>";
echo "<hr>";

// 2. Mock the request to get_users.php output
echo "<h2>Simulating get_users.php Results:</h2>";

// Capture the output of get_users.php
// We bypass getAuthUserId() by temporarily setting a session OR just running the same query logic
$targetGender = (strtolower($me['gender'] ?? '') === 'woman') ? 'man' : 'woman';

echo "<b>Targeting Gender:</b> $targetGender<br>";

$query = "
    SELECT u.id, u.full_name, u.gender, u.profile_complete, u.show_in_discovery, u.latitude, u.longitude
    FROM users u
    WHERE u.id != $userId
      AND u.profile_complete = 1 
      AND u.show_in_discovery = 1
      AND LOWER(u.gender) = '$targetGender'
";

$res = $db->query($query);
echo "<b>Potential Candidates found in DB:</b> " . $res->num_rows . "<br><br>";

if ($res->num_rows > 0) {
    echo "<table border='1' cellpadding='10'>";
    echo "<tr><th>ID</th><th>Name</th><th>Gender</th><th>Lat</th><th>Lng</th></tr>";
    while($row = $res->fetch_assoc()) {
        echo "<tr>";
        echo "<td>".$row['id']."</td>";
        echo "<td>".$row['full_name']."</td>";
        echo "<td>".$row['gender']."</td>";
        echo "<td>".$row['latitude']."</td>";
        echo "<td>".$row['longitude']."</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<div style='color:red; font-weight:bold;'>CRITICAL: Zero candidates found.</div>";
    echo "Possible reasons:<br>";
    echo "1. No users of gender '$targetGender' exist.<br>";
    echo "2. Existing users have profile_complete = 0.<br>";
    echo "3. Existing users have show_in_discovery = 0.<br>";
}

$db->close();
?>
