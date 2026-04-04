<?php
require_once __DIR__ . '/../config.php';
$userId = getAuthUserId();
$db = getDB();
$stmt = $db->prepare("
    SELECT u.id, u.full_name, u.age, u.city,
           (SELECT photo_url FROM user_photos
            WHERE user_id = u.id AND is_dp = 1 LIMIT 1) AS dp_url
    FROM blocks b
    JOIN users u ON u.id = b.blocked_user_id
    WHERE b.blocker_id = ?
    ORDER BY b.created_at DESC
");
$stmt->bind_param('i', $userId);
$stmt->execute();
$result = $stmt->get_result();
$users = [];
while ($row = $result->fetch_assoc()) {
    $users[] = [
        'id'        => (int) $row['id'],
        'full_name' => $row['full_name'],
        'age'       => (int) $row['age'],
        'city'      => $row['city'],
        'dp_url'    => $row['dp_url'],
    ];
}
$stmt->close();
$db->close();
echo json_encode(['status' => 'success', 'blocked_users' => $users]);
