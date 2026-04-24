<?php
// notifications/pusher_config.php

require_once __DIR__ . '/../vendor/autoload.php';

// USE YOUR RAILWAY SOKETI DOMAIN HERE (Example: 'soketi-production.up.railway.app')
define('SOKETI_HOST', getenv('SOKETI_HOST') ?: 'soketi-production-f741.up.railway.app'); 
define('SOKETI_PORT', getenv('SOKETI_PORT') ? (int)getenv('SOKETI_PORT') : 443); 
define('SOKETI_APP_ID', getenv('SOKETI_APP_ID') ?: 'legitdate-app');
define('SOKETI_APP_KEY', getenv('SOKETI_APP_KEY') ?: 'legit-key-123');
define('SOKETI_APP_SECRET', getenv('SOKETI_APP_SECRET') ?: 'legit-secret-456');

function getPusher() {
    $options = [
        'host' => SOKETI_HOST,
        'port' => SOKETI_PORT,
        'scheme' => 'https',
        'encrypted' => true,
        'useTLS' => true,
        'cluster' => 'mt1', // Soketi doesn't care about cluster, but the library needs it
    ];

    return new Pusher\Pusher(
        SOKETI_APP_KEY,
        SOKETI_APP_SECRET,
        SOKETI_APP_ID,
        $options
    );
}

/**
 * Broadcasts an event to a specific channel.
 * Example: broadcastToSoketi('chat_12', 'new_message', ['text' => 'Hello']);
 */
function broadcastToSoketi($channel, $event, $data) {
    try {
        $pusher = getPusher();
        $pusher->trigger($channel, $event, $data);
    } catch (Exception $e) {
        error_log("[SOKETI] Error: " . $e->getMessage());
    }
}
