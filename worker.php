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
        // BLPOP blocks for 20 seconds waiting for a new task
        // This is much more efficient than polling.
        $taskData = $redis->blPop(['nitro_tasks'], 20);

        if ($taskData) {
            // $taskData[0] is the key name, $taskData[1] is the value
            $payload = json_decode($taskData[1], true);
            
            if ($payload) {
                echo "[" . date('H:i:s') . "] Processing task: " . ($payload['action_type'] ?? 'unknown') . "\n";
                
                // Keep DB alive
                if (time() - $lastPing > 30) {
                    if (!@mysqli_ping($db)) {
                        $db = getDB();
                    }
                    $lastPing = time();
                }

                handleTaskDirectly($payload);
            }
        }
    } catch (Throwable $e) {
        echo "Worker Exception: " . $e->getMessage() . "\n";
        sleep(2); // Prevent rapid looping on error
        $db = getDB(); // Recurrent DB
    }
}
