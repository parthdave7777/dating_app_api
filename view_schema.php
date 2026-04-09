<?php
require_once __DIR__ . '/config.php';
$db = getDB();

echo "--- CORE DATABASE SCHEMA ---\n\n";

$tables = ['users', 'user_photos', 'matches', 'messages', 'swipes', 'notifications', 'call_logs'];

foreach ($tables as $table) {
    echo "[$table]\n";
    $res = $db->query("DESCRIBE $table");
    if ($res) {
        while($row = $res->fetch_assoc()) {
            echo "  - " . $row['Field'] . " (" . $row['Type'] . ")";
            if ($row['Key'] == 'PRI') echo " [PRIMARY KEY]";
            echo "\n";
        }
    } else {
        echo "  (Table not found)\n";
    }
    echo "\n";
}

$db->close();
?>
