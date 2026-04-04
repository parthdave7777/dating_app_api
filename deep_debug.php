<?php
require_once __DIR__ . '/config.php';
$db = getDB();

// Debugging why User 1 doesn't see User 2
$id1 = 1;
$id2 = 2;

echo "--- DEEP DEBUG: User 1 vs User 2 ---\n";

function getU($id, $db) {
    return $db->query("SELECT * FROM users WHERE id=$id")->fetch_assoc();
}

$u1 = getU($id1, $db);
$u2 = getU($id2, $db);

echo "User 1: {$u1['full_name']} | Gender: {$u1['gender']} | Lat/Lng: {$u1['latitude']}, {$u1['longitude']} | Interests: {$u1['interests']}\n";
echo "User 2: {$u2['full_name']} | Gender: {$u2['gender']} | Lat/Lng: {$u2['latitude']}, {$u2['longitude']} | Interests: {$u2['interests']}\n";

// Haversine
function hav($lat1, $lon1, $lat2, $lon2) {
    $R = 6371;
    $dLat = deg2rad($lat2 - $lat1);
    $dLon = deg2rad($lon2 - $lon1);
    $a = sin($dLat/2) * sin($dLat/2) + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLon/2) * sin($dLon/2);
    return round($R * 2 * atan2(sqrt($a), sqrt(1-$a)), 2);
}

$dist = hav($u1['latitude'], $u1['longitude'], $u2['latitude'], $u2['longitude']);
echo "Distance between them: {$dist} km\n";

// Check if User 1 matched or liked User 2
$swipe = $db->query("SELECT * FROM swipes WHERE swiper_id=$id1 AND swiped_id=$id2")->fetch_assoc();
if ($swipe) {
    echo "User 1 ALREADY SWIPED on User 2: Action: {$swipe['action']} at {$swipe['created_at']}\n";
} else {
    echo "No swipe record from 1 to 2.\n";
}

// Check other conditions
echo "User 2 Profile Complete: {$u2['profile_complete']}\n";
echo "User 2 Show in Discovery: {$u2['show_in_discovery']}\n";
echo "User 1 Looking For: {$u1['looking_for']}\n";
echo "User 2 Gender: {$u2['gender']}\n";

// Find Ranking
echo "\n--- RANKING CHECK ---\n";
// (Logic from get_users.php)
$myInterests = array_filter(array_map('trim', explode(',', strtolower($u1['interests'] ?? ''))));
$u2Interests = array_filter(array_map('trim', explode(',', strtolower($u2['interests'] ?? ''))));
$overlap = count(array_intersect($myInterests, $u2Interests));

$score = 10000000;
$score -= ($dist * 1000000);
$ageDiff = abs($u1['age'] - $u2['age']);
$score += max(0, 2000000 - ($ageDiff * 100000));
$score += ($overlap * 50000);

echo "CALCULATED SCORE for User 2: $score\n";

$res = $db->query("SELECT id FROM users WHERE id != 1 AND gender='man' AND profile_complete=1 AND show_in_discovery=1");
$higher = 0;
while($row = $res->fetch_assoc()) {
    $oid = $row['id'];
    if ($oid == 2) continue;
    $o = getU($oid, $db);
    $odist = hav($u1['latitude'], $u1['longitude'], $o['latitude'], $o['longitude']);
    $oInts = array_filter(array_map('trim', explode(',', strtolower($o['interests'] ?? ''))));
    $oOverlap = count(array_intersect($myInterests, $oInts));
    
    $oscore = 10000000;
    $oscore -= ($odist * 1000000);
    $oageDiff = abs($u1['age'] - $o['age']);
    $oscore += max(0, 2000000 - ($oageDiff * 100000));
    $oscore += ($oOverlap * 50000);
    
    if ($oscore > $score) $higher++;
}
echo "Users with HIGHER score than User 2: $higher\n";
?>
