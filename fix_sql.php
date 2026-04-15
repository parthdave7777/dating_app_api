<?php
// dating_api/fix_sql.php
require_once __DIR__ . '/config.php';
$db = getDB();

$sql = "ALTER TABLE swipes MODIFY COLUMN action ENUM('like', 'dislike', 'superlike', 'compliment') NOT NULL";

if ($db->query($sql)) {
    echo json_encode(['status' => 'success', 'message' => 'Swipes table updated successfully to support compliments!']);
} else {
    echo json_encode(['status' => 'error', 'message' => 'Error updating table: ' . $db->error]);
}
$db->close();
?>
