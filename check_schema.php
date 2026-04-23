<?php
require_once 'config.php';
$db = getDB();
$res = $db->query("DESCRIBE messages");
while ($row = $res->fetch_assoc()) {
    print_r($row);
}
$db->close();
