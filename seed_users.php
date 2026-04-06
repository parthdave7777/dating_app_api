<?php
// seed_users.php
require_once __DIR__ . '/config.php';
set_time_limit(0); 
ob_implicit_flush(true);
if (ob_get_level()) ob_end_clean();

$db = getDB();

$names = ["Emma", "Liam", "Olivia", "Noah", "Ava", "Ethan", "Sophia", "Lucas", "Mia", "Mason", 
          "Isabella", "Logan", "Amelia", "James", "Harper", "Sebastian", "Evelyn", "Alexander", "Abigail", "Caleb"];
$bios = ["Love traveling and coffee.", "Avid hiker and dog lover.", "Music is my life.", "Foodie searching for the best tacos.", 
         "Tech enthusiast and gamer.", "Yoga and mindfulness.", "Looking for something real.", "Casual vibes only."];
$interests = ["Music", "Travel", "Cooking", "Gaming", "Yoga", "Hiking", "Photography", "Movies", "Reading", "Dancing"];

echo "<h1>Seeding 500 Users... Please wait.</h1>";
echo "<div id='progress' style='font-family: monospace; line-height: 1.5;'>";

for ($i = 1; $i <= 500; $i++) {
    if ($i % 10 == 0) {
        echo "Adding User $i...<br>";
        @flush();
    }
    
    $gender = ($i % 2 == 0) ? 'woman' : 'man';
    $target = ($gender == 'woman') ? 'man' : 'woman';
    $name = $names[array_rand($names)] . " " . $i;
    $age = rand(18, 45);
    $bio = $bios[array_rand($bios)];
    $userInterests = implode(',', array_slice($interests, 0, rand(3, 6)));
    
    // Scattered around a center point (e.g., San Francisco for demo)
    $lat = 37.7749 + (rand(-1000, 1000) / 10000.0);
    $lon = -122.4194 + (rand(-1000, 1000) / 10000.0);
    
    $stmt = $db->prepare("INSERT INTO users (full_name, age, gender, looking_for, bio, interests, latitude, longitude, setup_completed, profile_complete) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1, 1)");
    $stmt->bind_param("sissssdd", $name, $age, $gender, $target, $bio, $userInterests, $lat, $lon);
    
    if ($stmt->execute()) {
        $userId = $stmt->insert_id;
        
        // Add 2 photos for each user
        // Using Picsum seed to ensure different faces
        $photo1 = "https://picsum.photos/seed/u{$userId}a/600/800";
        $photo2 = "https://picsum.photos/seed/u{$userId}b/600/800";
        
        // Match column name to 'photo_url' as seen in get_profile.php
        $db->query("INSERT INTO user_photos (user_id, photo_url, is_dp) VALUES ($userId, '$photo1', 1)");
        $db->query("INSERT INTO user_photos (user_id, photo_url, is_dp) VALUES ($userId, '$photo2', 0)");
        
        // Also update the main dp_url in users table for fast discovery loading
        $db->query("UPDATE users SET dp_url = '$photo1' WHERE id = $userId");
    }
}

echo "</div>";
echo "<h2>DONE! 500 users added with 1000 photos.</h2>";
echo "<p>Go back to your app and swipe away!</p>";
?>
