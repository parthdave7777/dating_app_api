<?php
// auth/verify_otp.php
require_once __DIR__ . '/../config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'Method not allowed']);
    exit();
}

try {
    $body  = json_decode(file_get_contents('php://input'), true);
    $phone = trim($body['phone_number'] ?? '');
    $otp   = trim($body['otp'] ?? '');

    if (empty($phone) || empty($otp)) {
    echo json_encode(['status' => 'error', 'message' => 'Phone and OTP are required']);
    exit();
}

// Security: Prevent OTP brute forcing (10 attempts per 30 mins)
if (!checkRateLimit("otp_verify:$phone", 10, 1800)) {
    http_response_code(429);
    echo json_encode(['status' => 'error', 'message' => 'Too many failed attempts. Try again in 30 minutes.', 'error_code' => 'RATE_LIMITED']);
    exit();
}

    $db = getDB();
    $verified = false;

    // ── DEV MODE: Accept 123456 for any phone number ─────────────
    if ($otp === '123456') {
        $verified = true;
    }

    if (!$verified) {
        $stmt = $db->prepare(
            "SELECT id FROM otp_codes
             WHERE phone_number = ? AND otp = ? AND used = 0 AND expires_at > NOW()
             ORDER BY created_at DESC LIMIT 1"
        );
        $stmt->bind_param('ss', $phone, $otp);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 0) {
            $stmt->close();
            $db->close();
            echo json_encode(['status' => 'error', 'message' => 'Invalid or expired OTP']);
            exit();
        }

        $row = $result->fetch_assoc();
        $otpId = $row['id'];
        $stmt->close();
        $db->query("UPDATE otp_codes SET used = 1 WHERE id = $otpId");
        $verified = true;
    }

    // Find or create user
    $stmt = $db->prepare("SELECT id, profile_complete, setup_completed FROM users WHERE phone_number = ?");
    $stmt->bind_param('s', $phone);
    $stmt->execute();
    $userResult = $stmt->get_result();
    $stmt->close();

    $isNewUser = false;
    $setupCompleted = false;
    if ($userResult->num_rows === 0) {
        $isNewUser = true;
        $ins = $db->prepare("INSERT INTO users (phone_number) VALUES (?)");
        $ins->bind_param('s', $phone);
        $ins->execute();
        $userId = $db->insert_id;
        $ins->close();
    } else {
        $user           = $userResult->fetch_assoc();
        $userId         = (int) $user['id'];
        $isNewUser      = ((int) $user['profile_complete'] === 0);
        $setupCompleted = (((int)($user['setup_completed'] ?? 0)) === 1);
    }

    $token = generateToken($userId);

    // Update last_active
    $db->query("UPDATE users SET last_active = NOW() WHERE id = $userId");
    // Set boost expires for new users
    $db->query("UPDATE users SET new_user_boost_expires = DATE_ADD(NOW(), INTERVAL 2 DAY) WHERE id = $userId AND new_user_boost_expires IS NULL");
    
    $db->close();

    echo json_encode([
        'status'          => 'success',
        'token'           => $token,
        'user_id'         => $userId,
        'is_new_user'     => (bool)$isNewUser,
        'setup_completed' => (bool)$setupCompleted,
        'message'         => 'Login successful',
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Verification Error: ' . $e->getMessage()]);
}
