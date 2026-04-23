<?php
// profile/edit_profile.php

require_once __DIR__ . '/../config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'Method not allowed']);
    exit();
}

$userId = getAuthUserId();
$body   = json_decode(file_get_contents('php://input'), true);

if (!$body) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid JSON body']);
    exit();
}

// 1. Map incoming fields to database columns
$fieldMap = [
    'full_name'            => 's',
    'age'                  => 'i',
    'gender'               => 's',
    'looking_for'          => 's',
    'bio'                  => 's',
    'interests'            => 's',
    'job_title'            => 's',
    'company'              => 's',
    'education'            => 's',
    'height'               => 's',
    'lifestyle_pets'       => 's',
    'lifestyle_drinking'   => 's',
    'lifestyle_smoking'    => 's',
    'lifestyle_workout'    => 's',
    'lifestyle_diet'       => 's',
    'lifestyle_schedule'   => 's',
    'relationship_goal'    => 's',
    'communication_style'  => 's',
    'city'                 => 's',
    'show_in_discovery'    => 'i'
];

$updates = [];
$types   = '';
$params  = [];

// 2. Normalize gender strings if they exist
$genderMap = ['male' => 'man', 'female' => 'woman', 'men' => 'man', 'women' => 'woman'];
if (isset($body['gender']))      $body['gender']      = $genderMap[strtolower(trim($body['gender']))]      ?? trim($body['gender']);
if (isset($body['looking_for'])) $body['looking_for'] = $genderMap[strtolower(trim($body['looking_for']))] ?? trim($body['looking_for']);

// 3. Build dynamic query
foreach ($fieldMap as $apiKey => $type) {
    if (isset($body[$apiKey])) {
        $updates[] = "$apiKey = ?";
        $types .= $type;
        $params[] = $body[$apiKey];
    }
}

if (empty($updates)) {
    echo json_encode(['status' => 'success', 'message' => 'No changes provided']);
    exit();
}

$db = getDB();
$sql = "UPDATE users SET " . implode(', ', $updates) . " WHERE id = ?";
$types .= 'i';
$params[] = $userId;

$stmt = $db->prepare($sql);
if (!$stmt) {
    echo json_encode(['status' => 'error', 'message' => 'Prepare failed: ' . $db->error]);
    exit();
}

$stmt->bind_param($types, ...$params);

if ($stmt->execute()) {
    $stmt->close();
    
    // Return success to the app immediately
    echo json_encode(['status' => 'success', 'message' => 'Profile updated']);
    
    // Background task: clear cache after response is sent
    if (function_exists('fastcgi_finish_request')) {
        fastcgi_finish_request();
    }
    clearProfileCache($userId);
} else {
    echo json_encode(['status' => 'error', 'message' => 'Update failed: ' . $stmt->error]);
}

$db->close();
