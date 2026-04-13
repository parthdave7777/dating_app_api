<?php
require_once __DIR__ . '/config.php';
$db = getDB();
$result = $db->query("DESCRIBE users");
$columns = [];
while ($row = $result->fetch_assoc()) {
    $columns[] = $row['Field'];
}
echo json_encode(['status' => 'success', 'columns' => $columns]);
?>
