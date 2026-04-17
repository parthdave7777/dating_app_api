<?php
// flush_redis.php
require_once __DIR__ . '/config.php';

if (($_GET['secret'] ?? '') !== 'legit') {
    die("Forbidden.");
}

$redis = getRedis();
if ($redis) {
    $redis->flushAll();
    echo "<h1 style='color:green;'>🚀 NITRO FLUSHED!</h1>";
    echo "<p>Redis memory has been wiped clean. Every profile will now be re-cached with the updated FCM token logic.</p>";
    echo "<p><a href='redis_check.php?secret=legit'>Go to Inspector</a></p>";
} else {
    echo "<h1 style='color:red;'>FAILED to connect to Redis.</h1>";
}
