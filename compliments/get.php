<?php
// compliments/get.php
require_once __DIR__ . '/../config.php';

$userId = getAuthUserId();
$db     = getDB();
$type   = $_GET['type'] ?? 'received'; // 'received' or 'sent'

if ($type === 'sent') {
    $stmt = $db->prepare("
        SELECT c.*, u.full_name, u.age, u.city,
        (SELECT photo_url FROM user_photos WHERE user_id = u.id AND is_dp = 1 LIMIT 1) as dp_url
        FROM compliments c
        JOIN users u ON c.receiver_id = u.id
        WHERE c.sender_id = ?
        ORDER BY c.created_at DESC
    ");
} else {
    $stmt = $db->prepare("
        SELECT c.*, u.full_name, u.age, u.city,
        (SELECT photo_url FROM user_photos WHERE user_id = u.id AND is_dp = 1 LIMIT 1) as dp_url
        FROM compliments c
        JOIN users u ON c.sender_id = u.id
        WHERE c.receiver_id = ?
        ORDER BY c.created_at DESC
    ");
}

$stmt->bind_param('i', $userId);
$stmt->execute();
$result = $stmt->get_result();

$compliments = [];
while ($row = $result->fetch_assoc()) {
    $compliments[] = [
        'id' => (int)$row['id'],
        'message' => $row['message'],
        'created_at' => $row['created_at'],
        'sender' => [
            'id' => (string)(($type === 'sent') ? $row['receiver_id'] : $row['sender_id']),
            'full_name' => $row['full_name'],
            'age' => (int)$row['age'],
            'city' => $row['city'],
            'dp_url' => $row['dp_url']
        ]
    ];
}

$stmt->close();
$db->close();

echo json_encode([
    'status' => 'success',
    'compliments' => $compliments
]);
