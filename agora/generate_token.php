<?php
/**
 * Agora RTC Token Generator
 *
 * Endpoint: POST /agora/generate_token.php
 * Headers:  Authorization: Bearer <jwt_token>
 * Body:     { "channel_name": "match_123" }
 * Returns:  { "status": "success", "token": "...", "uid": 12345, "channel": "match_123", "expires_in": 3600 }
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/Util.php';
require_once __DIR__ . '/AccessToken2.php';
require_once __DIR__ . '/RtcTokenBuilder2.php';

if (!defined('AGORA_APP_ID'))   define('AGORA_APP_ID',   '');
if (!defined('AGORA_APP_CERT')) define('AGORA_APP_CERT', '');
if (!defined('TOKEN_EXPIRY'))   define('TOKEN_EXPIRY',   3600);

// ── Auth ─────────────────────────────────────────────────────
$userId = getAuthUserId();

// ── Input ────────────────────────────────────────────────────
$body        = json_decode(file_get_contents('php://input'), true) ?? [];
$channelName = trim($body['channel_name'] ?? '');

if (empty($channelName)) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'channel_name is required']);
    exit();
}

// ── Generate Token ───────────────────────────────────────────
$token = RtcTokenBuilder2::buildTokenWithUid(
    AGORA_APP_ID,
    AGORA_APP_CERT,
    $channelName,
    $userId,
    RtcTokenBuilder2::ROLE_PUBLISHER,
    TOKEN_EXPIRY,
    TOKEN_EXPIRY
);

echo json_encode([
    'status'     => 'success',
    'token'      => $token,
    'uid'        => $userId,
    'channel'    => $channelName,
    'app_id'     => AGORA_APP_ID,
    'expires_in' => TOKEN_EXPIRY,
    'expires_at' => time() + TOKEN_EXPIRY,
]);
