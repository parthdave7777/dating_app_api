<?php
/**
 * credits/google_play_auth.php
 * Helper that returns a valid Bearer token from the service account JSON.
 */

require_once __DIR__ . '/../config.php';

function getGooglePlayAccessToken(): string {
    $redis = getRedis();
    $cacheKey = "gplay_access_token";

    // 1. Try Redis Cache (Refresh before 3600s expiry)
    if ($redis) {
        $cached = $redis->get($cacheKey);
        if ($cached) return $cached;
    }

    // 2. Read Service Account from Env
    $saJson = getenv('GOOGLE_SERVICE_ACCOUNT_JSON');
    if (empty($saJson)) {
        // Fallback to local file if Env not set (for testing)
        $saFile = __DIR__ . '/../legitdate-d69ce-f98ee630c9c2.json';
        if (file_exists($saFile)) {
            $saJson = file_get_contents($saFile);
        } else {
            throw new Exception("GOOGLE_SERVICE_ACCOUNT_JSON not found.");
        }
    }

    $sa = json_decode($saJson, true);
    if (!$sa || !isset($sa['private_key']) || !isset($sa['client_email'])) {
        throw new Exception("Invalid Service Account JSON.");
    }

    // 3. Build JWT
    $now = time();
    $header = base64UrlEncode(json_encode(['alg' => 'RS256', 'typ' => 'JWT']));
    $payload = base64UrlEncode(json_encode([
        'iss'   => $sa['client_email'],
        'scope' => 'https://www.googleapis.com/auth/androidpublisher',
        'aud'   => 'https://oauth2.googleapis.com/token',
        'exp'   => $now + 3600,
        'iat'   => $now
    ]));

    $sigInput = "$header.$payload";
    $signature = '';
    
    if (!openssl_sign($sigInput, $signature, $sa['private_key'], OPENSSL_ALGO_SHA256)) {
        throw new Exception("Failed to sign JWT for Google Play API.");
    }
    
    $signature = base64UrlEncode($signature);
    $jwt = "$sigInput.$signature";

    // 4. Exchange JWT for Access Token
    $ch = curl_init('https://oauth2.googleapis.com/token');
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => http_build_query([
            'grant_type' => 'urn:ietf:params:oauth2:jwt-bearer',
            'assertion'  => $jwt
        ]),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 10
    ]);

    $response = curl_exec($ch);
    $data = json_decode($response, true);
    curl_close($ch);

    if (empty($data['access_token'])) {
        throw new Exception("Token exchange failed: " . ($data['error_description'] ?? $response));
    }

    $token = $data['access_token'];

    // 5. Cache in Redis (3500s)
    if ($redis) {
        $redis->setex($cacheKey, 3500, $token);
    }

    return $token;
}
