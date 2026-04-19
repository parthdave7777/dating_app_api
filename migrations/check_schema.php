<?php
require_once __DIR__ . '/../config.php';
$db = getDB();
$res = $db->query("SHOW COLUMNS FROM users LIKE 'stealth_radius'");
if ($res->num_rows > 0) {
    echo "Column 'stealth_radius' exists.";
} else {
    echo "Column 'stealth_radius' DOES NOT EXIST.";
}
$db->close();
?>
