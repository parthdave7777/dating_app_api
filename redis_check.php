<?php
// redis_check.php — NITRO INSPECTOR (DEBUG VERSION)
require_once __DIR__ . '/config.php';

if (($_GET['secret'] ?? '') !== 'legit') {
    die("FORBIDDEN: Access Denied.");
}

echo "<html><head><title>Nitro Redis Inspector</title>";
echo "<style>body{font-family:sans-serif;background:#121212;color:#eee;padding:20px;} pre{background:#222;padding:10px;border-radius:5px;overflow:auto;} .key{color:#00ff00;font-weight:bold;text-decoration:none;} .info{color:#aaa;}</style>";
echo "</head><body>";

echo "<h1>🚀 Nitro Redis Inspector</h1>";

// DEBUG: What variables does the server see?
echo "<h3>🔍 Debug Environment:</h3>";
echo "<ul>";
echo "<li><b>ENV REDISHOST:</b> " . (getenv('REDISHOST') ?: '<span style="color:red;">NOT SET</span>') . "</li>";
echo "<li><b>ENV REDISPORT:</b> " . (getenv('REDISPORT') ?: '<span style="color:red;">NOT SET</span>') . "</li>";
echo "<li><b>CONST REDIS_HOST:</b> " . REDIS_HOST . "</li>";
echo "</ul>";

$redis = getRedis();

if (!$redis) {
    echo "<p style='color:red;'>REDIS CONNECTION FAILED!</p>";
    echo "<p><b>Possible issues:</b><br>";
    echo "1. Railway variables haven't updated yet (Wait 1 minute).<br>";
    echo "2. Redis service doesn't have a 'Private Domain' enabled.<br>";
    echo "3. Password is incorrect.</p>";
    exit();
}

$info = $redis->info();
echo "<h3>✅ Connected Successfully!</h3>";
echo "<ul>";
echo "<li><b>Used Memory:</b> {$info['used_memory_human']}</li>";
echo "</ul>";

$keys = $redis->keys('*');
echo "<h3>🔑 All Keys:</h3>";
if (empty($keys)) {
    echo "<p>No keys yet.</p>";
} else {
    foreach ($keys as $key) {
        echo "<li>$key</li>";
    }
}
echo "</body></html>";
