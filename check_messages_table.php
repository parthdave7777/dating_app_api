<?php
require_once 'config.php';
$db = getDB();
$result = $db->query("DESCRIBE messages");
$columns = [];
while ($row = $result->fetch_assoc()) {
    $columns[] = $row['Field'];
}
echo json_encode($columns);
?>
