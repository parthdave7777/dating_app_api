<?php
/**
 * scripts/worker.php
 * LONG RUNNING REDIS WORKER
 * 
 * Usage: php scripts/worker.php
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../notifications/send_push.php';

// NITRO STARTUP: Wait for Redis (crucial for Railway/Production)
$redis = null;
$retries = 0;
while ($redis === null && $retries < 10) {
    echo "[WORKER] Attempting to connect to Redis... (Attempt " . ($retries + 1) . ")\n";
    $redis = getRedis();
    if (!$redis) {
        $retries++;
        sleep(2);
    }
}

if (!$redis) {
    die("ERROR: Redis is required for the worker system. Please ensure your Redis service is running.\n");
}

echo "[WORKER] Started at " . date('Y-m-d H:i:s') . "\n";
echo "[WORKER] Monitoring queue 'task_queue'...\n";

while (true) {
    try {
        // block for 20 seconds waiting for data
        $result = $redis->blPop('task_queue', 20);
        
        if (!$result) {
            // Check if we should exit (optional) or just keep looping
            continue;
        }

        $payloadJson = $result[1];
        $payload = json_decode($payloadJson, true);
        
        if (!$payload) {
            echo "[WORKER] Invalid JSON payload received.\n";
            continue;
        }

        $type = $payload['action_type'] ?? 'unknown';
        echo "[WORKER] Processing: $type\n";

        if ($type === 'new_message') {
            processNewMessage($payload);
        } else if ($type === 'messages_read') {
            processMessagesRead($payload);
        } else if ($type === 'incoming_call') {
            processIncomingCall($payload);
        } else if ($type === 'message_edited') {
            processMessageEdited($payload);
        }

    } catch (Exception $e) {
        echo "[WORKER] Exception: " . $e->getMessage() . "\n";
        sleep(2); // Wait a bit before retry
    }
}

function processNewMessage($payload) {
    $matchId     = $payload['match_id'];
    $recipientId = $payload['recipient_id'];
    $senderId    = $payload['sender_id'];
    $senderName  = $payload['sender_name'];
    $message     = $payload['message_text'];
    $msgType     = $payload['message_type'];
    $msgRow      = $payload['message_row'];

    // 1. Broadcast to Soketi
    broadcastToSoketi("match_$matchId", "new_message", ['message' => $msgRow]);

    // 2. Send Push Notification
    $msgPreview = ($msgType === 'image') ? '📷 Photo' : $message;
    $db = getDB();
    sendPush($db, $recipientId, 'message', $senderName, $msgPreview, [
        'match_id'  => (string)$matchId,
        'sender_id' => (string)$senderId,
    ]);
    $db->close();

    // 3. Clear Chat List Cache (NITRO) so listing updates
    $redis = getRedis();
    if ($redis) {
        $redis->del("user_chats_" . $senderId);
        $redis->del("user_chats_" . $recipientId);
    }
    
    if ($pushRes) {
        echo "[WORKER] SUCCESS: Push sent to user $recipientId\n";
    } else {
        echo "[WORKER] FAILED: Push could not be sent to user $recipientId. Check send_push logs.\n";
    }
}

function processMessagesRead($payload) {
    broadcastToSoketi("match_" . $payload['match_id'], "messages_read", [
        'match_id'  => $payload['match_id'],
        'reader_id' => $payload['reader_id']
    ]);
}

function processIncomingCall($payload) {
    $db = getDB();
    sendPush($db, $payload['recipient_id'], 'incoming_call', $payload['title'], $payload['body'], $payload['data']);
    $db->close();
}

function processMessageEdited($payload) {
    broadcastToSoketi("match_" . $payload['match_id'], "message_edited", [
        'message_id' => $payload['message_id'],
        'new_text'   => $payload['new_text'],
        'match_id'   => $payload['match_id']
    ]);
}
