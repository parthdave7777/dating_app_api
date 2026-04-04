<?php
require_once 'c:/xampp/htdocs/dating_api/config.php';
$db = getDB();

echo "--- USERS ---\n";
$res = $db->query("DESCRIBE users");
while($row = $res->fetch_assoc()) {
    echo $row['Field'] . " (" . $row['Type'] . ")\n";
}

echo "\n--- MESSAGES ---\n";
$res = $db->query("DESCRIBE messages");
while($row = $res->fetch_assoc()) {
    echo $row['Field'] . " (" . $row['Type'] . ")\n";
}

$db->close();
?>
