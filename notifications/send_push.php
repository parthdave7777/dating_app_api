<?php
/**
 * notifications/send_push.php — internal helper (not a public endpoint)
 * Called by swipe.php, send_message.php, initiate_call.php, respond_call.php
 *
 * Uses FCM HTTP v1 API with Service Account (OAuth2 JWT).
 * No legacy server key needed — works permanently in production.
 */

if (!function_exists('sendPush')) {

// Helper to load service account
function getFcmServiceAccount(): ?array {
    static $sa = null;
    if ($sa !== null) return $sa;
    
    // Check Env Var
    $envJson = getenv('FCM_SERVICE_ACCOUNT_JSON');
    if (!empty($envJson)) {
        $sa = json_decode($envJson, true);
        return $sa;
    }
    
    // Check File (Render fallback/local)
    $possiblePaths = [
        __DIR__ . '/../legitdate-d69ce-f98ee630c9c2.json',
        __DIR__ . '/legitdate-d69ce-f98ee630c9c2.json'
    ];
    foreach ($possiblePaths as $p) {
        if (file_exists($p)) {
            $sa = json_decode(file_get_contents($p), true);
            return $sa;
        }
    }
    return null;
}

// ─────────────────────────────────────────────────────────────
//  Generate OAuth2 Bearer token
// ─────────────────────────────────────────────────────────────
function getFcmAccessToken(): ?string {
    static $cachedToken    = null;
    static $cachedExpiry   = 0;
    
    $sa = getFcmServiceAccount();
    if (!$sa) return null;
    $projectId = $sa['project_id'] ?? '';

    // SPEED OPT 3: Try APCu cache first (~200ms saving)
    if (function_exists('apcu_fetch')) {
        $apcuToken = apcu_fetch('fcm_access_token', $success);
        if ($success && $apcuToken) return $apcuToken;
    }

    // SPEED OPT 4: File-based fallback cache if APCu is missing (Common on XAMPP)
    $cacheDir  = __DIR__ . '/../cache';
    $cacheFile = $cacheDir . '/fcm_token.json';
    if (!is_dir($cacheDir)) @mkdir($cacheDir, 0777, true);

    if (file_exists($cacheFile)) {
        $fCache = json_decode(file_get_contents($cacheFile), true);
        if ($fCache && ($fCache['expiry'] ?? 0) > time() + 60) {
            return $fCache['token'];
        }
    }

    // Fall back to static variable cache (same request)
    if ($cachedToken && time() < $cachedExpiry - 60) {
        return $cachedToken;
    }

    if (!file_exists(FCM_SERVICE_ACCOUNT_PATH)) {
        error_log('[FCM] Service account file not found: ' . FCM_SERVICE_ACCOUNT_PATH);
        return null;
    }

    $sa = json_decode(file_get_contents(FCM_SERVICE_ACCOUNT_PATH), true);
    if (!$sa || empty($sa['private_key']) || empty($sa['client_email'])) {
        error_log('[FCM] Invalid service account JSON');
        return null;
    }

    // ── Build JWT ─────────────────────────────────────────────
    $now = time();
    $exp = $now + 3600; // 1 hour

    $header  = base64_encode(json_encode(['alg' => 'RS256', 'typ' => 'JWT']));
    $payload = base64_encode(json_encode([
        'iss'   => $sa['client_email'],
        'scope' => 'https://www.googleapis.com/auth/firebase.messaging',
        'aud'   => 'https://oauth2.googleapis.com/token',
        'iat'   => $now,
        'exp'   => $exp,
    ]));

    // URL-safe base64
    $header  = rtrim(strtr($header,  '+/', '-_'), '=');
    $payload = rtrim(strtr($payload, '+/', '-_'), '=');

    $sigInput = "$header.$payload";

    // Sign with private key (RS256)
    $privateKey = openssl_pkey_get_private($sa['private_key']);
    if (!$privateKey) {
        error_log('[FCM] Failed to load private key');
        return null;
    }

    $signature = '';
    if (!openssl_sign($sigInput, $signature, $privateKey, OPENSSL_ALGO_SHA256)) {
        error_log('[FCM] Failed to sign JWT');
        return null;
    }

    $signature = rtrim(strtr(base64_encode($signature), '+/', '-_'), '=');
    $jwt = "$sigInput.$signature";

    // ── Exchange JWT for access token ─────────────────────────
    $ch = curl_init('https://oauth2.googleapis.com/token');
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => http_build_query([
            'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
            'assertion'  => $jwt,
        ]),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 10,
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode !== 200 || !$response) {
        error_log('[FCM] Token exchange failed: ' . $response);
        return null;
    }

    $tokenData = json_decode($response, true);
    if (empty($tokenData['access_token'])) {
        error_log('[FCM] No access_token in response: ' . $response);
        return null;
    }

    $cachedToken  = $tokenData['access_token'];
    $cachedExpiry = $now + (int)($tokenData['expires_in'] ?? 3600);

    // SPEED OPT 3: Store in APCu
    if (function_exists('apcu_store') && !empty($tokenData['access_token'])) {
        apcu_store('fcm_access_token', $tokenData['access_token'], 3300);
    }

    // SPEED OPT 4: Store in file cache
    @file_put_contents($cacheFile, json_encode([
        'token'  => $cachedToken,
        'expiry' => $cachedExpiry
    ]));

    return $cachedToken;
}

