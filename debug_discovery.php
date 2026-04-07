<?php
// Simple Debug for Parth (ID 23)
require_once __DIR__ . '/config.php';

$userId = 23; // HARDCODING PARTH'S ID
$db = getDB();

// Fetch Parth's requirements
$meQuery = "SELECT id, age, gender, looking_for, latitude, longitude FROM users WHERE id = $userId";
$meRs = $db->query($meQuery);
$me = $meRs->fetch_assoc();

$myLookingFor = strtolower(trim($me['looking_for']));
echo "Parth (23) is looking for: [" . $myLookingFor . "]\n";

// Manual Query for Hetanshi (ID 24)
$targetId = 24;
$targetQuery = "SELECT id, full_name, age, gender, latitude, longitude FROM users WHERE id = $targetId";
$targetRs = $db->query($targetQuery);
$target = $targetRs->fetch_assoc();

$targetGender = strtolower(trim($target['gender']));
echo "Target (Hetanshi, 24) gender is: [" . $targetGender . "]\n";

// Check the Clause
$genderClause = ($targetGender === $myLookingFor) ? "MATCH FOUND" : "NO MATCH";
echo "Gender Match Result: " . $genderClause . "\n";

// Check Distance
$dist = 0;
if ($me['latitude'] && $target['latitude']) {
    $R = 6371; 
    $dLat = deg2rad($target['latitude'] - $me['latitude']);
    $dLon = deg2rad($target['longitude'] - $me['longitude']);
    $a = sin($dLat/2) * sin($dLat/2) + cos(deg2rad($me['latitude'])) * cos(deg2rad($target['latitude'])) * sin($dLon/2) * sin($dLon/2);
    $dist = $R * 2 * atan2(sqrt($a), sqrt(1-$a));
}
echo "Calculated Distance: " . $dist . " km\n";

$db->close();
?>
