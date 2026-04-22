<?php
// notifications/async_worker.php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/send_push.php';

/**
 * BACKGROUND WORKER
 * This script is triggered asynchronously to handle external HTTP calls
 * without slowing down the main API response.
 */

// We expect data via CLI arguments
if ($argc < 2) exit();

$payload = json_decode($argv[1], true);
if (!$payload) exit();

$logFile = __DIR__ . '/../worker.log';
function workerLog($msg) {
    global $logFile;
    file_put_contents($logFile, "[" . date('Y-m-d H:i:s') . "] $msg\n", FILE_APPEND);
}

workerLog("Worker started for action: " . ($payload['action_type'] ?? 'unknown'));

$type = $payload['action_type'] ?? '';

if ($type === 'new_message') {
    $matchId     = $payload['match_id'];
    $recipientId = $payload['recipient_id'];
    $senderId    = $payload['sender_id'];
    $senderName  = $payload['sender_name'];
    $message     = $payload['message_text'];
    $msgType     = $payload['message_type'];
    $msgRow      = $payload['message_row'];

    // 1. Broadcast to Soketi (Background)
    $res = broadcastToSoketi("match_$matchId", "new_message", [
        'message' => $msgRow
    ]);
    workerLog("Soketi Broadcast " . ($res ? "SUCCESS" : "FAILED") . " for match $matchId");

    // 2. Send Push Notification (Background)
    $msgPreview = ($msgType === 'image') ? '📷 Photo' : $message;
    
    $db = getDB();
    $pushRes = sendPush($db, $recipientId, 'message', $senderName, $msgPreview, [
        'match_id'  => (string)$matchId,
        'sender_id' => (string)$senderId,
    ]);
    workerLog("FCM Push " . ($pushRes ? "SENT" : "FAILED") . " to user $recipientId");
    $db->close();
}
else if ($type === 'messages_read') {
    $matchId  = $payload['match_id'];
    $readerId = $payload['reader_id'];

    $res = broadcastToSoketi("match_$matchId", "messages_read", [
        'match_id'  => $matchId,
        'reader_id' => $readerId
    ]);
    workerLog("Read Broadcast " . ($res ? "SUCCESS" : "FAILED") . " for match $matchId");
}
else if ($type === 'incoming_call') {
    $recipientId = $payload['recipient_id'];
    $title       = $payload['title'];
    $body        = $payload['body'];
    $data        = $payload['data'];

    $db = getDB();
    $pushRes = sendPush($db, $recipientId, 'incoming_call', $title, $body, $data);
    workerLog("FCM Call Push " . ($pushRes ? "SENT" : "FAILED") . " to user $recipientId");
    $db->close();
}
else if ($type === 'message_edited') {
    $matchId   = $payload['match_id'];
    $messageId = $payload['message_id'];
    $newText   = $payload['new_text'];

    $res = broadcastToSoketi("match_$matchId", "message_edited", [
        'message_id' => $messageId,
        'new_text'   => $newText,
        'match_id'   => $matchId
    ]);
    workerLog("Edit Broadcast " . ($res ? "SUCCESS" : "FAILED") . " for match $matchId");
}
