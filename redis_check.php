<?php
// redis_check.php — NITRO INSPECTOR
require_once __DIR__ . '/config.php';

// SIMPLE SECURITY: Only allow viewing if ?secret=legit is in the URL
if (($_GET['secret'] ?? '') !== 'legit') {
    die("FORBIDDEN: Access Denied. Use ?secret=legit to view.");
}

$redis = getRedis();

echo "<html><head><title>Nitro Redis Inspector</title>";
echo "<style>body{font-family:sans-serif;background:#121212;color:#eee;padding:20px;} pre{background:#222;padding:10px;border-radius:5px;overflow:auto;} .key{color:#00ff00;font-weight:bold;text-decoration:none;} .info{color:#aaa;}</style>";
echo "</head><body>";

echo "<h1>🚀 Nitro Redis Inspector</h1>";

if (!$redis) {
    echo "<p style='color:red;'>REDIS CONNECTION FAILED! Check your Railway variables.</p>";
    exit();
}

$info = $redis->info();
echo "<h3>System Info:</h3>";
echo "<ul>";
echo "<li><b>Redis Version:</b> {$info['redis_version']}</li>";
echo "<li><b>Memory Used:</b> {$info['used_memory_human']}</li>";
echo "<li><b>Total Keys:</b> " . $redis->dbSize() . "</li>";
echo "</ul>";

$keys = $redis->keys('*');
echo "<h3>🔑 All Keys:</h3>";

if (empty($keys)) {
    echo "<p>No keys in cache yet. Go swipe or view a profile!</p>";
} else {
    echo "<ul>";
    foreach ($keys as $key) {
        $ttl = $redis->ttl($key);
        echo "<li><a href='?secret=legit&view=$key' class='key'>$key</a> <span class='info'>(TTL: {$ttl}s)</span></li>";
    }
    echo "</ul>";
}

if (isset($_GET['view'])) {
    $viewKey = $_GET['view'];
    echo "<h3>📜 Content of: $viewKey</h3>";
    $value = $redis->get($viewKey);
    if ($value) {
        $decoded = json_decode($value, true);
        echo "<pre>";
        print_r($decoded ?: $value);
        echo "</pre>";
    } else {
        echo "<p>Key not found or expired.</p>";
    }
}

echo "</body></html>";
