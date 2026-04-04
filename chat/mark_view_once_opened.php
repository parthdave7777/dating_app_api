<?php
// chat/mark_media_opened.php
// Marks a view-once message as opened and permanently deletes the content.
require_once __DIR__ . '/../config.php';

$userId    = getAuthUserId();
$body      = json_decode(file_get_contents('php://input'), true) ?? [];
$messageId = (int)($body['message_id'] ?? 0);

if (!$messageId) {
    echo json_encode(['status' => 'error', 'message' => 'message_id required']);
    exit();
}

$db = getDB();

$stmt = $db->prepare("
    SELECT m.id, m.message, m.type, m.is_view_once, m.is_opened, m.match_id, mt.user1_id, mt.user2_id
    FROM messages m
    JOIN matches mt ON mt.id = m.match_id
    WHERE m.id = ?
");
$stmt->bind_param('i', $messageId);
$stmt->execute();
$msg = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$msg) {
    echo json_encode(['status' => 'error', 'message' => 'Message not found']);
    exit();
}

// Only the participants can mark as opened
$isPart = ($userId == $msg['user1_id'] || $userId == $msg['user2_id']);
if (!$isPart) {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit();
}

// Only view-once messages
if (!$msg['is_view_once']) {
    echo json_encode(['status' => 'error', 'message' => 'Not a view-once message']);
    exit();
}

// Already opened?
if ($msg['is_opened']) {
    echo json_encode(['status' => 'error', 'message' => 'Already opened']);
    exit();
}

$fileUrl = $msg['message'];
$resourceType = (strpos($msg['type'], 'video') !== false) ? 'video' : 'image';

// ── Update DB first ───────────────────────────────────────────
$updateStmt = $db->prepare("UPDATE messages SET is_opened = 1, opened_at = NOW(), message = 'OPENED' WHERE id = ?");
$updateStmt->bind_param('i', $messageId);
$updateStmt->execute();
$updateStmt->close();

// ── Delete from Cloudinary/Local ──────────────────────────────
if (!empty($fileUrl)) {
    if (strpos($fileUrl, 'cloudinary.com') !== false) {
        cloudinaryDelete($fileUrl, $resourceType);
    } else {
        // If it's a local file in XAMPP
        $localPath = __DIR__ . '/../' . $fileUrl;
        if (file_exists($localPath)) {
            @unlink($localPath);
        }
    }
}

$db->close();
echo json_encode(['status' => 'success']);
