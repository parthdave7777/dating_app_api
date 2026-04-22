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

// --- CLOUD SECURITY HEADERS ---
header("X-Content-Type-Options: nosniff");
header("X-Frame-Options: SAMEORIGIN");
header("X-XSS-Protection: 1; mode=block");
header("Referrer-Policy: strict-origin-when-cross-origin");

// Set local timezone for all PHP operations
date_default_timezone_set('Asia/Kolkata');

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
    
    // ── AGORA VIDEO CALLS ────────────────────────────────────
    define('AGORA_APP_ID',   '093e7f655f564c7ca14acbcdce68f390');
    define('AGORA_APP_CERT', '11c4d01441fd4c19aacd5bf2f867e945');

    // ── RAZORPAY ────────────────────────────────────────────
    define('RAZORPAY_KEY',    'rzp_test_ScveM5B4eK7mQL');
    define('RAZORPAY_SECRET', 'y2omoCZQbK3o90BSWRCvanFL');
    
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
    define('FCM_PROJECT_ID', getenv('FCM_PROJECT_ID') ?: '');

    // ── AGORA VIDEO CALLS ────────────────────────────────────
    define('AGORA_APP_ID',   getenv('AGORA_APP_ID')   ?: '093e7f655f564c7ca14acbcdce68f390');
    define('AGORA_APP_CERT', getenv('AGORA_APP_CERT') ?: '11c4d01441fd4c19aacd5bf2f867e945');

    // ── RAZORPAY ────────────────────────────────────────────
    define('RAZORPAY_KEY',    getenv('RAZORPAY_KEY')    ?: 'rzp_test_ScveM5B4eK7mQL');
    define('RAZORPAY_SECRET', getenv('RAZORPAY_SECRET') ?: 'y2omoCZQbK3o90BSWRCvanFL');
}

