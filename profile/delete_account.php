<?php
// profile/delete_account.php
// Permanently deletes a user account + all Cloudinary media + all DB records.
require_once __DIR__ . '/../config.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'Method not allowed']);
    exit();
}

try {
    $userId = getAuthUserId();
} catch (Exception $e) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit();
}

$db = getDB();

// ── 1. Fetch user details ────────────────────────────────────────────────────
$stmt = $db->prepare("SELECT phone_number FROM users WHERE id = ?");
$stmt->bind_param('i', $userId);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$user) {
    echo json_encode(['status' => 'error', 'message' => 'User not found']);
    $db->close();
    exit();
}

$phoneNumber = $user['phone_number'];

// ── 2. Collect all Cloudinary URLs to delete ─────────────────────────────────
$cloudinaryUrls = [];

// A. Profile photos
$stmt = $db->prepare("SELECT photo_url FROM user_photos WHERE user_id = ?");
$stmt->bind_param('i', $userId);
$stmt->execute();
$res = $stmt->get_result();
while ($row = $res->fetch_assoc()) {
    if (!empty($row['photo_url'])) $cloudinaryUrls[] = $row['photo_url'];
}
$stmt->close();

// B. User posts (photos / videos)
$stmt = $db->prepare("SELECT photo_url FROM user_posts WHERE user_id = ?");
$stmt->bind_param('i', $userId);
$stmt->execute();
$res = $stmt->get_result();
while ($row = $res->fetch_assoc()) {
    if (!empty($row['photo_url'])) $cloudinaryUrls[] = $row['photo_url'];
}
$stmt->close();

// C. Chat media (images / videos sent in any match this user was part of)
$stmt = $db->prepare("
    SELECT m.message
    FROM messages m
    JOIN matches mt ON m.match_id = mt.id
    WHERE (mt.user1_id = ? OR mt.user2_id = ?)
      AND m.type IN ('image', 'video')
");
$stmt->bind_param('ii', $userId, $userId);
$stmt->execute();
$res = $stmt->get_result();
while ($row = $res->fetch_assoc()) {
    if (!empty($row['message'])) $cloudinaryUrls[] = $row['message'];
}
$stmt->close();

// ── 3. Delete from Cloudinary ────────────────────────────────────────────────
$cloudinaryErrors = 0;
foreach ($cloudinaryUrls as $url) {
    if (strpos($url, 'cloudinary.com') === false) continue; // skip non-cloudinary
    // Determine resource type from URL
    $resourceType = (strpos($url, '/video/') !== false) ? 'video' : 'image';
    $deleted = cloudinaryDelete($url, $resourceType);
    if (!$deleted) $cloudinaryErrors++;
}

// ── 4. Database cleanup ──────────────────────────────────────────────────────
// Delete OTP codes (no FK constraint)
$db->query("DELETE FROM otp_codes WHERE phone_number = '" . $db->real_escape_string($phoneNumber) . "'");

// Delete the user — ON DELETE CASCADE handles:
// user_photos, user_posts, swipes, matches → messages,
// profile_views, reports, blocks, notifications
$stmt = $db->prepare("DELETE FROM users WHERE id = ?");
$stmt->bind_param('i', $userId);

if ($stmt->execute()) {
    $stmt->close();
    $db->close();
    echo json_encode([
        'status'  => 'success',
        'message' => 'Your account and all associated data have been permanently deleted.',
        'media_deleted' => count($cloudinaryUrls),
        'media_errors'  => $cloudinaryErrors,
    ]);
} else {
    $err = $stmt->error;
    $stmt->close();
    $db->close();
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Failed to delete account: ' . $err]);
}
?>
