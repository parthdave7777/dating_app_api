<?php
// notifications/async_push_worker.php
// This is a CLI script designed to be run in the background.

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/send_push.php';

// Get JSON from command line argument
$rawPayload = $argv[1] ?? '{}';
$body = json_decode($rawPayload, true) ?? [];

$recipientId = (int)($body['recipient_id'] ?? 0);
$type        = $body['type'] ?? 'message';
$title       = $body['title'] ?? '';
$bodyText    = $body['body'] ?? '';
$data        = $body['data'] ?? [];

if ($recipientId > 0) {
    try {
        $db = getDB();
        sendPush($db, $recipientId, $type, $title, $bodyText, $data);
        $db->close();
    } catch (Exception $e) {
        error_log("[CLI WORKER] Error: " . $e->getMessage());
    }
}
exit();
