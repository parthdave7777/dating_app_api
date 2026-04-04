<?php
// profile/upload_photo.php
// Uploads profile / selfie photo to Cloudinary.
require_once __DIR__ . '/../config.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'Method not allowed']);
    exit();
}

$token  = $_POST['token'] ?? '';
$userId = $token ? (verifyToken($token) ?? 0) : getAuthUserId();
if (!$userId) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit();
}

if (empty($_FILES['photo'])) {
    echo json_encode(['status' => 'error', 'message' => 'No photo uploaded']);
    exit();
}

$file    = $_FILES['photo'];
$allowed = ['image/jpeg', 'image/png', 'image/webp'];
$mimeType = function_exists('mime_content_type')
    ? mime_content_type($file['tmp_name'])
    : ($file['type'] ?? 'image/jpeg');

if (!in_array($mimeType, $allowed)) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid file type. Got: ' . $mimeType]);
    exit();
}

if ($file['size'] > 10 * 1024 * 1024) {
    echo json_encode(['status' => 'error', 'message' => 'Image must be under 10MB']);
    exit();
}

$isSelfie = ($_POST['is_selfie'] ?? '0') === '1';
$db = getDB();

// FORCE: User cannot choose DP. Only the first photo they ever upload is designated as DP.
// Check if any DP already exists for this user.
$resCount = $db->query("SELECT COUNT(*) as cnt FROM user_photos WHERE user_id = $userId AND is_dp = 1");
$rowCount = $resCount->fetch_assoc();
$isDP     = ((int)$rowCount['cnt'] === 0);

// ── Upload to Cloudinary ──────────────────────────────────────
$folder   = 'dating_app/photos';
$publicId = 'photo_' . $userId . '_' . ($isSelfie ? 'selfie_' : '') . uniqid();

// We use the raw temp file. No quality reduction here on server side to keep quality high as requested.
$photoUrl = cloudinaryUpload($file['tmp_name'], $folder, $publicId, 'image');

if (!$photoUrl) {
    echo json_encode(['status' => 'error', 'message' => 'Failed to upload photo']);
    exit();
}

$stmt = $db->prepare("INSERT INTO user_photos (user_id, photo_url, is_dp, is_verified) VALUES (?, ?, ?, ?)");
$dpInt        = $isDP     ? 1 : 0;
$verifiedInt  = $isSelfie ? 1 : 0;
$stmt->bind_param('isii', $userId, $photoUrl, $dpInt, $verifiedInt);

if ($stmt->execute()) {
    $photoId = $db->insert_id;
    if ($isSelfie) {
        $db->query("UPDATE users SET verification_status = 1 WHERE id = $userId");
    }
    echo json_encode(['status' => 'success', 'photo_id' => $photoId, 'photo_url' => $photoUrl]);
} else {
    echo json_encode(['status' => 'error', 'message' => 'Execute Error: ' . $stmt->error]);
}

$stmt->close();
$db->close();
