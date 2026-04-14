<?php
// profile/matches.php
require_once __DIR__ . '/../config.php';

$userId = getAuthUserId();
$db     = getDB();

$stmt = $db->prepare("
    SELECT
        m.id AS match_id,
        CONCAT(REPLACE(m.created_at, ' ', 'T'), '+05:30') as created_at,
        CASE WHEN m.user1_id = ? THEN m.user2_id ELSE m.user1_id END AS other_user_id
    FROM matches m
    WHERE m.user1_id = ? OR m.user2_id = ?
    ORDER BY m.created_at DESC
");
$stmt->bind_param('iii', $userId, $userId, $userId);
$stmt->execute();
$result = $stmt->get_result();
$stmt->close();

$rows = [];
$otherIds = [];
while ($row = $result->fetch_assoc()) {
    $rows[] = $row;
    $otherIds[] = (int)$row['other_user_id'];
}

$others = [];
if (!empty($otherIds)) {
    $idList = implode(',', $otherIds);
    $uRes = $db->query("
        SELECT u.id, u.full_name, u.age, u.city, u.is_verified, u.last_active,
               (SELECT photo_url FROM user_photos WHERE user_id = u.id AND is_dp = 1 LIMIT 1) AS dp_url
        FROM users u WHERE id IN ($idList)
    ");
    while ($u = $uRes->fetch_assoc()) {
        $others[(int)$u['id']] = $u;
    }
}

$matches = [];
foreach ($rows as $row) {
    $otherId = (int)$row['other_user_id'];
    $other = $others[$otherId] ?? null;
    if (!$other) continue;

    $matches[] = [
        'match_id'   => (int)$row['match_id'],
        'matched_at' => $row['created_at'],
        'user'       => [
            'id'          => $otherId,
            'full_name'   => $other['full_name'],
            'age'         => (int)$other['age'],
            'city'        => $other['city'],
            'is_verified' => (bool)$other['is_verified'],
            'is_active_now' => (strtotime($other['last_active'] ?? '') > (time() - 300)),
            // Optimized DP thumbnail for match list
            'dp_url'      => cloudinaryTransform($other['dp_url'] ?? '', 'w_300,c_thumb,g_face,q_auto,f_auto'),
        ],
    ];
}

$db->close();
echo json_encode(['status' => 'success', 'matches' => $matches]);
