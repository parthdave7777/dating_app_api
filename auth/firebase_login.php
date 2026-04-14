<?php
// auth/firebase_login.php
//
// ── FIREBASE ADMIN SDK SETUP ────────────────────────────────
//  This endpoint verifies a Firebase ID token (sent from the Flutter
//  app after phone-auth via Firebase).
//
//  Steps to enable:
//  1. Go to Firebase Console → Project Settings → Service Accounts
//  2. Click "Generate new private key" → download the JSON file
//  3. Place it at:  dating_api/firebase-service-account.json
//  4. Install the SDK:  composer require kreait/firebase-php
//  5. Delete or comment out the "FIREBASE NOT CONFIGURED" block below
//
//  ────────────────────────────────────────────────────────────
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

// ── FIREBASE NOT CONFIGURED (dev bypass) ────────────────────
//  If Firebase credentials are not yet set up, we skip token
//  verification and trust the phone number from the request.
//  DELETE this entire block once you add the service account JSON.
$serviceAccountPath = __DIR__ . '/../firebase-service-account.json';
if (!file_exists($serviceAccountPath)) {
    // Treat as unauthenticated — just use phone directly
    $verifiedPhone = $phone;
    goto handleUser;
}
// ── END DEV BYPASS ──────────────────────────────────────────

// ── PRODUCTION: verify token with Firebase Admin SDK ────────
//  Uncomment after running:  composer require kreait/firebase-php
//
// require_once __DIR__ . '/../vendor/autoload.php';
// use Kreait\Firebase\Factory;
//
// try {
//     $factory = (new Factory)->withServiceAccount($serviceAccountPath);
//     $auth    = $factory->createAuth();
//     $verified = $auth->verifyIdToken($idToken);
//     $verifiedPhone = $verified->claims()->get('phone_number');
// } catch (\Exception $e) {
//     echo json_encode(['status' => 'error', 'message' => 'Invalid Firebase token']);
//     exit();
// }

handleUser:
$db = getDB();

$stmt = $db->prepare("SELECT id, profile_complete FROM users WHERE phone_number = ?");
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

// Update last_active and expire new user boost check
$activeDb = getDB();
$activeDb->query("UPDATE users SET last_active = NOW() WHERE id = $userId");
// Set new_user_boost_expires if this is a new user (only first time)
$activeDb->query("UPDATE users SET new_user_boost_expires = DATE_ADD(NOW(), INTERVAL 2 DAY) WHERE id = $userId AND new_user_boost_expires IS NULL");
$activeDb->close();

$db->close();

echo json_encode([
    'status'          => 'success',
    'token'           => $token,
    'user_id'         => $userId,
    'is_new_user'     => (bool)$isNewUser,
    'setup_completed' => (bool)$setupCompleted,
]);
