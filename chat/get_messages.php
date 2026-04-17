<?php
// chat/get_messages.php
require_once __DIR__ . '/../config.php';

// BUG FIX: Increase time limit for long polling; ignore client disconnects so
// the response is still sent even if the app briefly goes to background.
set_time_limit(35);
ignore_user_abort(true);

$userId   = getAuthUserId();
$matchId  = (int) ($_GET['match_id']  ?? 0);
$lastId   = (int) ($_GET['last_id']   ?? 0);
$beforeId = isset($_GET['before_id']) ? (int) $_GET['before_id'] : null;

if (!$matchId) {
    echo json_encode(['status' => 'error', 'message' => 'match_id required']);
    exit();
}

$db = getDB();

// BUG FIX: Set transaction isolation level to READ COMMITTED.
// Without this, InnoDB's default REPEATABLE READ snapshot would prevent
// the long-polling loop from seeing status changes (like is_read) made by 
// the other user in a different session until the script restarts.
$db->query("SET SESSION TRANSACTION ISOLATION LEVEL READ COMMITTED");

// Verify access
$authStmt = $db->prepare(
    "SELECT user1_id, user2_id FROM matches WHERE id = ? AND (user1_id = ? OR user2_id = ?)"
);
$authStmt->bind_param('iii', $matchId, $userId, $userId);
$authStmt->execute();
$matchRow = $authStmt->get_result()->fetch_assoc();
$authStmt->close();

if (!$matchRow) {
    echo json_encode(['status' => 'error', 'message' => 'Access denied']);
    $db->close();
    exit();
}

$otherId = ((int)$matchRow['user1_id'] === $userId)
    ? (int)$matchRow['user2_id']
    : (int)$matchRow['user1_id'];

// ── PAGINATED HISTORY (load older messages) ──────────────────
if ($beforeId !== null) {
    echo json_encode(buildBeforeResponse($db, $matchId, $userId, $otherId, $beforeId));
    $db->close();
    exit();
}

// ── FIRST LOAD ───────────────────────────────────────────────
if ($lastId === 0) {
    echo json_encode(buildLatestResponse($db, $matchId, $userId, $otherId));
    $db->close();
    exit();
}

// ── NO LONG POLL ─────────────────────────────────────────────
// We are now using WebSockets for real-time. 
// This script now only does a SINGLE FAST CHECK and returns.
// This prevents "Empty Responses" and "10s Delays" in Adminer.

$newStmt = $db->prepare(
    "SELECT COUNT(*) as cnt FROM messages WHERE match_id = ? AND id > ?"
);
$newStmt->bind_param('ii', $matchId, $lastId);
$newStmt->execute();
$newCnt = (int)$newStmt->get_result()->fetch_assoc()['cnt'];
$newStmt->close();

if ($newCnt > 0) {
    echo json_encode(buildResponse($db, $matchId, $userId, $otherId, $lastId));
} else {
    // Check for read status changes only
    $currentHash = getReadHash($db, $matchId);
    if ($currentHash !== $lastReadHash) {
        echo json_encode(buildFullResponse($db, $matchId, $userId, $otherId));
    } else {
        echo json_encode([
            'status'     => 'success',
            'messages'   => [],
            'other_user' => getOtherUser($db, $otherId),
            'timeout'    => false,
        ]);
    }
}

$db->close();
exit();

// ── Helpers ──────────────────────────────────────────────────

function getReadHash(mysqli $db, int $matchId): string {
    $stmt = $db->prepare(
      "SELECT MAX(id), SUM(is_read), SUM(is_received), SUM(is_deleted), SUM(is_edited) 
       FROM messages WHERE match_id = ?"
    );
    $stmt->bind_param('i', $matchId);
    $stmt->execute();
    $row  = $stmt->get_result()->fetch_row();
    $stmt->close();
    return md5(implode('|', $row));
}

