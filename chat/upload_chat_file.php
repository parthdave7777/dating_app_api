<?php
// chat/upload_chat_file.php
// Uploads view-once or regular media to Cloudinary, stores URL in DB.
require_once __DIR__ . '/../config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'Method not allowed']);
    exit();
}

$userId  = getAuthUserId();
$matchId = (int) ($_GET['match_id'] ?? $_POST['match_id'] ?? 0);

if (!$matchId) {
    echo json_encode(['status' => 'error', 'message' => 'match_id required']);
    exit();
}

if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
    $code = $_FILES['file']['error'] ?? 'missing';
    echo json_encode(['status' => 'error', 'message' => "File upload error: $code"]);
    exit();
}

$db = getDB();

// Verify user belongs to this match
$authStmt = $db->prepare(
    "SELECT user1_id, user2_id FROM matches WHERE id = ? AND (user1_id = ? OR user2_id = ?)"
);
$authStmt->bind_param('iii', $matchId, $userId, $userId);
$authStmt->execute();
$matchRow = $authStmt->get_result()->fetch_assoc();
$authStmt->close();

if (!$matchRow) {
    $db->close();
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Access denied']);
    exit();
}

$file = $_FILES['file'];
$ext  = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

$imageExts = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
$videoExts = ['mp4', 'mov', 'avi', 'mkv', '3gp', 'webm'];

$isViewOnce = (int)($_GET['view_once'] ?? $_POST['view_once'] ?? 0) === 1;

if (in_array($ext, $imageExts)) {
    $msgType      = $isViewOnce ? 'image_view_once' : 'image';
    $maxSize      = 10 * 1024 * 1024;
    $resourceType = 'image';
} elseif (in_array($ext, $videoExts)) {
    $msgType      = $isViewOnce ? 'video_view_once' : 'video';
    $maxSize      = 50 * 1024 * 1024;
    $resourceType = 'video';
} else {
    $db->close();
    echo json_encode(['status' => 'error', 'message' => 'Only image or video files are allowed']);
    exit();
}

if ($file['size'] > $maxSize) {
    $db->close();
    echo json_encode(['status' => 'error', 'message' => 'File too large (max ' . ($resourceType === 'video' ? '50MB' : '10MB') . ')']);
    exit();
}

// ── Upload to Cloudinary ──────────────────────────────────────
$folder   = 'dating_app/chat';
$publicId = 'chat_' . $userId . '_' . time() . '_' . bin2hex(random_bytes(4));

$fileUrl = cloudinaryUpload($file['tmp_name'], $folder, $publicId, $resourceType);

if (!$fileUrl) {
    $db->close();
    echo json_encode(['status' => 'error', 'message' => 'Failed to upload to Cloudinary']);
    exit();
}

// ── Insert message ────────────────────────────────────────────
$stmt = $db->prepare(
    "INSERT INTO messages (match_id, sender_id, message, type, is_view_once) VALUES (?, ?, ?, ?, ?)"
);
$isVoInt = $isViewOnce ? 1 : 0;
$stmt->bind_param('iissi', $matchId, $userId, $fileUrl, $msgType, $isVoInt);

if (!$stmt->execute()) {
    $dbErr = $stmt->error;
    $stmt->close();
    $db->close();
    error_log("[upload_chat_file] INSERT failed: $dbErr");
    echo json_encode(['status' => 'error', 'message' => "DB error: $dbErr"]);
    exit();
}

$msgId = $db->insert_id;
$stmt->close();

$db->query("UPDATE users SET last_active = NOW() WHERE id = $userId");

// Fetch inserted message
$msgStmt = $db->prepare("
    SELECT m.*, u.full_name AS sender_name
    FROM messages m JOIN users u ON u.id = m.sender_id
    WHERE m.id = ?
");
$msgStmt->bind_param('i', $msgId);
$msgStmt->execute();
$msgRow = $msgStmt->get_result()->fetch_assoc();
$msgStmt->close();

// Push notification
$recipientId = ((int)$matchRow['user1_id'] === $userId)
    ? (int) $matchRow['user2_id']
    : (int) $matchRow['user1_id'];

require_once __DIR__ . '/../notifications/send_push.php';

$tcStmt = $db->prepare("SELECT fcm_token FROM users WHERE id IN (?, ?) ORDER BY id ASC");
$tcStmt->bind_param('ii', $userId, $recipientId);
$tcStmt->execute();
$tcResult = $tcStmt->get_result();
$tcTokens = [];
while ($tr = $tcResult->fetch_assoc()) { $tcTokens[] = $tr['fcm_token']; }
$tcStmt->close();

if (count($tcTokens) < 2 || $tcTokens[0] !== $tcTokens[1] || empty($tcTokens[0])) {
    $senderName = $msgRow['sender_name'] ?? 'Someone';
    $pushBody   = (strpos($msgType, 'video') !== false) ? '🎥 Sent a video' : '📷 Sent a photo';
    sendPush($db, $recipientId, 'message', $senderName, $pushBody, [
        'match_id'  => (string)$matchId,
        'sender_id' => (string)$msgRow['sender_id'],
        'title'     => $senderName,
        'body'      => $pushBody,
    ]);
}

$db->close();

echo json_encode([
    'status'   => 'success',
    'file_url' => $fileUrl,
    'message'  => [
        'id'          => (int)  $msgRow['id'],
        'sender_id'   => (int)  $msgRow['sender_id'],
        'sender_name' =>        $msgRow['sender_name'],
        'message'     =>        $fileUrl,
        'type'         =>        $msgType,
        'is_read'      => false,
        'is_received'  => false,
        'is_opened'    => false,
        'is_view_once' => $isViewOnce,
        'is_saved'     => 0,
        'is_deleted'   => false,
        'is_edited'    => false,
        'created_at'   =>        $msgRow['created_at'],
    ],
]);
