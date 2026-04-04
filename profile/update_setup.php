<?php
// profile/update_setup.php
header('Content-Type: application/json');
require_once __DIR__ . '/../config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'Method not allowed']);
    exit();
}

$userId = getAuthUserId();
if (!$userId) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit();
}

$inputData = json_decode(file_get_contents("php://input"), true);
if (!$inputData) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid JSON input']);
    exit();
}

/**
 * Expected fields:
 * - full_name (string)
 * - age (int)
 * - gender (string)
 * - relationship_goal (string)
 * - lifestyle_drinking (string)
 * - lifestyle_smoking (string)
 * - lifestyle_workout (string)
 * - communication_style (string)
 * - interests (array)
 */

$fullName = trim($inputData['full_name'] ?? '');
$age = (int) ($inputData['age'] ?? 0);
$gender = trim($inputData['gender'] ?? '');
$goal = trim($inputData['relationship_goal'] ?? '');
$drinking = trim($inputData['lifestyle_drinking'] ?? '');
$smoking = trim($inputData['lifestyle_smoking'] ?? '');
$workout = trim($inputData['lifestyle_workout'] ?? '');
$commStyle = trim($inputData['communication_style'] ?? '');
$interests = is_array($inputData['interests'] ?? null) ? implode(',', $inputData['interests']) : '';

if (empty($fullName) || $age < 18 || empty($gender)) {
    echo json_encode(['status' => 'error', 'message' => 'Basic info (Name, Age, Gender) is required and must be valid.']);
    exit();
}

$db = getDB();

$stmt = $db->prepare("UPDATE users SET 
    full_name = ?, age = ?, gender = ?, 
    relationship_goal = ?, lifestyle_drinking = ?, 
    lifestyle_smoking = ?, lifestyle_workout = ?, 
    communication_style = ?, interests = ?,
    setup_completed = 1,
    profile_complete = 1
    WHERE id = ?");

$stmt->bind_param('sisssssssi', 
    $fullName, $age, $gender, 
    $goal, $drinking, $smoking, $workout, 
    $commStyle, $interests, $userId
);

if ($stmt->execute()) {
    echo json_encode(['status' => 'success', 'message' => 'Profile updated successfully']);
} else {
    echo json_encode(['status' => 'error', 'message' => 'Database error: ' . $stmt->error]);
}

$stmt->close();
$db->close();
?>
