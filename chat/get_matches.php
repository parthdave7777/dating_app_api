<?php
// chat/get_matches.php
require_once __DIR__ . '/../config.php';

$userId = getAuthUserId();
$db = getDB();

// Find matches where NO messages have been sent yet.
// These are "New Matches" for the top carousel.
$stmt = $db->prepare("
    SELECT 
        m.id AS match_id,
        m.created_at AS match_created,
        CASE WHEN m.user1_id = ? THEN m.user2_id ELSE m.user1_id END AS other_id
    FROM matches m
    WHERE (m.user1_id = ? OR m.user2_id = ?)
    AND NOT EXISTS (
        SELECT 1 FROM messages msg WHERE msg.match_id = m.id
    )
    ORDER BY m.created_at DESC
    LIMIT 20
");

$stmt->bind_param('iii', $userId, $userId, $userId);
$stmt->execute();
$result = $stmt->get_result();
$stmt->close();

$matches = [];
while ($row = $result->fetch_assoc()) {
    $otherId = (int) $row['other_id'];
    
    // Get other user's info
    $uStmt = $db->prepare("
        SELECT u.id, u.full_name, u.age, u.is_verified,
               (SELECT photo_url FROM user_photos WHERE user_id = u.id AND is_dp = 1 LIMIT 1) AS photo
        FROM users u WHERE u.id = ?
    ");
    $uStmt->bind_param('i', $otherId);
    $uStmt->execute();
    $uRow = $uStmt->get_result()->fetch_assoc();
    $uStmt->close();
    
    if ($uRow) {
        $photo = !empty($uRow['photo']) ? cloudinaryTransform($uRow['photo'], 'w_200,c_thumb,g_face,q_auto,f_auto') : '';
        
        $matches[] = [
            'match_id' => (int) $row['match_id'],
            'created_at' => $row['match_created'],
            'user' => [
                'id' => (int) $uRow['id'],
                'full_name' => $uRow['full_name'] ?? 'User',
                'age' => (int) ($uRow['age'] ?? 0),
                'is_verified' => (bool) ($uRow['is_verified'] ?? false),
                'dp_url' => $photo,
            ]
        ];
    }
}

$db->close();
echo json_encode(['status' => 'success', 'matches' => $matches]);
