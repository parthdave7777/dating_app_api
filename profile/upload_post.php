<?php
// profile/upload_post.php
// Uploads a post/lifestyle photo to Cloudinary.
require_once __DIR__ . '/../config.php';

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

$caption = trim($_POST['caption'] ?? '');

if (empty($_FILES['post'])) {
    echo json_encode(['status' => 'error', 'message' => 'No image uploaded']);
    exit();
}

$file     = $_FILES['post'];
$allowed  = ['image/jpeg', 'image/png', 'image/webp'];
$mimeType = function_exists('mime_content_type')
    ? mime_content_type($file['tmp_name'])
    : ($file['type'] ?? 'image/jpeg');

if (!in_array($mimeType, $allowed)) {
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, ['jpg', 'jpeg', 'png', 'webp'])) {
        echo json_encode(['status' => 'error', 'message' => 'Only JPEG, PNG, WebP allowed. Got: ' . $mimeType]);
        exit();
    }
}

if ($file['size'] > 10 * 1024 * 1024) {
    echo json_encode(['status' => 'error', 'message' => 'File must be under 10 MB']);
    exit();
}

// ── Upload to Cloudinary ──────────────────────────────────────
$folder   = 'dating_app/posts';
$publicId = 'post_' . $userId . '_' . uniqid();

$photoUrl = cloudinaryUpload($file['tmp_name'], $folder, $publicId, 'image');

if (!$photoUrl) {
    echo json_encode(['status' => 'error', 'message' => 'Failed to upload post image']);
    exit();
}

$db   = getDB();
$stmt = $db->prepare("INSERT INTO user_posts (user_id, photo_url, caption) VALUES (?, ?, ?)");
$stmt->bind_param('iss', $userId, $photoUrl, $caption);

if ($stmt->execute()) {
    $postId = $db->insert_id;
    echo json_encode([
        'status'    => 'success',
        'post_id'   => $postId,
        'photo_url' => $photoUrl,
        'message'   => 'Post uploaded',
    ]);
} else {
    echo json_encode(['status' => 'error', 'message' => 'Database error: ' . $stmt->error]);
}

$stmt->close();
$db->close();
