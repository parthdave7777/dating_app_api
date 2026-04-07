<?php
// profile/setup.php
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

$bio             = trim($body['bio']             ?? '');
$interests       = trim($body['interests']       ?? '');
$height          = trim($body['height']          ?? '');
$jobTitle        = trim($body['job_title']       ?? '');
$company         = trim($body['company']         ?? '');
$pets            = trim($body['lifestyle_pets']  ?? '');
$drinking        = trim($body['lifestyle_drinking'] ?? '');
$smoking         = trim($body['lifestyle_smoking']  ?? '');
$workout         = trim($body['lifestyle_workout']  ?? '');
$diet            = trim($body['lifestyle_diet']     ?? '');
$schedule        = trim($body['lifestyle_schedule'] ?? '');
$commStyle       = trim($body['communication_style'] ?? '');
$relationshipGoal = trim($body['relationship_goal'] ?? '');
$latitude        = isset($body['latitude'])  ? (float) $body['latitude']  : 0.0;
$longitude       = isset($body['longitude']) ? (float) $body['longitude'] : 0.0;
$city            = trim($body['city']            ?? '');

if (empty($fullName) || $age < 18 || empty($gender) || empty($lookingFor)) {
    echo json_encode(['status' => 'error', 'message' => 'Required fields missing (full_name, age ≥18, gender, looking_for)']);
    exit();
}

$db = getDB();

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
        lifestyle_drinking = ?, 
        lifestyle_smoking = ?,
        lifestyle_workout = ?, 
        lifestyle_diet = ?, 
        lifestyle_schedule = ?,
        relationship_goal = ?, 
        communication_style = ?, 
        latitude = ?,
        longitude = ?, 
        city = ?, 
        setup_completed = 1,
        profile_complete = 1
    WHERE id = ?
");

if (!$stmt) {
    echo json_encode(['status' => 'error', 'message' => 'Query preparation failed: ' . $db->error]);
    exit();
}

$stmt->bind_param(
    'sisssssssssssssddsi',
    $fullName, $age, $gender, $lookingFor, $bio,
    $interests, $jobTitle,
    $company, $pets, $drinking, $smoking,
    $workout, $diet, $schedule,
    $relationshipGoal, $commStyle, $latitude,
    $longitude, $city, $userId
);

if ($stmt->execute()) {
    echo json_encode(['status' => 'success', 'message' => 'Profile setup complete']);
} else {
    echo json_encode(['status' => 'error', 'message' => 'Execution failed: ' . $stmt->error]);
}

$stmt->close();
$db->close();
?>
