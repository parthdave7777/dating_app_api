<?php
// credits/setup_billing_tables.php
// Setup script for Google Play Billing tables.
// Run this once: https://yourapi.com/credits/setup_billing_tables.php?secret=YOUR_SETUP_SECRET

require_once __DIR__ . '/../config.php';

// Security check
$setupSecret = 'admin_setup_2026'; // Change this to whatever you want
if (($_GET['secret'] ?? '') !== $setupSecret) {
    die("Unauthorized.");
}

$db = getDB();

$queries = [
    "CREATE TABLE IF NOT EXISTS purchase_tokens (
        id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        user_id         INT NOT NULL,
        purchase_token  VARCHAR(500) NOT NULL,
        product_id      VARCHAR(100) NOT NULL,
        credits_granted INT NOT NULL DEFAULT 0,
        order_id        VARCHAR(200),
        purchase_state  TINYINT DEFAULT 0,
        acknowledged    TINYINT DEFAULT 0,
        raw_response    TEXT,
        created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uq_token (purchase_token(255)),
        KEY idx_user (user_id),
        KEY idx_ack (acknowledged)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;",

    "CREATE TABLE IF NOT EXISTS billing_errors (
        id           BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        user_id      INT,
        purchase_token VARCHAR(500),
        error_stage  VARCHAR(50),
        error_msg    TEXT,
        created_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;",

    // Add is_purchase to credit_logs if not exists
    "SET @dbname = DATABASE();
     SET @tablename = 'credit_logs';
     SET @columnname = 'is_purchase';
     SET @preparedStatement = (SELECT IF(
       (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = @tablename AND COLUMN_NAME = @columnname) > 0,
       'SELECT 1',
       'ALTER TABLE credit_logs ADD COLUMN is_purchase TINYINT(1) DEFAULT 0'
     ));
     PREPARE stmt FROM @preparedStatement;
     EXECUTE stmt;
     DEALLOCATE PREPARE stmt;"
];

foreach ($queries as $q) {
    if (!$db->query($q)) {
        echo "Error executing query: " . $db->error . "<br>";
    } else {
        echo "Success.<br>";
    }
}

$db->close();
echo "Setup complete.";
