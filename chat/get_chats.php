<?php
// chat/get_chats.php
require_once __DIR__ . '/../config.php';

$userId = getAuthUserId();

$redis = getRedis();
$cacheKey = "user_chats_$userId";

// 1. NITRO CACHE: Try to serve from Redis first (10s TTL - reduced to feel fresher)
if ($redis) {
    if ($cached = $redis->get($cacheKey)) {
        header('Content-Type: application/json');
        echo $cached;
        exit();
    }
}

$db = getDB();
autoSyncUserMeta($userId, $db);

// NITRO OPTIMIZED: We fetch match details, then batch-fetch messages and users.
// This is significantly faster for large message tables.
$stmt = $db->prepare("
    SELECT 
        m.id AS match_id,
        m.created_at AS match_created,
        CASE WHEN m.user1_id = ? THEN m.user2_id ELSE m.user1_id END AS other_id,
        (SELECT id FROM messages WHERE match_id = m.id ORDER BY id DESC LIMIT 1) as last_msg_id,
        (SELECT COUNT(*) FROM messages WHERE match_id = m.id AND sender_id != ? AND is_read = 0) as unread_count,
        (SELECT MAX(started_at) FROM call_logs WHERE match_id = m.id) as last_call_time
    FROM matches m
    WHERE m.user1_id = ? OR m.user2_id = ?
");
$stmt->bind_param('iiii', $userId, $userId, $userId, $userId);
$stmt->execute();
$matches = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$chats = [];
if (!empty($matches)) {
    // Collect all last_msg_ids for batch fetch
    $lastMsgIds = array_filter(array_column($matches, 'last_msg_id'));
    $msgData = [];
    if (!empty($lastMsgIds)) {
        $ids = implode(',', $lastMsgIds);
        $mRes = $db->query("SELECT id, message, type, sender_id, is_deleted, created_at FROM messages WHERE id IN ($ids)");
        while ($m = $mRes->fetch_assoc()) {
            $msgData[$m['id']] = $m;
        }
    }

    // Collect all other_ids for batch fetch
    $otherIds = array_unique(array_column($matches, 'other_id'));
    $userData = [];
    if (!empty($otherIds)) {
        $uIds = implode(',', $otherIds);
        $uRes = $db->query("
            SELECT u.id, u.full_name, u.age, u.city, u.is_verified,
                   (SELECT photo_url FROM user_photos WHERE user_id = u.id AND is_dp = 1 LIMIT 1) AS photo,
                   (CASE WHEN u.updated_at >= DATE_SUB(NOW(), INTERVAL 5 MINUTE) THEN 1 ELSE 0 END) AS is_online
            FROM users u WHERE u.id IN ($uIds)
        ");
        while ($u = $uRes->fetch_assoc()) {
            $userData[(int)$u['id']] = $u;
        }
    }

    foreach ($matches as $row) {
        $otherId = (int)$row['other_id'];
        $other = $userData[$otherId] ?? [];
        $lastMsgId = $row['last_msg_id'];
        $lm = $lastMsgId ? ($msgData[$lastMsgId] ?? null) : null;
        
        $lastType   = $lm ? $lm['type'] : null;
        $lastSender = $lm ? (int)$lm['sender_id'] : 0;
        $isDeleted  = $lm ? (bool)$lm['is_deleted'] : false;
        $isMine     = ($lastSender === $userId);

        $previewTimeStr = ($lm && $lm['created_at']) ? date('H:i', strtotime($lm['created_at'])) : '';
        $otherName      = ($other['full_name'] ?? 'User');
        $preview = '';

        if ($lastType === 'call_missed') {
            $preview = $isMine ? '📵 Outgoing call missed' : "📵 Missed call from $otherName at $previewTimeStr";
        } elseif ($lastType === 'call_ended' || $lastType === 'call_event') {
            $preview = $isMine ? '📹 You: Video call' : '📹 Video call';
        } elseif ($isDeleted) {
            $preview = $isMine ? 'You deleted a message' : 'Message deleted';
        } elseif ($lastType && strpos($lastType, 'image') !== false) {
            $preview = $isMine ? '📷 You: Sent a photo' : '📷 ' . $otherName . ': Sent a photo';
        } elseif ($lastType && strpos($lastType, 'video') !== false) {
            $preview = $isMine ? '🎥 You: Sent a video' : '🎥 ' . $otherName . ': Sent a video';
        } elseif (!$lm) {
            $preview = 'New match 🎉';
        } else {
            $text    = $lm['message'] ?? '';
            $preview = $isMine ? 'You: ' . $text : $otherName . ': ' . $text;
            if (mb_strlen($preview) > 55) {
                $preview = mb_substr($preview, 0, 52) . '...';
            }
        }

        $chatPhoto = !empty($other['photo']) ? cloudinaryTransform($other['photo'], 'w_150,c_thumb,g_face,q_auto,f_auto') : '';

        $chats[] = [
            'match_id'          => (int)  $row['match_id'],
            'name'              =>        $otherName,
            'photo'             =>        $chatPhoto,
            'is_online'         => (bool) ($other['is_online'] ?? false),
            'is_verified'       => (bool) ($other['is_verified'] ?? false),
            'last_message'      =>        $preview,
            'last_message_time' => ($row['last_call_time'] && strtotime($row['last_call_time']) > strtotime($lm['created_at'] ?? '1970-01-01'))
                                        ? $row['last_call_time'] 
                                        : ($lm['created_at'] ?? $row['match_created']),
            'unread_count'      => (int)  $row['unread_count'],
            'user'              => [
                'id'          => $otherId,
                'full_name'   => $otherName,
                'age'         => (int) ($other['age'] ?? 0),
                'city'        =>       $other['city'] ?? '',
                'is_verified' => (bool)($other['is_verified'] ?? false),
                'dp_url'      =>       $chatPhoto,
            ],
        ];
    }

    // Sort by last activity DESC
    usort($chats, function($a, $b) {
        return strtotime($b['last_message_time']) <=> strtotime($a['last_message_time']);
    });
}

$db->close();
$response = json_encode(['status' => 'success', 'chats' => $chats]);

if ($redis) {
    $redis->setex($cacheKey, 10, $response);
}

echo $response;
