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
    $redis = getRedis();
    $logEntry = [
        'time' => date('H:i:s'),
        'type' => $type,
        'status' => 'success'
    ];

    try {
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
            $pushResult = sendPush(getDB(), $recipientId, 'message', $senderName, $msgPreview, [
                'match_id'  => (string)$matchId,
                'sender_id' => (string)$senderId,
            ]);
            
            error_log("[DEBUG_ASYNC] Push Result for User $recipientId: " . ($pushResult ? "SUCCESS" : "FAILED"));

            // 3. Clear Cache
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
            sendPush(getDB(), $payload['recipient_id'], 'incoming_call', $payload['title'], $payload['body'], $payload['data']);
        } 
        else if ($type === 'message_edited') {
            broadcastToSoketi("match_" . $payload['match_id'], "message_edited", [
                'message_id' => $payload['message_id'],
                'new_text'   => $payload['new_text'],
                'match_id'   => $payload['match_id']
            ]);
        }
        else if ($type === 'message_deleted') {
            broadcastToSoketi("match_" . $payload['match_id'], "message_deleted", [
                'message_id' => $payload['message_id'],
                'match_id'   => $payload['match_id'],
            ]);
        }
        else if ($type === 'media_opened') {
            broadcastToSoketi("match_" . $payload['match_id'], "media_opened", [
                'message_id' => $payload['message_id'],
                'match_id'   => $payload['match_id'],
            ]);
        }
        else if ($type === 'messages_received') {
            broadcastToSoketi("match_" . $payload['match_id'], "messages_received", [
                'match_id'  => $payload['match_id'],
                'user_id'   => $payload['user_id']
            ]);
        }
        else if ($type === 'acknowledge_purchase') {
            require_once __DIR__ . '/../credits/acknowledge.php';
            acknowledgeGooglePurchase(
                getDB(), 
                $payload['package_name'], 
                $payload['product_id'], 
                $payload['purchase_token'], 
                $payload['user_id']
            );
        }
        else if ($type === 'profile_view') {
            sendPush(getDB(), $payload['recipient_id'], 'profile_view', $payload['title'], $payload['body'], $payload['data']);
        }
    } catch (Throwable $e) {
        $logEntry['status'] = 'FAILED: ' . $e->getMessage();
    }

    if ($redis) {
        $redis->lPush('worker_logs', json_encode($logEntry));
        $redis->lTrim('worker_logs', 0, 9); // Keep last 10 logs
    }
}
