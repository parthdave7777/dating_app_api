<?php
// profile/edit_profile.php

require_once __DIR__ . '/../config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'Method not allowed']);
    exit();
}

$userId = getAuthUserId();
$body   = json_decode(file_get_contents('php://input'), true);

$fullName        = trim($body['full_name']        ?? '');
$age             = (int)   ($body['age']             ?? 0);
$genderRaw       = strtolower(trim($body['gender']   ?? ''));
$lookingForRaw   = strtolower(trim($body['looking_for'] ?? ''));

// Normalize gender strings
$genderMap = [
    'male'   => 'man',
    'female' => 'woman',
    'men'    => 'man',
    'women'  => 'woman'
];

$gender      = $genderMap[$genderRaw]     ?? $genderRaw;
$lookingFor  = $genderMap[$lookingForRaw] ?? $lookingForRaw;

$bio        = trim($body['bio']         ?? '');
$interests  = trim($body['interests']   ?? '');

// NEW FIELDS
$jobTitle    = trim($body['job_title']    ?? '');
$company     = trim($body['company']      ?? '');
$education   = trim($body['education']    ?? '');
$height      = trim($body['height']       ?? '');
$pets        = trim($body['lifestyle_pets']    ?? '');
$smoking     = trim($body['lifestyle_smoking'] ?? '');
$workout     = trim($body['lifestyle_workout'] ?? '');
$diet        = trim($body['lifestyle_diet']    ?? '');
$schedule    = trim($body['lifestyle_schedule'] ?? '');
$goal        = trim($body['relationship_goal']  ?? '');
$commStyle   = trim($body['communication_style']?? '');

if (empty($fullName)) {
    echo json_encode(['status' => 'error', 'message' => 'full_name is required']);
    exit();
}

$db   = getDB();
$stmt = $db->prepare("
    UPDATE users SET 
        full_name = ?, 
        age = ?, 
        gender = ?,
        looking_for = ?, 
        bio = ?, 
        interests = ?,
        job_title = ?,
        company = ?,
        lifestyle_pets = ?,
        lifestyle_smoking = ?,
        lifestyle_drinking = ?,
        lifestyle_workout = ?,
        lifestyle_diet = ?,
        lifestyle_schedule = ?,
        relationship_goal = ?,
        communication_style = ?
    WHERE id = ?
");
$stmt->bind_param('sissssssssssssssi', 
    $fullName, $age, $gender, $lookingFor, $bio, $interests, 
    $jobTitle, $company,
    $pets, $smoking, $drinking, $workout, $diet, $schedule,
    $goal, $commStyle,
    $userId
);
$stmt->execute();
$stmt->close();
$db->close();

echo json_encode(['status' => 'success', 'message' => 'Profile updated']);
