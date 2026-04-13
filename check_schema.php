<?php
require_once __DIR__ . '/config.php';
$db = getDB();
$res = $db->query("DESCRIBE users");
$columns = [];
while($row = $res->fetch_assoc()) {
    $columns[] = $row;
}
echo json_encode($columns, JSON_PRETTY_PRINT);
?>
