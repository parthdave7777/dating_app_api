<?php
// notifications/get_notifications.php
require_once __DIR__ . '/../config.php';

$userId = getAuthUserId();
$db     = getDB();

$stmt = $db->prepare("
    SELECT id, type, title, body, data, is_read, created_at
    FROM notifications
    WHERE user_id = ?
    ORDER BY created_at DESC
    LIMIT 50
");
$stmt->bind_param('i', $userId);
$stmt->execute();
$result = $stmt->get_result();
$stmt->close();

$notifications = [];
while ($row = $result->fetch_assoc()) {
    $notifications[] = [
        'id'         => (int)  $row['id'],
        'type'       =>        $row['type'],
        'title'      =>        $row['title'],
        'body'       =>        $row['body'],
        'data'       =>        $row['data'] ? json_decode($row['data'], true) : null,
        'is_read'    => (bool) $row['is_read'],
        'created_at' =>        $row['created_at'],
    ];
}

// Mark all as read
$db->query("UPDATE notifications SET is_read = 1 WHERE user_id = $userId AND is_read = 0");

$db->close();
echo json_encode(['status' => 'success', 'notifications' => $notifications]);