// ── Returns latest 25 messages (first load) ──────────────────
function buildLatestResponse(mysqli $db, int $matchId, int $userId, int $otherId): array {
    markReceived($db, $matchId, $userId);
    markRead($db, $matchId, $userId);

    $stmt = $db->prepare("
        SELECT m.id, m.sender_id, m.message, m.type,
               m.is_read, m.is_received, m.is_opened, m.is_view_once,
               m.is_deleted, m.is_edited, m.deleted_by, CONCAT(REPLACE(m.created_at, ' ', 'T'), '+05:30') as created_at,
               m.call_event, m.duration, m.reply_to_id, rm.message AS reply_text
        FROM messages m
        LEFT JOIN messages rm ON rm.id = m.reply_to_id
        WHERE m.match_id = ?
        ORDER BY m.id DESC
        LIMIT 25
    ");
    $stmt->bind_param('i', $matchId);
    $stmt->execute();
    $result = $stmt->get_result();
    $stmt->close();

    $messages = buildMessageArray($result);
    // Reverse so oldest-first order is preserved for the client
    $messages = array_reverse($messages);

    return [
        'status'     => 'success',
        'messages'   => $messages,
        'other_user' => getOtherUser($db, $otherId),
        'timeout'    => false,
    ];
}

// ── Returns up to 25 messages older than $beforeId (pagination) ─
function buildBeforeResponse(mysqli $db, int $matchId, int $userId, int $otherId, int $beforeId): array {
    markReceived($db, $matchId, $userId);
    markRead($db, $matchId, $userId);

    $stmt = $db->prepare("
        SELECT m.id, m.sender_id, m.message, m.type,
               m.is_read, m.is_received, m.is_opened, m.is_view_once,
               m.is_deleted, m.is_edited, m.deleted_by, CONCAT(REPLACE(m.created_at, ' ', 'T'), '+05:30') as created_at,
               m.call_event, m.duration, m.reply_to_id, rm.message AS reply_text
        FROM messages m
        LEFT JOIN messages rm ON rm.id = m.reply_to_id
        WHERE m.match_id = ? AND m.id < ?
        ORDER BY m.id DESC
        LIMIT 25
    ");
    $stmt->bind_param('ii', $matchId, $beforeId);
    $stmt->execute();
    $result = $stmt->get_result();
    $stmt->close();

    $messages = buildMessageArray($result);
    // Reverse so oldest-first order is preserved for the client
    $messages = array_reverse($messages);

    return [
        'status'     => 'success',
        'messages'   => $messages,
        'other_user' => getOtherUser($db, $otherId),
        'timeout'    => false,
    ];
}

// ── Long-poll response: messages newer than $sinceId ─────────
function buildResponse(mysqli $db, int $matchId, int $userId, int $otherId, int $sinceId): array {
    markReceived($db, $matchId, $userId);
    markRead($db, $matchId, $userId);

    $stmt = $db->prepare("
        SELECT m.id, m.sender_id, m.message, m.type,
               m.is_read, m.is_received, m.is_opened, m.is_view_once,
               m.is_deleted, m.is_edited, m.deleted_by, CONCAT(REPLACE(m.created_at, ' ', 'T'), '+05:30') as created_at,
               m.call_event, m.duration, m.reply_to_id, rm.message AS reply_text
        FROM messages m
        LEFT JOIN messages rm ON rm.id = m.reply_to_id
        WHERE m.match_id = ? AND m.id > ?
        ORDER BY m.id ASC
        LIMIT 100
    ");
    $stmt->bind_param('ii', $matchId, $sinceId);
    $stmt->execute();
    $result = $stmt->get_result();
    $stmt->close();

    return [
        'status'     => 'success',
        'messages'   => buildMessageArray($result),
        'other_user' => getOtherUser($db, $otherId),
        'timeout'    => false,
    ];
}

function markRead(mysqli $db, int $matchId, int $userId): void {
    $now = date('Y-m-d H:i:s');
    $readStmt = $db->prepare(
        "UPDATE messages SET is_read = 1, read_at = IFNULL(read_at, ?), is_received = 1, received_at = IFNULL(received_at, ?) 
         WHERE match_id = ? AND sender_id != ? AND is_read = 0"
    );
    $readStmt->bind_param('ssii', $now, $now, $matchId, $userId);
    $readStmt->execute();
    $readStmt->close();
}

function markReceived(mysqli $db, int $matchId, int $userId): void {
    $now = date('Y-m-d H:i:s');
    $recStmt = $db->prepare(
        "UPDATE messages SET is_received = 1, received_at = IFNULL(received_at, ?) 
         WHERE match_id = ? AND sender_id != ? AND is_received = 0"
    );
    $recStmt->bind_param('sii', $now, $matchId, $userId);
    $recStmt->execute();
    $recStmt->close();
}

function buildFullResponse(mysqli $db, int $matchId, int $userId, int $otherId): array {
    markReceived($db, $matchId, $userId);
    markRead($db, $matchId, $userId);

    $stmt = $db->prepare("
        SELECT m.id, m.sender_id, m.message, m.type,
               m.is_read, m.is_received, m.is_opened, m.is_view_once,
               m.is_deleted, m.is_edited, m.deleted_by, CONCAT(REPLACE(m.created_at, ' ', 'T'), '+05:30') as created_at,
               m.call_event, m.duration, m.reply_to_id, rm.message AS reply_text
        FROM messages m
        LEFT JOIN messages rm ON rm.id = m.reply_to_id
        WHERE m.match_id = ?
        ORDER BY m.id DESC
        LIMIT 200
    ");
    $stmt->bind_param('i', $matchId);
    $stmt->execute();
    $result = $stmt->get_result();
    $stmt->close();

    $messages = buildMessageArray($result);
    // Reverse for oldest-first UI
    $messages = array_reverse($messages);

    return [
        'status'      => 'success',
        'messages'    => $messages,
        'other_user'  => getOtherUser($db, $otherId),
        'timeout'     => false,
        'full_reload' => true,
    ];
}

function buildMessageArray($result): array {
    $messages = [];
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $isDeleted = (bool)$row['is_deleted'];
            $isOpened  = (bool)($row['is_opened'] ?? 0);
            $msg       = $row['message'] ?? '';
            
            if ($isDeleted) {
                $msg = 'This message was deleted';
            } else if ($isOpened) {
                $msg = 'OPENED';
            }

            $messages[] = [
                'id'           => (int)  $row['id'],
                'sender_id'    => (int)  $row['sender_id'],
                'message'      => $msg,
                'type'         =>        $row['type'] ?? 'text',
                'is_read'      => (bool) ($row['is_read'] ?? 0),
                'is_received'  => (bool) ($row['is_received'] ?? 0),
                'is_opened'    => $isOpened,
                'is_view_once' => (bool) ($row['is_view_once'] ?? 0),
                'is_deleted'   => $isDeleted,
                'is_edited'    => (bool) ($row['is_edited'] ?? 0),
                'deleted_by'   => (int)  ($row['deleted_by'] ?? 0),
                'created_at'   =>        $row['created_at'],
                'reply_to_id'  => isset($row['reply_to_id']) ? (int)$row['reply_to_id'] : null,
                'reply_text'   => $row['reply_text'] ?? null,
                'call_event'   =>        $row['call_event'] ?? null,
                'duration'     => isset($row['duration']) ? (int)$row['duration'] : null,
            ];
        }
    }
    return $messages;
}

function getOtherUser(mysqli $db, int $otherId): array {
    // SOLID & FAST: Single query with JOIN for photo
    $stmt = $db->prepare("
        SELECT u.id, u.full_name, up.photo_url,
               (CASE WHEN u.updated_at >= DATE_SUB(NOW(), INTERVAL 5 MINUTE) THEN 1 ELSE 0 END) AS is_online
        FROM users u 
        LEFT JOIN user_photos up ON up.user_id = u.id AND up.is_dp = 1
        WHERE u.id = ? 
        LIMIT 1
    ");
    $stmt->bind_param('i', $otherId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$row) return ['id' => $otherId, 'full_name' => 'User', 'photo_url' => '', 'photo' => ''];

    $photo = !empty($row['photo_url']) ? cloudinaryTransform($row['photo_url'], 'w_150,c_thumb,g_face,q_auto,f_auto') : '';
    return [
        'id'        => (int) $row['id'],
        'full_name' =>       $row['full_name'] ?? 'User',
        'photo_url' =>       $photo,
        'photo'     =>       $photo,
        'dp_url'    =>       $photo,
        'is_online' => (bool) ($row['is_online'] ?? false),
    ];
}
