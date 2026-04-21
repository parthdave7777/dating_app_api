<?php
// auth/send_otp.php
require_once __DIR__ . '/../config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'Method not allowed']);
    exit();
}

$body = json_decode(file_get_contents('php://input'), true);
$phone = trim($body['phone_number'] ?? '');

if (empty($phone)) {
    echo json_encode(['status' => 'error', 'message' => 'Phone number is required']);
    exit();
}

if (!preg_match('/^\+\d{10,15}$/', $phone)) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid phone number format']);
    exit();
}

// Security: Rate limit OTP requests to 5 per hour per phone
if (!checkRateLimit("otp_send:$phone", 5, 3600)) {
    http_response_code(429);
    echo json_encode(['status' => 'error', 'message' => 'Too many OTP requests. Try again in an hour.', 'error_code' => 'RATE_LIMITED']);
    exit();
}

$db = getDB();

// ── DEV MODE: Static OTP — always 123456, never expires ─────
// Just enter 123456 on the OTP screen for any phone number.
// When going to production, delete these 2 lines and uncomment the 2 PRODUCTION lines:
$otp = '123456';
$exp = '2099-12-31 23:59:59';
// PRODUCTION: uncomment below and delete the 2 lines above
// $otp = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
// $exp = date('Y-m-d H:i:s', time() + 600); // 10 minutes

// Upsert OTP — insert or update if phone already exists
$stmt = $db->prepare("
    INSERT INTO otp_codes (phone_number, otp, expires_at, used)
    VALUES (?, ?, ?, 0)
    ON DUPLICATE KEY UPDATE otp = VALUES(otp), expires_at = VALUES(expires_at), used = 0
");
$stmt->bind_param('sss', $phone, $otp, $exp);
$stmt->execute();
$stmt->close();
$db->close();

echo json_encode(['status' => 'success', 'message' => 'OTP sent successfully']);
