<?php
// scripts/verify_integrity.php
require_once __DIR__ . '/../config.php';

$db = getDB();

echo "--- DATA INTEGRITY CHECK ---\n\n";

// 1. Check User A and B
$res = $db->query("SELECT id, phone_number, credits FROM users WHERE phone_number IN ('1234567890', '0987654321')");
$users = [];
while ($row = $res->fetch_assoc()) $users[$row['phone_number']] = $row;

$uidA = $users['1234567890']['id'] ?? null;
$uidB = $users['0987654321']['id'] ?? null;

if (!$uidA || !$uidB) die("Users not found in DB.\n");

echo "User A (ID $uidA) Credits: " . ($users['1234567890']['credits'] ?? 0) . "\n";
echo "User B (ID $uidB) Credits: " . ($users['0987654321']['credits'] ?? 0) . "\n\n";

// 2. Check Swipes
$swipes = $db->query("SELECT * FROM swipes WHERE swiper_id = $uidA AND swiped_id = $uidB")->fetch_assoc();
echo "Swipe from A to B: " . ($swipes ? $swipes['action'] . " (" . $swipes['created_at'] . ")" : "NOT FOUND") . "\n";

$swipes2 = $db->query("SELECT * FROM swipes WHERE swiper_id = $uidB AND swiped_id = $uidA")->fetch_assoc();
echo "Swipe from B to A: " . ($swipes2 ? $swipes2['action'] . " (" . $swipes2['created_at'] . ")" : "NOT FOUND") . "\n\n";

// 3. Check Matches
$u1 = min($uidA, $uidB); $u2 = max($uidA, $uidB);
$match = $db->query("SELECT * FROM matches WHERE user1_id = $u1 AND user2_id = $u2")->fetch_assoc();
echo "Match Record: " . ($match ? "FOUND (ID " . $match['id'] . ")" : "NOT FOUND") . "\n";

// 4. Check Compliments
$comp = $db->query("SELECT * FROM compliments WHERE sender_id = $uidA AND receiver_id = $uidB")->fetch_assoc();
echo "Compliment Record: " . ($comp ? "FOUND: '" . $comp['message'] . "'" : "NOT FOUND") . "\n";

// 5. Check Messages
$msg = $db->query("SELECT * FROM messages WHERE sender_id = $uidA AND receiver_id = $uidB ORDER BY created_at DESC LIMIT 1")->fetch_assoc();
echo "Latest Message: " . ($msg ? "'" . $msg['message'] . "'" : "NOT FOUND") . "\n\n";

echo "--- CHECK COMPLETE ---\n";
$db->close();
