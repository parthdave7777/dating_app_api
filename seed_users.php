<?php
require_once __DIR__ . '/config.php';
$db = getDB();

// Increase execution time for 300 users
set_time_limit(300);

$usersFile = 'c:/Users/dpart/Desktop/date/users.csv';
$photosFile = 'c:/Users/dpart/Desktop/date/user_photos.csv';

if (!file_exists($usersFile)) {
    die(json_encode(['status'=>'error', 'message'=>'users.csv not found at ' . $usersFile]));
}

echo "--- STARTING SEEDING ---\n";

// 1. Clear existing test data if needed (OPTIONAL - COMMENT OUT IF NOT WANTED)
// $db->query("DELETE FROM user_photos WHERE user_id > 10");
// $db->query("DELETE FROM users WHERE id > 10");

// 2. Load Users
$handle = fopen($usersFile, "r");
$header = fgetcsv($handle); // Skip header

$count = 0;
while (($data = fgetcsv($handle)) !== FALSE) {
    // [id, full_name, email, password, gender, dob, bio, hobbies, height, lat, lng, is_active, is_verified, fcm_token]
    $stmt = $db->prepare("INSERT IGNORE INTO users (id, full_name, email, password, gender, dob, bio, hobbies, height, lat, lng, is_active, is_verified) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $hashedPass = password_hash($data[3], PASSWORD_DEFAULT);
    $stmt->bind_param("issssssssddii", 
        $data[0], $data[1], $data[2], $hashedPass, $data[4], $data[5], $data[6], $data[7], $data[8], $data[9], $data[10], $data[11], $data[12]
    );
    if ($stmt->execute()) $count++;
    $stmt->close();
}
fclose($handle);
echo "Imported $count users.\n";

// 3. Load Photos
$handle = fopen($photosFile, "r");
$header = fgetcsv($handle); // Skip header

$pCount = 0;
while (($data = fgetcsv($handle)) !== FALSE) {
    // [id, user_id, photo_url, is_dp]
    $stmt = $db->prepare("INSERT IGNORE INTO user_photos (id, user_id, photo_url, is_dp) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("iisi", $data[0], $data[1], $data[2], $data[3]);
    if ($stmt->execute()) $pCount++;
    $stmt->close();
}
fclose($handle);
echo "Imported $pCount photos.\n";

echo "--- SEEDING COMPLETE ---";
$db->close();
?>
