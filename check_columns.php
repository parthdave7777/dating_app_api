<?php
require_once 'config.php';
$db = getDB();
$res = $db->query("DESCRIBE users");
while($row = $res->fetch_assoc()) {
    echo $row['Field'] . "\n";
}
$db->close();
?>
