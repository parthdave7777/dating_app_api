<?php
// config.php
header("Content-Type: application/json");
ini_set('display_errors', 0);
error_reporting(0);

// --- SMART DATABASE DETECTION ---
// If we are on Railway, use the Railway host. Otherwise, use localhost.
$db_host = getenv('MYSQL_URL') ? 'mysql.railway.internal' : 'localhost';
if (getenv('MYSQLHOST')) $db_host = getenv('MYSQLHOST');

define('DB_HOST', $db_host);
define('DB_USER', getenv('MYSQLUSER') ?: 'root');
define('DB_PASS', getenv('MYSQLPASSWORD') ?: '');
define('DB_NAME', getenv('MYSQLDATABASE') ?: 'dating_app');

function getDB(): mysqli {
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    if ($conn->connect_error) {
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => 'Database connection failed']);
        exit();
    }
    $conn->set_charset('utf8mb4');
    return $conn;
}

// --- PUSHER / SOKETI CONFIG ---
define('SOKETI_HOST',   'soketi-production-3817.up.railway.app');
define('SOKETI_APP_ID', 'legitdate-app');
define('SOKETI_KEY',    'legit-key-123');
define('SOKETI_SECRET', 'legit-secret-456');

/**
 * ULTRA-FAST BROADCASTER (1 Second Timeout)
 */
function broadcastToSoketi(string $channel, string $event, array $data): bool {
    $path = "/apps/" . SOKETI_APP_ID . "/events";
    $body = json_encode(['name' => $event, 'channels' => [$channel], 'data' => json_encode($data)]);
    $auth_timestamp = time();
    $auth_query = "auth_key=" . SOKETI_KEY . "&auth_timestamp=$auth_timestamp&auth_version=1.0&body_md5=" . md5($body);
    $auth_signature = hash_hmac('sha256', "POST\n$path\n$auth_query", SOKETI_SECRET);
    $url = "https://" . SOKETI_HOST . "$path?$auth_query&auth_signature=$auth_signature";

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    
    // THE 1-SECOND RULES: Don't wait for more than 1 second!
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 1); 
    curl_setopt($ch, CURLOPT_TIMEOUT, 1);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return ($httpCode === 200);
}

// --- RE-IMPLEMENTING NECESSARY AUTH HELPERS ---
define('JWT_SECRET', 'CHANGE_THIS_TO_A_LONG_RANDOM_STRING_min32chars');

function verifyToken(string $token): ?int {
    $parts = explode('.', $token);
    if (count($parts) !== 3) return null;
    $payload = json_decode(base64_decode(str_replace(['-', '_'], ['+', '/'], $parts[1])), true);
    if (!$payload || !isset($payload['exp']) || $payload['exp'] < time()) return null;
    return (int) $payload['user_id'];
}

function getAuthUserId(): int {
    $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
    if (preg_match('/Bearer\s+(.+)/i', $authHeader, $m)) {
        $userId = verifyToken($m[1]);
        if ($userId) return $userId;
    }
    http_response_code(401);
    exit(json_encode(['status' => 'error', 'message' => 'Unauthorized']));
}

function getUserName($userId) {
    $db = getDB();
    $res = $db->query("SELECT full_name FROM users WHERE id = $userId");
    $name = $res->fetch_assoc()['full_name'] ?? 'User';
    $db->close();
    return $name;
}