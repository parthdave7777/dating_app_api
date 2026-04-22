<?php
/**
 * notifications/task_handler.php
 * Handles real-time tasks (Soketi + Push) directly.
 * Switch to SYNC delivery as per user request.
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/send_push.php';

function handleTaskDirectly(array $payload) {
    $type = $payload['action_type'] ?? 'unknown';

    if ($type === 'new_message') {
        $matchId     = $payload['match_id'];
        $recipientId = $payload['recipient_id'];
        $senderId    = $payload['sender_id'];
        $senderName  = $payload['sender_name'];
        $message     = $payload['message_text'];
        $msgType     = $payload['message_type'];
        $msgRow      = $payload['message_row'];

        // 1. Broadcast to Soketi (Real-time UI update)
        broadcastToSoketi("match_$matchId", "new_message", ['message' => $msgRow]);

        // 2. Send Push Notification
        $msgPreview = ($msgType === 'image') ? '📷 Photo' : $message;
        $db = getDB();
        sendPush($db, $recipientId, 'message', $senderName, $msgPreview, [
            'match_id'  => (string)$matchId,
            'sender_id' => (string)$senderId,
        ]);
        $db->close();

        // 3. Clear Cache
        $redis = getRedis();
        if ($redis) {
            $redis->del("user_chats_" . $senderId);
            $redis->del("user_chats_" . $recipientId);
        }
    } 
    else if ($type === 'messages_read') {
        broadcastToSoketi("match_" . $payload['match_id'], "messages_read", [
            'match_id'  => $payload['match_id'],
            'reader_id' => $payload['reader_id']
        ]);
    } 
    else if ($type === 'incoming_call') {
        $db = getDB();
        sendPush($db, $payload['recipient_id'], 'incoming_call', $payload['title'], $payload['body'], $payload['data']);
        $db->close();
    } 
    else if ($type === 'message_edited') {
        broadcastToSoketi("match_" . $payload['match_id'], "message_edited", [
            'message_id' => $payload['message_id'],
            'new_text'   => $payload['new_text'],
            'match_id'   => $payload['match_id']
        ]);
    }
}
