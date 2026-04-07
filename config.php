<?php
// ============================================================
//  DATING API — CORE CONFIGURATION
//  Production: Render (PHP) + Aiven (MySQL) + Cloudinary (Storage)
// ============================================================

// --- PRODUCTION CONFIGURATION ---
// Disable public errors to prevent them from breaking app JSON.
error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS, DELETE, PUT");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");

// --- ENVIRONMENT VARIABLES (.env) ---
if (file_exists(__DIR__ . '/vendor/autoload.php')) {
    require_once __DIR__ . '/vendor/autoload.php';
    if (class_exists('Dotenv\Dotenv')) {
        $dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
        $dotenv->safeLoad();
    }
}

// ─── ENVIRONMENT: set to 'local' for localhost, 'production' for live server ───
define('APP_ENV', getenv('APP_ENV') ?: 'local'); // Uses environment variable if set

if (APP_ENV === 'local') {
    // ── LOCAL XAMPP/WAMP/MAMP DATABASE ──────────────────────
    define('DB_HOST', '127.0.0.1');
    define('DB_PORT', '3306');
    define('DB_USER', 'root');        // default XAMPP/WAMP user
    define('DB_PASS', '');            // default XAMPP/WAMP password (empty)
    define('DB_NAME', 'dating_app');  // your local database name — create this in phpMyAdmin
    define('USE_SSL', false);

    // ── LOCAL STORAGE (save uploads to local disk) ──────────
    define('STORAGE_MODE', 'local');  // 'local' = disk, 'cloudinary' = cloud
    define('LOCAL_UPLOAD_PATH', __DIR__ . '/uploads/');
    define('LOCAL_UPLOAD_URL',  'http://localhost:8080/dating_api/uploads/');

    // ── JWT SECRET ───────────────────────────────────────────
    define('JWT_SECRET', 'local_dev_secret_min_32_chars_ok_here');

    // ── CLOUDINARY — disabled locally ────────────────────────
    define('CLOUDINARY_CLOUD_NAME', '');
    define('CLOUDINARY_API_KEY',    '');
    define('CLOUDINARY_API_SECRET', '');

    // ── FCM — still works locally IF you have the service account JSON
    // ── Leave the FCM_SERVICE_ACCOUNT_PATH as-is; FCM sends to real devices
    // ── even from localhost as long as you have internet access
    define('FCM_PROJECT_ID', 'legitdate-d69ce');

} else {
    // ── PRODUCTION (Aiven MySQL + Render) ───────────────────
    define('DB_HOST', getenv('DB_HOST') ?: '');
    define('DB_PORT', getenv('DB_PORT') ?: '3306');
    define('DB_USER', getenv('DB_USER') ?: '');
    define('DB_PASS', getenv('DB_PASS') ?: '');
    define('DB_NAME', getenv('DB_NAME') ?: '');
    define('USE_SSL', true);
    define('STORAGE_MODE', 'cloudinary');
    define('JWT_SECRET', getenv('JWT_SECRET') ?: 'CHANGE_THIS_TO_A_LONG_RANDOM_STRING_min32chars');
    define('CLOUDINARY_CLOUD_NAME', getenv('CLOUDINARY_CLOUD_NAME') ?: '');
    define('CLOUDINARY_API_KEY',    getenv('CLOUDINARY_API_KEY')    ?: '');
    define('CLOUDINARY_API_SECRET', getenv('CLOUDINARY_API_SECRET') ?: '');
    define('FCM_PROJECT_ID', 'legitdate-d69ce');
}

function getDB(): mysqli {
    try {
        mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
        $conn = mysqli_init();
        mysqli_options($conn, MYSQLI_OPT_CONNECT_TIMEOUT, 5);

        if (USE_SSL) {
            mysqli_options($conn, MYSQLI_OPT_SSL_VERIFY_SERVER_CERT, false);
            $success = mysqli_real_connect($conn, DB_HOST, DB_USER, DB_PASS, DB_NAME, (int)DB_PORT, NULL, MYSQLI_CLIENT_SSL);
        } else {
            $success = mysqli_real_connect($conn, DB_HOST, DB_USER, DB_PASS, DB_NAME, (int)DB_PORT);
        }

        if (!$success) throw new Exception("Connection failed: " . mysqli_connect_error());
        $conn->set_charset('utf8mb4');
        return $conn;
    } catch (Exception $e) {
        http_response_code(500);
        die(json_encode(['status' => 'error', 'message' => 'Database Error: ' . $e->getMessage()]));
    }
}

// ─── JWT ─────────────────────────────────────────────────────
function base64UrlEncode(string $data): string {
    return str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($data));
}

function base64UrlDecode(string $data): string {
    $remainder = strlen($data) % 4;
    if ($remainder) {
        $padlen = 4 - $remainder;
        $data .= str_repeat('=', $padlen);
    }
    return base64_decode(str_replace(['-', '_'], ['+', '/'], $data));
}

function generateToken(int $userId): string {
    $header    = base64UrlEncode(json_encode(['alg' => 'HS256', 'typ' => 'JWT']));
    $payload   = base64UrlEncode(json_encode([
        'user_id' => $userId,
        'iat'     => time(),
        'exp'     => time() + (60 * 60 * 24 * 30), // 30 days
    ]));
    $signature = base64UrlEncode(hash_hmac('sha256', "$header.$payload", JWT_SECRET, true));
    return "$header.$payload.$signature";
}