// ─────────────────────────────────────────────────────────────
//  Main sendPush function
// ─────────────────────────────────────────────────────────────
function sendPush(
    mysqli $db,
    int    $toUserId,
    string $type,
    string $title,
    string $body,
    array  $data = []
): void {

    // ── Save to notifications table (History Hub) ───────────────────────────
    // BUG 3 FIX: Only persist match/like/superlike/missed_call — profile_view removed.
    // Skip transient events (incoming_call, accept, etc.) and individual chat messages.
    $persistTypes = ['match', 'like', 'superlike', 'missed_call'];
    if (in_array($type, $persistTypes, true)) {
        $dataJson = json_encode(array_merge($data, ['type' => $type]));
        $stmt = $db->prepare(
            "INSERT INTO notifications (user_id, type, title, body, data) VALUES (?, ?, ?, ?, ?)"
        );
        $stmt->bind_param('issss', $toUserId, $type, $title, $body, $dataJson);
        $stmt->execute();
        $stmt->close();
    }

    // ── Get recipient's FCM token AND check preferences ─────────────────────────────
    $tokStmt = $db->prepare("SELECT fcm_token, notif_matches, notif_messages, notif_likes FROM users WHERE id = ?");
    $tokStmt->bind_param('i', $toUserId);
    $tokStmt->execute();
    $row = $tokStmt->get_result()->fetch_assoc();
    $tokStmt->close();

    if (!$row) return;

    // Filter by type
    if ($type === 'message' && ($row['notif_messages'] ?? 1) == 0) {
        error_log("[FCM] Suppression: User $toUserId has disabled message notifications");
        return;
    }
    if ($type === 'match' && ($row['notif_matches'] ?? 1) == 0) {
        error_log("[FCM] Suppression: User $toUserId has disabled match notifications");
        return;
    }
    if ($type === 'like' && ($row['notif_likes'] ?? 1) == 0) {
        error_log("[FCM] Suppression: User $toUserId has disabled like notifications");
        return;
    }

    if (empty($row['fcm_token'])) {
        error_log("[FCM] No FCM token for user $toUserId");
        return;
    }

    $fcmToken = $row['fcm_token'];

    // ── Get OAuth2 access token ───────────────────────────────
    $accessToken = getFcmAccessToken();
    if (!$accessToken) {
        error_log('[FCM] Could not get access token — push skipped');
        return;
    }

    // ── All data values must be strings for FCM data messages ─
    $stringData = array_map('strval', array_merge($data, [
        'type'  => $type,
        'title' => $title,
        'body'  => $body
    ]));

    // ── Build FCM HTTP v1 payload ─────────────────────────────
    $isCall = ($type === 'incoming_call');
    // SPEED OPT 2: Shorter TTL for time-sensitive events so FCM won't redeliver stale pushes
    $isTimeSensitive = in_array($type, ['like', 'superlike', 'match', 'incoming_call']);

    // Get project ID dynamically for the URL
    $sa = getFcmServiceAccount();
    $projectId = $sa['project_id'] ?? '';
    if (!$projectId) {
        error_log('[FCM] No project_id found in JSON or EnvVar — push skipped');
        return;
    }

    // BUG FIX: Use data-only messages (no 'notification' block) for ALL types
    // so the Flutter app always handles them — this prevents the OS from
    // showing duplicate notifications AND stops foreground-suppression issues.
    // The Flutter NotificationService creates the visible banner itself.
    $message = [
        'message' => [
            'token' => $fcmToken,
            'data'  => $stringData,
            'android' => [
                'priority' => 'high',
                // Short TTL for time-sensitive types; 24h for the rest.
                'ttl'      => $isCall ? '30s' : ($isTimeSensitive ? '3600s' : '86400s'),
                // NOTE: No 'notification' block here — we use data-only messages
                // so Flutter's onMessage / background handler always fires.
                // flutter_local_notifications builds the visible banner itself.
            ],
            // BUG FIX: Add APNS config for iOS so high-priority push wakes device
            'apns' => [
                'headers' => [
                    'apns-priority' => $isCall ? '10' : '5',
                    'apns-push-type' => $isCall ? 'alert' : 'alert',
                ],
                'payload' => [
                    'aps' => [
                        'content-available' => 1,
                        'sound' => $isCall ? 'default' : 'default',
                    ],
                ],
            ],
        ],
    ];

    $url = 'https://fcm.googleapis.com/v1/projects/' . $projectId . '/messages:send';

    // SPEED OPT 6: Fire FCM push asynchronously so swipe.php responds instantly.
    // BUG FIX: On Windows XAMPP, exec() with Linux shell syntax (> /dev/null) fails.
    // Force Option B (standard curl) for Windows local development to ensure reliability.
    $isWindows = strncasecmp(PHP_OS, 'WIN', 3) === 0;
    $disabledFunctions = array_map('trim', explode(',', ini_get('disable_functions')));
    $execAvailable = !$isWindows && function_exists('exec') && !in_array('exec', $disabledFunctions);
    
    if ($execAvailable) {
        // Option A — async fire-and-forget
        $payload = json_encode($message);
        $cmd = "curl -s -X POST " . escapeshellarg($url)
             . " -H " . escapeshellarg('Authorization: Bearer ' . $accessToken)
             . " -H 'Content-Type: application/json'"
             . " -d " . escapeshellarg($payload)
             . " > /dev/null 2>&1 &";
        exec($cmd);
        error_log("[FCM] Push dispatched async to user $toUserId type=$type");
    } else {
        // Option B — blocking curl with fast 3s timeout
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_HTTPHEADER     => [
                'Authorization: Bearer ' . $accessToken,
                'Content-Type: application/json',
            ],
            CURLOPT_POSTFIELDS     => json_encode($message),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 3,  // Reduced from 8s
        ]);

        $result   = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode === 404) {
            // Token is unregistered (app uninstalled/reinstalled) — clear it
            $clearStmt = $db->prepare("UPDATE users SET fcm_token = NULL WHERE id = ?");
            $clearStmt->bind_param('i', $toUserId);
            $clearStmt->execute();
            $clearStmt->close();
            error_log("[FCM] Cleared stale token for user $toUserId");
        } elseif ($httpCode !== 200) {
            error_log("[FCM] Push failed (HTTP $httpCode) for user $toUserId: $result");
        } else {
            error_log("[FCM] Push sent OK to user $toUserId type=$type");
        }
    }
}

} // end if (!function_exists)
