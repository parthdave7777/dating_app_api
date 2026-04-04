<?php
require_once __DIR__ . '/config.php';

$db = getDB();

$userData = [
    [
        'name' => 'Parth Dave',
        'phone' => '+919999999991',
        'gender' => 'Man',
        'looking_for' => 'Woman',
        'age' => 24,
        'bio' => 'Building the future of dating. Tech lover and cricket fan.',
        'interests' => 'Coding,Cricket,Tech,Travel',
        'city' => 'Ahmedabad', 'lat' => 23.0225, 'lng' => 72.5714,
        'photo' => 'dating_app/photos/parth.png',
        'height' => "5'11\"", 'education' => 'B.Tech CS', 'job_title' => 'Developer', 'company' => 'Stitch'
    ],
    [
        'name' => 'Ananya Sharma',
        'phone' => '+919999999992',
        'gender' => 'Woman',
        'looking_for' => 'Man',
        'age' => 22,
        'bio' => 'Artist and designer. Loves coffee and meaningful conversations.',
        'interests' => 'Art,Coffee,Dance,Design',
        'city' => 'Ahmedabad', 'lat' => 23.0226, 'lng' => 72.5715,
        'photo' => 'dating_app/photos/ananya.png',
        'height' => "5'5\"", 'education' => 'MFA', 'job_title' => 'Designer', 'company' => 'Studio'
    ]
];

$ids = [];

echo "--- Resetting and Setting up Premium Test Accounts ---\n";

foreach ($userData as $u) {
    $db->query("DELETE FROM users WHERE phone_number = '" . $u['phone'] . "'");
    
    $sql = "INSERT INTO users (full_name, phone_number, gender, looking_for, age, bio, interests, city, latitude, longitude, height, education, job_title, company, setup_completed, profile_complete, is_verified) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, 1, 1)";
    $stmt = $db->prepare($sql);
    $stmt->bind_param("ssssisssddssss", $u['name'], $u['phone'], $u['gender'], $u['looking_for'], $u['age'], $u['bio'], $u['interests'], $u['city'], $u['lat'], $u['lng'], $u['height'], $u['education'], $u['job_title'], $u['company']);
    $stmt->execute();
    $userId = $db->insert_id;
    $ids[] = $userId;
    
    $photoUrl = "uploads/" . $u['photo'];
    $db->query("INSERT INTO user_photos (user_id, photo_url, is_dp, is_verified) VALUES ($userId, '$photoUrl', 1, 1)");
    $db->query("DELETE FROM otp_codes WHERE phone_number = '" . $u['phone'] . "'");
    $db->query("INSERT INTO otp_codes (phone_number, otp, expires_at) VALUES ('" . $u['phone'] . "', '123456', DATE_ADD(NOW(), INTERVAL 1 YEAR))");

    echo "Created: " . $u['name'] . " (ID: $userId)\n";
}

if (count($ids) == 2) {
    $u1 = $ids[0];
    $u2 = $ids[1];
    $db->query("DELETE FROM matches WHERE (user1_id = $u1 AND user2_id = $u2) OR (user1_id = $u2 AND user2_id = $u1)");
    $db->query("INSERT INTO matches (user1_id, user2_id) VALUES ($u1, $u2)");
    $db->query("DELETE FROM swipes WHERE (swiper_id = $u1 AND swiped_id = $u2) OR (swiper_id = $u2 AND swiped_id = $u1)");
    $db->query("INSERT INTO swipes (swiper_id, swiped_id, action) VALUES ($u1, $u2, 'like')");
    $db->query("INSERT INTO swipes (swiper_id, swiped_id, action) VALUES ($u2, $u1, 'like')");
    echo "--- Match Created: Parth and Ananya matched! ---\n";
}