function getDB(): mysqli {
    try {
        mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
        $conn = mysqli_init();
        mysqli_options($conn, MYSQLI_OPT_CONNECT_TIMEOUT, 5);

        if (USE_SSL) {
            mysqli_options($conn, MYSQLI_OPT_SSL_VERIFY_SERVER_CERT, false);
        // OPTIMIZATION: Only use SSL for public/external database connections.
        // Internal railway connections (.internal or localhost) are safe and much faster without SSL.
        $isInternal = (strpos(DB_HOST, '.internal') !== false || DB_HOST === 'localhost' || DB_HOST === '127.0.0.1');
        $flags = $isInternal ? 0 : MYSQLI_CLIENT_SSL;
        
        try {
            $success = mysqli_real_connect($conn, DB_HOST, DB_USER, DB_PASS, DB_NAME, (int)DB_PORT, NULL, $flags);
            if (!$success) {
                // Fallback attempt without SSL if SSL failed
                $success = mysqli_real_connect($conn, DB_HOST, DB_USER, DB_PASS, DB_NAME, (int)DB_PORT, NULL, 0);
            }
        } catch (Exception $e) {
            // Final fallback
            @mysqli_real_connect($conn, DB_HOST, DB_USER, DB_PASS, DB_NAME, (int)DB_PORT, NULL, 0);
        }
        } else {
            $success = mysqli_real_connect($conn, DB_HOST, DB_USER, DB_PASS, DB_NAME, (int)DB_PORT);
        }

        if (!$success) throw new Exception("Connection failed: " . mysqli_connect_error());
        $conn->set_charset('utf8mb4');
        @$conn->query("SET time_zone = '+05:30'"); // Silent fallback if host blocks this
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

// ─── REDIS CONFIGURATION (FAST CACHE) ────────────────────────
define('REDIS_HOST', getenv('REDISHOST') ?: '127.0.0.1');
define('REDIS_PORT', getenv('REDISPORT') ?: '6379');
define('REDIS_PASS', getenv('REDISPASSWORD') ?: '');

/**
 * Returns a connected Redis instance or null if connection fails.
 * Fails gracefully to prevent app crashes if Redis is down.
 */
function getRedis(): ?Redis {
    static $redis = null;
    if ($redis !== null) return $redis;

    if (!class_exists('Redis')) return null;

    try {
        $instance = new Redis();
        // Use a short timeout so we don't hang if Redis is slow
        $connected = @$instance->connect(REDIS_HOST, (int)REDIS_PORT, 1.5);
        
        if ($connected && !empty(REDIS_PASS)) {
            $instance->auth(REDIS_PASS);
        }

        if ($connected) {
            $redis = $instance;
            return $redis;
        }
    } catch (Exception $e) {
        error_log("[REDIS] Connection Error: " . $e->getMessage());
    }
    return null;
}

/**
 * GLOBAL RATE LIMITER (Redis-Backed)
 * @param string $key Unique key for the action (e.g., 'login_1.2.3.4')
 * @param int $limit Max attempts
 * @param int $seconds Time window
 * @return bool True if allowed, false if blocked
 */
function checkRateLimit(string $key, int $limit, int $seconds): bool {
    $redis = getRedis();
    if (!$redis) return true; // Fallback to allow if Redis is down (high availability)

    $current = $redis->get("rl:$key");
    if ($current !== false && (int)$current >= $limit) return false;

    $redis->incr("rl:$key");
    if ($current === false) {
        $redis->expire("rl:$key", $seconds);
    }
    return true;
}

/**
 * High-performance profile fetch with Redis caching.
 * Caches the expensive User + Photos + Posts object.
 */
function getCachedProfileData(mysqli $db, int $targetId): ?array {
    $redis = getRedis();
    $cacheKey = "profile_data_$targetId";
    
    // 1. Try Cache
    if ($redis) {
        $cached = $redis->get($cacheKey);
        if ($cached) return json_decode($cached, true);
    }

    // 2. Fetch from DB (Original logic from get_profile.php)
    $stmt = $db->prepare("
        SELECT id, phone_number, full_name, age, gender, looking_for, bio,
               interests, height, education, job_title, company,
               fcm_token,
               lifestyle_pets, lifestyle_drinking, lifestyle_smoking, lifestyle_workout, 
               lifestyle_diet, lifestyle_schedule, communication_style, relationship_goal,
               latitude, longitude, city, state, country, is_verified, profile_complete, setup_completed,
               discovery_min_age, discovery_max_age, discovery_max_dist, discovery_min_dist, global_discovery,
               stealth_radius,
               notif_matches, notif_messages, notif_likes, notif_who_swiped, notif_activity,
               credits, premium_credits
        FROM users WHERE id = ?
    ");
    $stmt->bind_param('i', $targetId);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$user) return null;

    // Photos
    $photoStmt = $db->prepare("SELECT photo_url, is_dp FROM user_photos WHERE user_id = ? ORDER BY is_dp DESC, created_at ASC");
    $photoStmt->bind_param('i', $targetId);
    $photoStmt->execute();
    $photoResult = $photoStmt->get_result();
    $photoStmt->close();

    $photos = []; $seenUrls = []; $dpUrl = null; $firstUrl = null;
    while ($photo = $photoResult->fetch_assoc()) {
        if (in_array($photo['photo_url'], $seenUrls)) continue;
        $seenUrls[] = $photo['photo_url'];
        $photos[] = ['url' => cloudinaryTransform($photo['photo_url']), 'is_dp' => (bool)$photo['is_dp']];
        if ($firstUrl === null) $firstUrl = cloudinaryTransform($photo['photo_url']);
        if ($photo['is_dp']) $dpUrl = cloudinaryTransform($photo['photo_url']);
    }
    if ($dpUrl === null && $firstUrl !== null) $dpUrl = $firstUrl;

    // Posts
    $postStmt = $db->prepare("SELECT id, photo_url, caption, created_at FROM user_posts WHERE user_id = ? ORDER BY created_at DESC");
    $postStmt->bind_param('i', $targetId);
    $postStmt->execute();
    $posts = $postStmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $postStmt->close();

    $profileData = [
        'user'   => $user,
        'photos' => $photos,
        'dp_url' => $dpUrl,
        'posts'  => $posts
    ];

    // 3. Save to Cache (1 Hour TTL)
    if ($redis) {
        $redis->setex($cacheKey, 3600, json_encode($profileData));
    }

    return $profileData;
}

/**
 * Clears the profile cache. Call this after any profile update.
 */
function clearProfileCache(int $userId): void {
    $redis = getRedis();
    if ($redis) {
        $redis->del("profile_data_$userId");
    }
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

/**
 * HIGH-PERFORMANCE ASYNC DISPATCHER
 * Moves expensive background tasks (Push Notifs, Socket Broadcasts) 
 * to a Redis-backed queue. This prevents spawning PHP processes per-request.
 */
function dispatchAsync(array $payload): void {
    $redis = getRedis();
    if ($redis) {
        // Option 1: Fast Redis Push
        $redis->lPush('task_queue', json_encode($payload));
    } else {
        // Option 2: Fallback to direct background process
        $jsonPayload = escapeshellarg(json_encode($payload));
        $workerPath  = __DIR__ . "/notifications/async_worker.php";
        
        $isWindows = strncasecmp(PHP_OS, 'WIN', 3) === 0;
        if ($isWindows) {
            // Robust Windows PHP detection
            $phpPath = 'php';
            if (!`where $phpPath 2>nul`) {
                if (file_exists('C:\xampp\php\php.exe')) {
                    $phpPath = 'C:\xampp\php\php.exe';
                }
            }
            // Windows XAMPP fallback (using start /B for async)
            $cmd = "$phpPath " . escapeshellarg($workerPath) . " " . $jsonPayload;
            pclose(popen("start /B $cmd", "r"));
        } else {
            // Linux Production fallback (async fork)
            exec("nohup php " . escapeshellarg($workerPath) . " " . $jsonPayload . " > /dev/null 2>&1 &");
        }
    }
}

// ─── REAL-TIME CHAT (SOKETI) ─────────────────────────────────
define('SOKETI_HOST',   getenv('SOKETI_HOST')   ?: 'soketi-production-3817.up.railway.app');
define('SOKETI_APP_ID', getenv('SOKETI_APP_ID') ?: 'legitdate-app');
define('SOKETI_KEY',    getenv('SOKETI_APP_KEY')    ?: 'legit-key-123');
define('SOKETI_SECRET', getenv('SOKETI_APP_SECRET') ?: 'legit-secret-456');

/**
 * Broadcasts data to Soketi via HTTP API
 */
function broadcastToSoketi(string $channel, string $event, array $data): bool {
    $path = "/apps/" . SOKETI_APP_ID . "/events";
    $body = json_encode([
        'name'     => $event,
        'channels' => [$channel],
        'data'     => json_encode($data)
    ]);

    $auth_timestamp = time();
    $auth_version   = '1.0';
    $method         = 'POST';
    
    $body_md5 = md5($body);
    $auth_query = "auth_key=" . SOKETI_KEY 
                . "&auth_timestamp=$auth_timestamp"
                . "&auth_version=$auth_version"
                . "&body_md5=$body_md5";

    $string_to_sign = "$method\n$path\n$auth_query";
    $auth_signature = hash_hmac('sha256', $string_to_sign, SOKETI_SECRET);
    
    $url = "https://" . SOKETI_HOST . "$path?$auth_query&auth_signature=$auth_signature";

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_TIMEOUT, 5);
    
    $response = curl_exec($ch);
    $info = curl_getinfo($ch);
    curl_close($ch);

    return ($info['http_code'] === 200);
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
// ─── CREDIT SYSTEM CONFIGURATION ─────────────────────────────
define('CREDIT_COST_LIKE',        10);
define('CREDIT_COST_SUPERLIKE',  25);
define('CREDIT_COST_COMPLIMENT', 30);
define('CREDIT_COST_REWIND',     10);
define('CREDIT_COST_VIEW_SECRET', 50); 
define('CREDIT_COST_PROFILE_VIEW', 10); 
define('CREDIT_COST_CALL_MIN',   50); 
define('DAILY_FREE_CREDITS',     100);

// Reads token from Authorization header OR from JSON body field 'token'
function getAuthUserId(): int {
    // 1. Try Authorization: Bearer <token>
    $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '';
    if (preg_match('/Bearer\s+(.+)/i', $authHeader, $m)) {
        $userId = verifyToken(trim($m[1]));
        if ($userId) return $userId;
    }

    // 2. Try body field 'token'
    $body = json_decode(file_get_contents('php://input'), true) ?? [];
    if (!empty($body['token'])) {
        $userId = verifyToken($body['token']);
        if ($userId) return $userId;
    }

    // 3. Try query param 'token'
    if (!empty($_GET['token'])) {
        $userId = verifyToken($_GET['token']);
        if ($userId) return $userId;
    }

    http_response_code(401);
    die(json_encode(['status' => 'error', 'message' => 'Unauthorized']));
}

/**
 * Lazy sync for location and credits. Call only from high-overhead endpoints.
 * Moves these queries OUT of the auth hot-path.
 */
function autoSyncUserMeta(int $userId, mysqli $db): void {
    // 1. Sync location from HTTP headers
    $lat = $_SERVER['HTTP_X_LATITUDE'] ?? null;
    $lng = $_SERVER['HTTP_X_LONGITUDE'] ?? null;
    $city = $_SERVER['HTTP_X_CITY'] ?? null;
    
    if ($lat && $lng) {
        $citySql = $city ? ", city = '" . $db->real_escape_string($city) . "'" : "";
        $db->query("UPDATE users SET latitude = $lat, longitude = $lng, last_active = NOW() $citySql WHERE id = $userId");
    }

    // 2. Auto-refresh daily credits (e.g., reset to daily free amount every 24h)
    $res = $db->query("SELECT last_credit_refresh FROM users WHERE id = $userId");
    if ($res && $row = $res->fetch_assoc()) {
        $last = $row['last_credit_refresh'];
        if (!$last || (time() - strtotime($last) > 86400)) {
            $db->query("UPDATE users SET credits = " . DAILY_FREE_CREDITS . ", last_credit_refresh = NOW() WHERE id = $userId");
            $db->query("INSERT INTO credit_logs (user_id, amount, reason) VALUES ($userId, " . DAILY_FREE_CREDITS . ", 'Daily reset')");
        }
    }
}

function getUserCredits(mysqli $db, int $userId): int {
    $stmt = $db->prepare("SELECT credits, premium_credits FROM users WHERE id = ?");
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $res = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    
    $free = (int)($res['credits'] ?? 0);
    $premium = (int)($res['premium_credits'] ?? 0);
    return $free + $premium;
}

function deductCredits(mysqli $db, int $userId, int $amount, string $reason): bool {
    if ($amount <= 0) return true;
    
    $stmt = $db->prepare("SELECT credits, premium_credits FROM users WHERE id = ?");
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $res = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    
    $free = (int)($res['credits'] ?? 0);
    $premium = (int)($res['premium_credits'] ?? 0);
    
    if (($free + $premium) < $amount) return false;
    
    // Spend free credits first
    $spentFromFree = min($amount, $free);
    $spentFromPremium = $amount - $spentFromFree;
    
    $db->begin_transaction();
    try {
        if ($spentFromFree > 0) {
            $upd = $db->prepare("UPDATE users SET credits = credits - ? WHERE id = ?");
            $upd->bind_param('ii', $spentFromFree, $userId);
            $upd->execute();
            $upd->close();
        }
        if ($spentFromPremium > 0) {
            $upd = $db->prepare("UPDATE users SET premium_credits = premium_credits - ? WHERE id = ?");
            $upd->bind_param('ii', $spentFromPremium, $userId);
            $upd->execute();
            $upd->close();
        }
        
        $log = $db->prepare("INSERT INTO credit_logs (user_id, amount, reason) VALUES (?, ?, ?)");
        $negAmount = -$amount;
        $log->bind_param('iis', $userId, $negAmount, $reason);
        $log->execute();
        $log->close();
        
        $db->commit();
        return true;
    } catch (Exception $e) {
        $db->rollback();
        return false;
    }
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