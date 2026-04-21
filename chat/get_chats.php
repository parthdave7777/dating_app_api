<?php
// chat/get_chats.php
require_once __DIR__ . '/../config.php';

$userId = getAuthUserId();

$redis = getRedis();
$cacheKey = "user_chats_$userId";

// 1. NITRO CACHE: Try to serve from Redis first (15s TTL)
if ($redis) {
    if ($cached = $redis->get($cacheKey)) {
        header('Content-Type: application/json');
        echo $cached;
        exit();
    }
}

$db = getDB();
autoSyncUserMeta($userId, $db);

// Single optimised query using pre-aggregated JOINs
$stmt = $db->prepare("
    SELECT 
        m.id AS match_id,
        m.created_at AS match_created,
        CASE WHEN m.user1_id = ? THEN m.user2_id ELSE m.user1_id END AS other_id,
        lm.message AS last_message,
        lm.type AS last_message_type,
        lm.sender_id AS last_sender_id,
        lm.is_deleted AS last_is_deleted,
        lm.created_at AS last_message_time,
        lc.started_at AS last_call_time,
        COALESCE(uc.count, 0) AS unread_count
    FROM matches m
    LEFT JOIN (
        SELECT msg.match_id, msg.message, msg.type, msg.sender_id, msg.is_deleted, msg.created_at
        FROM messages msg
        INNER JOIN (
            SELECT match_id, MAX(id) AS max_id FROM messages GROUP BY match_id
        ) latest ON msg.id = latest.max_id
    ) lm ON lm.match_id = m.id
    LEFT JOIN (
        SELECT match_id, MAX(started_at) AS started_at FROM call_logs GROUP BY match_id
    ) lc ON lc.match_id = m.id
    LEFT JOIN (
        SELECT match_id, COUNT(*) AS count FROM messages WHERE sender_id != ? AND is_read = 0 GROUP BY match_id
    ) uc ON uc.match_id = m.id
    WHERE m.user1_id = ? OR m.user2_id = ?
    ORDER BY GREATEST(
        COALESCE(lm.created_at, m.created_at),
        COALESCE(lc.started_at, m.created_at)
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

    $previewTimeStr = $row['last_message_time'] ? date('H:i', strtotime($row['last_message_time'])) : '';
    $otherName      = (isset($other['full_name']) && $other['full_name'] != '') ? $other['full_name'] : 'User';

    if ($lastType === 'call_missed') {
        $text = "Missed call at $previewTimeStr";
        // If IS NOT mine, it means I received it and missed it.
        $preview = $isMine ? '📵 Outgoing call missed' : "📵 Missed call from $otherName at $previewTimeStr";
    } elseif ($lastType === 'call_ended' || $lastType === 'call_event') {
        $text = "Video call";
        $preview = $isMine ? '📹 You: ' . $text : '📹 ' . $text;
    } elseif ($isDeleted) {
        $preview = $isMine ? 'You deleted a message' : 'Message deleted';
    } elseif (strpos($lastType, 'image') !== false) {
        $text = (strpos($lastType, 'opened') !== false) ? 'Photo opened' : 'Sent a photo';
        $preview = $isMine ? '📷 You: ' . $text : '📷 ' . $otherName . ': ' . $text;
    } elseif (strpos($lastType, 'video') !== false) {
        $text = (strpos($lastType, 'opened') !== false) ? 'Video opened' : 'Sent a video';
        $preview = $isMine ? '🎥 You: ' . $text : '🎥 ' . $otherName . ': ' . $text;
    } elseif (empty($row['last_message'])) {
        $preview = 'New match 🎉';
    } else {
        $text    = $row['last_message'];
        $preview = $isMine ? 'You: ' . $text : $otherName . ': ' . $text;
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
$response = json_encode(['status' => 'success', 'chats' => $chats]);

// Save to NITRO cache
if ($redis) {
    $redis->setex($cacheKey, 15, $response);
}

echo $response;
