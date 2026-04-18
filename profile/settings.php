<?php
require_once __DIR__ . '/../config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'POST only']);
    exit();
}

$userId = getAuthUserId();
$body   = json_decode(file_get_contents('php://input'), true) ?? [];

$db = getDB();

$allowed = [
    'show_in_discovery', 
    'notif_matches', 
    'notif_messages', 
    'notif_likes', 
    'notif_who_swiped',
    'discovery_min_age',
    'discovery_max_age',
    'discovery_max_dist',
    'global_discovery',
    'discovery_min_dist',
    'notif_activity'
];
$updates = [];
$params  = [];
$types   = '';

foreach ($allowed as $field) {
    if (!array_key_exists($field, $body)) continue;
    
    $val = $body[$field];
    
    // Convert true/false booleans to 1/0 for MySQL TINYINT
    if (is_bool($val)) {
        $val = $val ? 1 : 0;
    }
    
    $updates[] = "$field = ?";
    $types .= is_int($val) ? 'i' : 's';
    $params[] = $val;
}

if (empty($updates)) {
    $db->close();
    echo json_encode(['status' => 'success', 'message' => 'No DB fields to update']);
    exit();
}

$sql  = 'UPDATE users SET ' . implode(', ', $updates) . ' WHERE id = ?';
$types .= 'i';
$params[] = $userId;

$stmt = $db->prepare($sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$stmt->close();

// Clear NITRO cache
clearProfileCache($userId);

$db->close();

echo json_encode(['status' => 'success']);
