<?php
require_once __DIR__ . '/../config.php';
$db = getDB();

// DANGEROUS: Wipe for fresh testing
if (isset($_GET['fresh'])) {
    $db->query("TRUNCATE TABLE profile_views");
    $db->query("DELETE FROM credit_logs");
    $db->query("UPDATE users SET credits = 500, premium_credits = 0");
    echo "CLEAN SLATE: Views wiped. Users reset to 500 credits.\n";
}

$sql = "CREATE TABLE IF NOT EXISTS profile_views (
    id INT AUTO_INCREMENT PRIMARY KEY,
    viewer_id INT NOT NULL,
    viewed_id INT NOT NULL,
    viewed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY viewer_viewed (viewer_id, viewed_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";

if ($db->query($sql)) {
    echo "Table profile_views verified.\n";
} else {
    echo "Error: " . $db->error . "\n";
}

// Check current views
$res = $db->query("SELECT COUNT(*) as cnt FROM profile_views");
$row = $res->fetch_assoc();
echo "Current total views recorded: " . $row['cnt'] . "\n";
