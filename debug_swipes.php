<?php
require_once __DIR__ . '/config.php';
$db = getDB();

echo "--- Swipe Check for User 1 and 2 ---\n";

$res = $db->query("SELECT * FROM swipes WHERE swiper_id IN (1, 2) OR swiped_id IN (1, 2)");
while ($row = $res->fetch_assoc()) {
    echo "Swipe [ID: {$row['id']}]: Swiper {$row['swiper_id']} -> Swiped {$row['swiped_id']} | Action: {$row['action']}\n";
}

$db->close();
?>
