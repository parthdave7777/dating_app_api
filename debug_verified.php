<?php
require_once __DIR__ . '/config.php';
$db = getDB();

echo "--- User 1 vs User 2 Verification Status ---\n";

$res = $db->query("SELECT id, full_name, is_verified FROM users WHERE id IN (1, 2)");
while ($row = $res->fetch_assoc()) {
    echo "User [ID: {$row['id']}]: {$row['full_name']} | Verified: {$row['is_verified']}\n";
}

$db->close();
?>
