<?php
require_once __DIR__ . '/../config.php';
$db = getDB();

$sql = "CREATE TABLE IF NOT EXISTS profile_views (
    id INT AUTO_INCREMENT PRIMARY KEY,
    viewer_id INT NOT NULL,
    viewed_id INT NOT NULL,
    viewed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY viewer_viewed (viewer_id, viewed_id),
    FOREIGN KEY (viewer_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (viewed_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";

if ($db->query($sql)) {
    echo "Table profile_views created/verified successfully.\n";
} else {
    echo "Error creating table: " . $db->error . "\n";
}