function verifyToken(string $token): ?int {
    $parts = explode('.', $token);
    if (count($parts) !== 3) return null;

    [$header, $payload, $sig] = $parts;
    $expectedSig = base64UrlEncode(hash_hmac('sha256', "$header.$payload", JWT_SECRET, true));

    if (!hash_equals($expectedSig, $sig)) return null;

    $data = json_decode(base64UrlDecode($payload), true);
    if (!$data || !isset($data['exp']) || $data['exp'] < time()) return null;
    return (int) $data['user_id'];
}

// ─── GET AUTHENTICATED USER ───────────────────────────────────
function getAuthUserId(): int {
    $authHeader = $_SERVER['HTTP_AUTHORIZATION']
               ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION']
               ?? '';
    
    // DEBUG AUTH: See if the phone is actually sending the token correctly
    if (isset($_SERVER['REQUEST_URI']) && strpos($_SERVER['REQUEST_URI'], 'update_token.php') !== false) {
        $cleanHeader = substr($authHeader, 0, 15);
        $headerLog = date('Y-m-d H:i:s') . " - DEBUG AUTH: Header Start: " . ($cleanHeader ?: "EMPTY") . "...\n";
        file_put_contents(__DIR__ . '/notifications/fcm_log.txt', $headerLog, FILE_APPEND);
    }

    if (preg_match('/Bearer\s+(.+)/i', $authHeader, $m)) {
        $userId = verifyToken(trim($m[1]));
        if ($userId) return $userId;
    }

    $body = json_decode(file_get_contents('php://input'), true) ?? [];
    if (!empty($body['token'])) {
        $userId = verifyToken($body['token']);
        if ($userId) return $userId;
    }

    if (!empty($_GET['token'])) {
        $userId = verifyToken($_GET['token']);
        if ($userId) return $userId;
    }

    http_response_code(401);
    die(json_encode(['status' => 'error', 'message' => 'Unauthorized']));
}

// ─── CLOUDINARY ───────────────────────────────────────────────

function cloudinaryUpload(string $filePath, string $folder, string $publicId = '', string $resourceType = 'image'): ?string {
    if (STORAGE_MODE === 'local') {
        // Save to local uploads folder and return a local URL
        $ext      = pathinfo($filePath, PATHINFO_EXTENSION) ?: 'jpg';
        $fileName = ($publicId ?: uniqid('upload_', true)) . '.' . $ext;
        $destDir  = LOCAL_UPLOAD_PATH . $folder . '/';
        if (!is_dir($destDir)) mkdir($destDir, 0755, true);
        $destPath = $destDir . $fileName;
        copy($filePath, $destPath);
        return LOCAL_UPLOAD_URL . $folder . '/' . $fileName;
    }
    $timestamp = time();
    $params = ['folder' => $folder, 'timestamp' => $timestamp];
    if ($publicId !== '') $params['public_id'] = $publicId;
    ksort($params);
    $sigString = '';
    foreach ($params as $k => $v) $sigString .= ($sigString ? '&' : '') . "$k=$v";
    $signature = sha1($sigString . CLOUDINARY_API_SECRET);
    $postFields = array_merge($params, ['api_key' => CLOUDINARY_API_KEY, 'signature' => $signature, 'file' => new CURLFile($filePath)]);
    $url = 'https://api.cloudinary.com/v1_1/' . CLOUDINARY_CLOUD_NAME . '/' . $resourceType . '/upload';
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_POST, true); curl_setopt($ch, CURLOPT_POSTFIELDS, $postFields); curl_setopt($ch, CURLOPT_RETURNTRANSFER, true); curl_setopt($ch, CURLOPT_TIMEOUT, 60);
    $response = curl_exec($ch); curl_close($ch);
    $data = json_decode($response, true);
    return $data['secure_url'] ?? null;
}

function cloudinaryDelete(string $cloudinaryUrl, string $resourceType = 'image'): bool {
    $pattern = '#/upload/(?:v\d+/)?(.+?)(?:\.[a-z0-9]+)?$#i';
    if (!preg_match($pattern, $cloudinaryUrl, $m)) return false;
    $publicId = $m[1]; $timestamp = time();
    $signature = sha1("public_id={$publicId}&timestamp={$timestamp}" . CLOUDINARY_API_SECRET);
    $ch = curl_init('https://api.cloudinary.com/v1_1/' . CLOUDINARY_CLOUD_NAME . '/' . $resourceType . '/destroy');
    curl_setopt($ch, CURLOPT_POST, true); curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query(['public_id' => $publicId, 'timestamp' => $timestamp, 'api_key' => CLOUDINARY_API_KEY, 'signature' => $signature]));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true); $res = curl_exec($ch); curl_close($ch);
    return json_decode($res, true)['result'] === 'ok';
}

// ─── CLOUDINARY TRANSFORMATIONS ───────────────────────────────
/**
 * Injects transformations into a Cloudinary URL.
 * Example: q_auto,f_auto,w_500
 */
function cloudinaryTransform(string $url, string $transformation = 'q_auto,f_auto'): string {
    if (STORAGE_MODE === 'local') return $url; // no transforms on local files
    if (empty($url) || strpos($url, 'cloudinary.com') === false) return $url;
    
    // Pattern to find /upload/v12345/ or /upload/
    $pattern = '/(\/upload\/)(v[0-9]+\/)?/';
    $replacement = '$1' . $transformation . '/$2';
    
    $newUrl = preg_replace($pattern, $replacement, $url);
    return $newUrl ?: $url;
}

// ─── LEGACY UPLOAD CONSTANTS (no-ops for compatibility) ────────
define('UPLOAD_DIR', sys_get_temp_dir() . '/');
define('UPLOAD_URL', 'https://res.cloudinary.com/' . CLOUDINARY_CLOUD_NAME . '/image/upload/');
?>