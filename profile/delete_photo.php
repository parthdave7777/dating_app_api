<?php
// profile/delete_photo.php
// Deletes a profile photo from DB and Cloudinary.
require_once __DIR__ . '/../config.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'Method not allowed']);
    exit();
}

$userId   = getAuthUserId();
$body     = json_decode(file_get_contents('php://input'), true);
$photoUrl = $body['photo_url'] ?? '';

if (empty($photoUrl)) {
    echo json_encode(['status' => 'error', 'message' => 'Photo URL is required']);
    exit();
}

$db = getDB();

$stmt = $db->prepare("SELECT id, photo_url, is_dp FROM user_photos WHERE user_id = ? AND photo_url = ?");
$stmt->bind_param('is', $userId, $photoUrl);
$stmt->execute();
$res = $stmt->get_result();

if ($res->num_rows === 0) {
    echo json_encode(['status' => 'error', 'message' => 'Photo not found or unauthorized']);
    $stmt->close();
    $db->close();
    exit();
}

$photo = $res->fetch_assoc();
$photoId = $photo['id'];
$isDeletingDP = (bool)$photo['is_dp'];
$stmt->close();

$del = $db->prepare("DELETE FROM user_photos WHERE id = ?");
$del->bind_param('i', $photoId);

if ($del->execute()) {
    // If we deleted the DP, promote the next oldest photo to be the new DP
    if ($isDeletingDP) {
        $db->query("UPDATE user_photos SET is_dp = 1 
                    WHERE user_id = $userId 
                    ORDER BY created_at ASC LIMIT 1");
    }

    // Delete from Cloudinary (works for both old local URLs and new Cloudinary URLs)
    if (strpos($photo['photo_url'], 'cloudinary.com') !== false) {
        cloudinaryDelete($photo['photo_url'], 'image');
    }
    clearProfileCache($userId);
    echo json_encode(['status' => 'success', 'message' => 'Photo deleted']);
} else {
    echo json_encode(['status' => 'error', 'message' => 'Failed to delete from database']);
}

$del->close();
$db->close();
