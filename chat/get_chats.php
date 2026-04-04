<?php
// chat/get_chats.php
require_once __DIR__ . '/../config.php';

$userId = getAuthUserId();

$db = getDB();
$db->query("UPDATE users SET updated_at = NOW() WHERE id = $userId");

// Single optimised query — no per-row sub-queries
$stmt = $db->prepare("
    SELECT * FROM (
        SELECT
            m.id AS match_id,
            m.created_at AS match_created,
            CASE WHEN m.user1_id = ? THEN m.user2_id ELSE m.user1_id END AS other_id,
            (SELECT msg.message    FROM messages msg WHERE msg.match_id = m.id ORDER BY msg.id DESC LIMIT 1) AS last_message,
            (SELECT msg.type       FROM messages msg WHERE msg.match_id = m.id ORDER BY msg.id DESC LIMIT 1) AS last_message_type,
            (SELECT msg.sender_id  FROM messages msg WHERE msg.match_id = m.id ORDER BY msg.id DESC LIMIT 1) AS last_sender_id,
            (SELECT msg.is_deleted FROM messages msg WHERE msg.match_id = m.id ORDER BY msg.id DESC LIMIT 1) AS last_is_deleted,
            (SELECT msg.created_at FROM messages msg WHERE msg.match_id = m.id ORDER BY msg.id DESC LIMIT 1) AS last_message_time,
            (SELECT cl.started_at  FROM call_logs cl WHERE cl.match_id = m.id ORDER BY cl.id DESC LIMIT 1) AS last_call_time,
            (SELECT COUNT(*) FROM messages msg
             WHERE msg.match_id = m.id AND msg.sender_id != ? AND msg.is_read = 0) AS unread_count
        FROM matches m
        WHERE m.user1_id = ? OR m.user2_id = ?
    ) as chat_data
    ORDER BY GREATEST(
        COALESCE(last_message_time, match_created),
        COALESCE(last_call_time, match_created)
    ) DESC
");
$stmt->bind_param('iiii', $userId, $userId, $userId, $userId);
$stmt->execute();
$result = $stmt->get_result();
$stmt->close();

// Collect all other_ids first, then batch fetch users
$rows   = [];
$others = [];
while ($row = $result->fetch_assoc()) {
    $rows[]               = $row;
    $others[(int)$row['other_id']] = null;
}

// Batch fetch all other users in one query
if (!empty($others)) {
    $ids      = implode(',', array_keys($others));
    $uResult  = $db->query("
        SELECT u.id, u.full_name, u.age, u.city, u.is_verified,
               (SELECT photo_url FROM user_photos WHERE user_id = u.id AND is_dp = 1 LIMIT 1) AS photo,
               (CASE WHEN u.updated_at >= DATE_SUB(NOW(), INTERVAL 5 MINUTE) THEN 1 ELSE 0 END) AS is_online
        FROM users u
        WHERE u.id IN ($ids)
    ");
    while ($u = $uResult->fetch_assoc()) {
        $others[(int)$u['id']] = $u;
    }
}

$chats = [];
foreach ($rows as $row) {
    $otherId    = (int)  $row['other_id'];
    $other      = $others[$otherId] ?? [];
    $lastType   =        $row['last_message_type'];
    $lastSender = (int)  $row['last_sender_id'];
    $isDeleted  = (bool) $row['last_is_deleted'];
    $isMine     = ($lastSender === $userId);

    if ($lastType === 'call_missed') {
        $preview = $isMine ? 'You: 📵 Missed call' : '📵 Missed call';
    } elseif ($lastType === 'call_ended' || $lastType === 'call_event') {
        $preview = $isMine ? 'You: 📹 Video call' : '📹 Video call';
    } elseif ($isDeleted) {
        $preview = $isMine ? 'You: This message was deleted' : 'This message was deleted';
    } elseif (strpos($lastType, 'image') !== false) {
        $text = (strpos($lastType, 'opened') !== false) ? '📷 Photo opened' : '📷 Photo';
        $preview = $isMine ? 'You: ' . $text : $text;
    } elseif (strpos($lastType, 'video') !== false) {
        $text = (strpos($lastType, 'opened') !== false) ? '🎥 Video opened' : '🎥 Video';
        $preview = $isMine ? 'You: ' . $text : $text;
    } elseif (empty($row['last_message'])) {
        $preview = 'New match 🎉';
    } else {
        $text    = $row['last_message'];
        $preview = $isMine ? 'You: ' . $text : $text;
        if (mb_strlen($preview) > 55) {
            $preview = mb_substr($preview, 0, 52) . '...';
        }
    }

    $chatPhoto = !empty($other['photo']) ? cloudinaryTransform($other['photo'], 'w_150,c_thumb,g_face,q_auto,f_auto') : '';

    $chats[] = [
        'match_id'          => (int)  $row['match_id'],
        'name'              =>        $other['full_name'] ?? 'User',
        'photo'             =>        $chatPhoto,
        'is_online'         => (bool) ($other['is_online'] ?? false),
        'is_verified'       => (bool) ($other['is_verified'] ?? false),
        'last_message'      =>        $preview,
        'last_message_time' => ($row['last_call_time'] && strtotime($row['last_call_time']) > strtotime($row['last_message_time'] ?? '1970-01-01'))
                                    ? $row['last_call_time'] 
                                    : $row['last_message_time'],
        'unread_count'      => (int)  $row['unread_count'],
        'user'              => [
            'id'          => $otherId,
            'full_name'   => $other['full_name'] ?? 'User',
            'age'         => (int) ($other['age'] ?? 0),
            'city'        =>       $other['city'] ?? '',
            'is_verified' => (bool)($other['is_verified'] ?? false),
            'dp_url'      =>       $chatPhoto,
        ],
    ];
}

$db->close();
echo json_encode(['status' => 'success', 'chats' => $chats]);
