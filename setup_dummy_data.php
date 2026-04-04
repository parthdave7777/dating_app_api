<?php
// setup_dummy_data.php
require_once __DIR__ . '/config.php';

$db = getDB();

// 1. Array of dummy users
$dummyUsers = [
    [
        'full_name' => 'Sara Smith',
        'phone' => '+15550000001',
        'age' => 22,
        'gender' => 'woman',
        'looking_for' => 'man',
        'bio' => 'Coffee lover and traveler.',
        'interests' => 'Modern Art,Specialty Coffee,Travel',
        'latitude' => 37.4219983,
        'longitude' => -122.084,
        'city' => 'Mountain View',
        'profile_complete' => 1,
        'show_in_discovery' => 1,
        'photo' => 'https://res.cloudinary.com/demo/image/upload/v1312461204/sample.jpg'
    ],
    [
        'full_name' => 'Emma Watson',
        'phone' => '+15550000002',
        'age' => 25,
        'gender' => 'woman',
        'looking_for' => 'man',
        'bio' => 'Love reading and hiking.',
        'interests' => 'Literature,Architecture,Music',
        'latitude' => 37.4225,
        'longitude' => -122.085,
        'city' => 'Palo Alto',
        'profile_complete' => 1,
        'show_in_discovery' => 1,
        'photo' => 'https://res.cloudinary.com/demo/image/upload/v1312461204/baby.jpg'
    ],
    [
        'full_name' => 'Liam Neeson',
        'phone' => '+15550000003',
        'age' => 28,
        'gender' => 'man',
        'looking_for' => 'woman',
        'bio' => 'I have a very particular set of skills.',
        'interests' => 'Literature,Gaming,Journalism',
        'latitude' => 37.421,
        'longitude' => -122.08,
        'city' => 'Sunnyvale',
        'profile_complete' => 1,
        'show_in_discovery' => 1,
        'photo' => 'https://res.cloudinary.com/demo/image/upload/v1312461204/couple.jpg'
    ],
    [
        'full_name' => 'Sophia Loren',
        'phone' => '+15550000004',
        'age' => 21,
        'gender' => 'woman',
        'looking_for' => 'man',
        'bio' => 'Italian soul looking for adventure.',
        'interests' => 'Espresso,Modern Art,Specialty Coffee',
        'latitude' => 37.42,
        'longitude' => -122.09,
        'city' => 'Mountain View',
        'profile_complete' => 1,
        'show_in_discovery' => 1,
        'photo' => 'https://res.cloudinary.com/demo/image/upload/v1312461204/flower.jpg'
    ]
];

echo "Setting up dummy data...\n";

foreach ($dummyUsers as $user) {
    // Insert into users table
    $stmt = $db->prepare("INSERT IGNORE INTO users (full_name, phone_number, age, gender, looking_for, bio, interests, latitude, longitude, city, profile_complete, show_in_discovery) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param('ssisssssddii', 
        $user['full_name'], 
        $user['phone'],
        $user['age'], 
        $user['gender'], 
        $user['looking_for'], 
        $user['bio'], 
        $user['interests'], 
        $user['latitude'], 
        $user['longitude'], 
        $user['city'], 
        $user['profile_complete'], 
        $user['show_in_discovery']
    );
    
    if ($stmt->execute()) {
        $newUserId = $stmt->insert_id;
        if ($newUserId > 0) {
            echo "Inserted User: {$user['full_name']} (ID: $newUserId)\n";
            
            // Insert photo
            $photoStmt = $db->prepare("INSERT INTO user_photos (user_id, photo_url, is_dp) VALUES (?, ?, 1)");
            $photoStmt->bind_param('is', $newUserId, $user['photo']);
            $photoStmt->execute();
            $photoStmt->close();
        } else {
            echo "Skipped existing user: {$user['full_name']}\n";
        }
    } else {
        echo "Error inserting user: " . $stmt->error . "\n";
    }
    $stmt->close();
}

$db->close();
echo "Dummy data setup complete!\n";
?>
