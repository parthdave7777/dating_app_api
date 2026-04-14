<?php
// auth/firebase_login.php
require_once __DIR__ . '/../config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'Method not allowed']);
    exit();
}

$body    = json_decode(file_get_contents('php://input'), true);
$idToken = trim($body['id_token'] ?? '');
$phone   = trim($body['phone'] ?? '');

if (empty($idToken) || empty($phone)) {
    echo json_encode(['status' => 'error', 'message' => 'id_token and phone are required']);
    exit();
}

$serviceAccountPath = __DIR__ . '/../firebase-service-account.json';
if (!file_exists($serviceAccountPath)) {
    $verifiedPhone = $phone;
} else {
    // PRODUCTION: verify token with Firebase Admin SDK 
    // Logic goes here... for now we use the verifiedPhone check
    $verifiedPhone = $phone;
}

$db = getDB();

// Atomic Fetch or Create
$stmt = $db->prepare("SELECT id, profile_complete, setup_completed FROM users WHERE phone_number = ?");
$stmt->bind_param('s', $verifiedPhone);
$stmt->execute();
$result = $stmt->get_result();
$stmt->close();

$isNewUser = false;
$setupCompleted = false;

if ($result->num_rows === 0) {
    $isNewUser = true;
    $ins = $db->prepare("INSERT INTO users (phone_number) VALUES (?)");
    $ins->bind_param('s', $verifiedPhone);
    $ins->execute();
    $userId = $db->insert_id;
    $ins->close();
} else {
    $user    = $result->fetch_assoc();
    $userId  = (int) $user['id'];
    $isNewUser      = ((int) $user['profile_complete'] === 0);
    $setupCompleted = (((int)($user['setup_completed'] ?? 0)) === 1);
}

$token = generateToken($userId);

// ─── RESPOND IMMEDIATELY (Ultra-Speed) ─────────────────────
sendResponseAndContinue([
    'status'          => 'success',
    'token'           => $token,
    'user_id'         => $userId,
    'is_new_user'     => (bool)$isNewUser,
    'setup_completed' => (bool)$setupCompleted,
]);

// ─── BACKGROUND PROCESSING ───────────────────────────────
// Update last_active and setup boost timing without making the user wait
$now = date('Y-m-d H:i:s');
$db->query("UPDATE users SET last_active = '$now' WHERE id = $userId");
$db->query("UPDATE users SET new_user_boost_expires = DATE_ADD('$now', INTERVAL 2 DAY) WHERE id = $userId AND new_user_boost_expires IS NULL");

$db->close();
exit();
