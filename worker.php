<?php
/**
 * worker.php
 * High-performance background worker for Railway.
 * Processes the 'nitro_tasks' queue from Redis.
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/notifications/task_handler.php';

echo "Nitro Worker started... Listening for tasks.\n";

$redis = getRedis();
if (!$redis) {
    die("Worker Error: Could not connect to Redis. Background tasks will not work.\n");
}

// Ensure database connection stays alive
$db = getDB();

$lastPing = time();

while (true) {
    try {
        $redis = getRedis();
        if (!$redis) {
            echo "[" . date('H:i:s') . "] Redis offline. Retrying in 5s...\n";
            sleep(5);
            continue;
        }

        // BLPOP blocks for 20 seconds waiting for a new task
        $taskData = $redis->blPop(['nitro_tasks'], 20);

        if ($taskData && isset($taskData[1])) {
            $payload = json_decode($taskData[1], true);
            
            if ($payload) {
                $action = $payload['action_type'] ?? 'unknown';
                echo "[" . date('H:i:s') . "] Task: $action\n";
                
                // Keep DB alive
                if (time() - $lastPing > 30) {
                    if (!@mysqli_ping($db)) {
                        echo "[" . date('H:i:s') . "] DB Ping failed. Reconnecting...\n";
                        $db = getDB();
                    }
                    $lastPing = time();
                }

                handleTaskDirectly($payload);
            }
        }
    } catch (Throwable $e) {
        echo "[" . date('H:i:s') . "] Worker Exception: " . $e->getMessage() . "\n";
        sleep(2);
    }
}
