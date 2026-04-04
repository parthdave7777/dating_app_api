<?php
// profile/update_location.php — silently update user's current location
require_once __DIR__ . '/../config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'POST only']);
    exit();
}

$userId = getAuthUserId();
$body   = json_decode(file_get_contents('php://input'), true) ?? [];

$latitude  = isset($body['latitude'])  ? (float)$body['latitude']  : null;
$longitude = isset($body['longitude']) ? (float)$body['longitude'] : null;
$city      = isset($body['city']) ? trim($body['city']) : null;

if ($latitude === null || $longitude === null) {
    echo json_encode(['status' => 'error', 'message' => 'latitude and longitude required']);
    exit();
}

// Basic validation — lat must be -90 to 90, lng must be -180 to 180
if ($latitude < -90 || $latitude > 90 || $longitude < -180 || $longitude > 180) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid coordinates']);
    exit();
}

$db = getDB();

if (!empty($city)) {
    $stmt = $db->prepare("UPDATE users SET latitude = ?, longitude = ?, city = ?, last_active = NOW() WHERE id = ?");
    $stmt->bind_param('ddsi', $latitude, $longitude, $city, $userId);
} else {
    $stmt = $db->prepare("UPDATE users SET latitude = ?, longitude = ?, last_active = NOW() WHERE id = ?");
    $stmt->bind_param('ddi', $latitude, $longitude, $userId);
}
$stmt->execute();
$stmt->close();
$db->close();

echo json_encode(['status' => 'success']);
