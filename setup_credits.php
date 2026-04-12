<?php
// setup_credits.php
require_once __DIR__ . '/config.php';
$db = getDB();

echo "Setting up credit system...\n";

$queries = [
    "ALTER TABLE users ADD COLUMN credits INT DEFAULT 50",
    "ALTER TABLE users ADD COLUMN last_credit_refresh TIMESTAMP DEFAULT CURRENT_TIMESTAMP",
    "CREATE TABLE IF NOT EXISTS credit_logs (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        amount INT NOT NULL,
        reason VARCHAR(255),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    )",
    "CREATE TABLE IF NOT EXISTS unlocked_profiles (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        target_id INT NOT NULL,
        type VARCHAR(50),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY unq_unlock (user_id, target_id, type)
    )"
];

foreach ($queries as $q) {
    try {
        if ($db->query($q)) {
            echo "Success: $q\n";
        } else {
            echo "Failed: " . $db->error . "\n";
        }
    } catch (Exception $e) {
        echo "Error: " . $e->getMessage() . "\n";
    }
}

$db->close();
echo "Done.\n";
