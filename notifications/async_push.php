<?php
// notifications/async_push.php
// This is an internal script called asynchronously to avoid blocking the user.

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/send_push.php';

// Only allow internal "ping" or verify a simple secret if you want extra security
$body = json_decode(file_get_contents('php://input'), true) ?? [];

$recipientId = (int)($body['recipient_id'] ?? 0);
$type        = $body['type'] ?? 'message';
$title       = $body['title'] ?? '';
$bodyText    = $body['body'] ?? '';
$data        = $body['data'] ?? [];

if ($recipientId > 0) {
    $db = getDB();
    sendPush($db, $recipientId, $type, $title, $bodyText, $data);
    $db->close();
}
exit();
