<?php
// notifications/update_token.php — save FCM token for the device
require_once __DIR__ . '/../config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'Method not allowed']);
    exit();
}

$body     = json_decode(file_get_contents('php://input'), true);
$fcmToken = trim($body['fcm_token'] ?? '');

// 1. Log Attempt (Always)
$rawToken = substr($fcmToken, 0, 15);
$logEntry = date('Y-m-d H:i:s') . " - Attempting sync - Token Start: $rawToken...\n";
@file_put_contents(__DIR__ . '/fcm_log.txt', $logEntry, FILE_APPEND);

// 2. Auth Check
$userId   = getAuthUserId();

// 3. Log SUCCESS (Only if auth passes)
$successEntry = date('Y-m-d H:i:s') . " - SUCCESS - UserID: $userId - Token: " . substr($fcmToken, 0, 20) . "...\n";
@file_put_contents(__DIR__ . '/fcm_log.txt', $successEntry, FILE_APPEND);

if (empty($fcmToken)) {
    echo json_encode(['status' => 'error', 'message' => 'fcm_token required']);
    exit();
}

$db   = getDB();
$stmt = $db->prepare("UPDATE users SET fcm_token = ? WHERE id = ?");
$stmt->bind_param('si', $fcmToken, $userId);
$stmt->execute();
$stmt->close();
$db->close();

echo json_encode(['status' => 'success']);
