<?php
require_once __DIR__ . '/config.php';
$db = getDB();

echo "--- Resetting Database State ---\n";

// Disable foreign key checks for truncation
$db->query("SET FOREIGN_KEY_CHECKS = 0");

$tables = [
    'blocks',
    'reports',
    'notifications',
    'profile_views',
    'messages',
    'matches',
    'swipes',
    'user_posts',
    'user_photos',
    'otp_codes',
    'users'
];

foreach ($tables as $table) {
    if ($db->query("TRUNCATE TABLE $table")) {
        echo "Truncated $table\n";
    } else {
        echo "Error truncating $table: " . $db->error . "\n";
    }
}

$db->query("SET FOREIGN_KEY_CHECKS = 1");

echo "\n--- Seeding New Users ---\n";

// 1. Create my own user account first
$me = [
    'phone' => '+911234567890',
    'name' => 'John Doe',
    'age' => 25,
    'gender' => 'male',
    'looking' => 'female',
    'bio' => 'Software developer by day, music lover by night. Exploring the world one step at a time.',
    'interests' => 'Coding,Music,Travel,Coffee',
    'city' => 'Mumbai'
];

$stmt = $db->prepare("INSERT INTO users (phone_number, full_name, age, gender, looking_for, bio, interests, city, is_verified, profile_complete) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1, 1)");
$stmt->bind_param('ssisssss', $me['phone'], $me['name'], $me['age'], $me['gender'], $me['looking'], $me['bio'], $me['interests'], $me['city']);
$stmt->execute();
$myId = $db->insert_id;
echo "Created 'ME' user (ID: $myId)\n";

// Add photos for me
$myPhotos = [
    'https://images.unsplash.com/photo-1500648767791-00dcc994a43e?w=800',
    'https://images.unsplash.com/photo-1506794778202-cad84cf45f1d?w=800',
    'https://images.unsplash.com/photo-1531427186611-ecfd6d936c79?w=800'
];
foreach ($myPhotos as $idx => $url) {
    $isDp = ($idx === 0) ? 1 : 0;
    $pStmt = $db->prepare("INSERT INTO user_photos (user_id, photo_url, is_dp) VALUES (?, ?, ?)");
    $pStmt->bind_param('isi', $myId, $url, $isDp);
    $pStmt->execute();
}

// 2. Data Pools
$firstNamesFemale = ["Ananya", "Diya", "Isha", "Kavya", "Myra", "Sanya", "Zara", "Aavya", "Bhavna", "Chhavi", "Esha", "Gauri", "Hina", "Jiya", "Kiara", "Leila", "Megha", "Nora", "Ojaswi", "Pari"];
$firstNamesMale = ["Arjun", "Kabir", "Rohan", "Siddharth", "Vihaan", "Yuvraj", "Ishaan", "Arav", "Aryan", "Dev", "Hrithik", "Imran", "Kunal", "Lakshay", "Madhav", "Naman", "Pranav", "Rishi", "Sameer", "Tushar"];
$lastNames = ["Sharma", "Verma", "Gupta", "Malhotra", "Kapoor", "Khan", "Singh", "Iyer", "Reddy", "Patel", "Chopra", "Joshi", "Bose", "Das", "Nagpal"];
$cities = ["Mumbai", "Delhi", "Bangalore", "Hyderabad", "Ahmedabad", "Chennai", "Kolkata", "Surat", "Pune", "Jaipur"];
$interestsPool = ["Yoga", "Cooking", "Photography", "Trekking", "Reading", "Dancing", "Gaming", "Swimming", "Movies", "Netflix", "Music", "Art", "Travel", "Dogs", "Cats", "Gym", "Foodie", "Coffee", "Tea", "Wine"];
$bios = [
    "I'm a sunset chaser and a coffee enthusiast.",
    "Looking for a partner in crime for weekend adventures.",
    "Lover of all things vintage and vinyl records.",
    "Let's skip the small talk and share our Spotify playlists.",
    "Simple person with big dreams.",
    "Always down for a spontaneous road trip.",
    "In search of the best pizza in the city.",
    "Introvert but loves deep conversations.",
    "A fitness freak who also loves excessive amounts of dessert.",
    "Let's create stories we can tell our grandkids one day."
];

$femaleUnsplash = [
    "https://images.unsplash.com/photo-1494790108377-be9c29b29330?w=800",
    "https://images.unsplash.com/photo-1517841905240-472988babdf9?w=800",
    "https://images.unsplash.com/photo-1534528741775-53994a69daeb?w=800",
    "https://images.unsplash.com/photo-1524504388940-b1c1722653e1?w=800",
    "https://images.unsplash.com/photo-1506794778202-cad84cf45f1d?w=800", // Man but checking pool
    "https://images.unsplash.com/photo-1531123897727-8f129e1688ce?w=800",
    "https://images.unsplash.com/photo-1438761681033-6461ffad8d80?w=800",
    "https://images.unsplash.com/photo-1529626455594-4ff0802cfb7e?w=800",
    "https://images.unsplash.com/photo-1544005313-94ddf0286df2?w=800",
    "https://images.unsplash.com/photo-1488423191186-217e93ba2da1?w=800"
];

$maleUnsplash = [
    "https://images.unsplash.com/photo-1500648767791-00dcc994a43e?w=800",
    "https://images.unsplash.com/photo-1492562080023-ab3db95bfbce?w=800",
    "https://images.unsplash.com/photo-1506794778202-cad84cf45f1d?w=800",
    "https://images.unsplash.com/photo-1531427186611-ecfd6d936c79?w=800",
    "https://images.unsplash.com/photo-1519085360753-af0119f7cbe7?w=800",
    "https://images.unsplash.com/photo-1507003211169-0a1dd7228f2d?w=800",
    "https://images.unsplash.com/photo-1552058544-f2b08422138a?w=800",
    "https://images.unsplash.com/photo-1504257432389-52343af06ae3?w=800",
    "https://images.unsplash.com/photo-1480429370139-e0132c086e2a?w=800",
    "https://images.unsplash.com/photo-1503443207922-dff7d543fd0e?w=800"
];

// 3. Create 40 Users
$targetCount = 40;
$allUserIds = [$myId];

for ($i = 1; $i <= $targetCount; $i++) {
    $isFemale = ($i % 2 === 0);
    $firstName = $isFemale ? $firstNamesFemale[array_rand($firstNamesFemale)] : $firstNamesMale[array_rand($firstNamesMale)];
    $lastName = $lastNames[array_rand($lastNames)];
    $fullName = "$firstName $lastName";
    $phone = "+91" . rand(7000000000, 9999999999);
    $age = rand(18, 38);
    $gender = $isFemale ? "female" : "male";
    $looking = $isFemale ? "male" : "female";
    $bio = $bios[array_rand($bios)];
    $city = $cities[array_rand($cities)];
    
    // Random interests
    $ints = array_rand(array_flip($interestsPool), rand(3, 5));
    $interestsStr = implode(",", $ints);

    $uStmt = $db->prepare("INSERT INTO users (phone_number, full_name, age, gender, looking_for, bio, interests, city, is_verified, profile_complete) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1, 1)");
    $uStmt->bind_param('ssisssss', $phone, $fullName, $age, $gender, $looking, $bio, $interestsStr, $city);
    $uStmt->execute();
    $userId = $db->insert_id;
    $allUserIds[] = $userId;

    echo "Generated user $i: $fullName (ID: $userId)\n";

    // Add 3-4 photos
    $pool = $isFemale ? $femaleUnsplash : $maleUnsplash;
    shuffle($pool);
    $photoCount = rand(3, 5);
    for ($j = 0; $j < $photoCount; $j++) {
        $url = $pool[$j % count($pool)] . "&sig=" . ($userId * 10 + $j);
        $isDp = ($j === 0) ? 1 : 0;
        $phStmt = $db->prepare("INSERT INTO user_photos (user_id, photo_url, is_dp) VALUES (?, ?, ?)");
        $phStmt->bind_param('isi', $userId, $url, $isDp);
        $phStmt->execute();
    }
}

echo "\n--- Seeding Swipes & Matches ---\n";

// Create some likes for "ME" to potentially match
// Pick 5 women to like "ME"
$femaleIds = [];
$res = $db->query("SELECT id FROM users WHERE gender = 'female' AND id != $myId");
while($row = $res->fetch_assoc()) $femaleIds[] = $row['id'];

shuffle($femaleIds);
$likingWomen = array_slice($femaleIds, 0, 10);

foreach ($likingWomen as $fId) {
    // Action could be like or superlike
    $action = rand(0, 10) > 8 ? 'superlike' : 'like';
    $db->query("INSERT IGNORE INTO swipes (swiper_id, swiped_id, action) VALUES ($fId, $myId, '$action')");
    echo "User $fId liked ME\n";
}

// Me liking some of them back to create matches
$matchedWomen = array_slice($likingWomen, 0, 5);
foreach ($matchedWomen as $fId) {
    $db->query("INSERT IGNORE INTO swipes (swiper_id, swiped_id, action) VALUES ($myId, $fId, 'like')");
    // Insert into matches since it's a mutual like
    $db->query("INSERT IGNORE INTO matches (user1_id, user2_id) VALUES ($myId, $fId)");
    $matchId = $db->insert_id;
    echo "Match created between ME and $fId (Match ID: $matchId)\n";

    if ($matchId) {
        // Add some messages
        $messages = [
            ["sender" => $fId, "text" => "Hey! How are you doing today?"],
            ["sender" => $myId, "text" => "I'm good! Just finished working on a cool Flutter app."],
            ["sender" => $fId, "text" => "Oh wow, a developer! That's impressive."],
            ["sender" => $myId, "text" => "Haha thanks. What about you?"],
            ["sender" => $fId, "text" => "I'm a designer. We should totally work on something together."]
        ];
        foreach ($messages as $msg) {
            $mStmt = $db->prepare("INSERT INTO messages (match_id, sender_id, message) VALUES (?, ?, ?)");
            $mStmt->bind_param('iis', $matchId, $msg['sender'], $msg['text']);
            $mStmt->execute();
        }
    }
}

// 4. Random swipes among other users to populate the world
for ($k = 0; $k < 100; $k++) {
    $u1 = $allUserIds[array_rand($allUserIds)];
    $u2 = $allUserIds[array_rand($allUserIds)];
    if ($u1 === $u2) continue;
    
    $actions = ['like', 'dislike', 'like', 'like', 'superlike'];
    $act = $actions[array_rand($actions)];
    $db->query("INSERT IGNORE INTO swipes (swiper_id, swiped_id, action) VALUES ($u1, $u2, '$act')");
}

echo "\n--- Seeding Complete! ---\n";
echo "You can now log in with +911234567890 (OTP 111111/123456 as per config).\n";
?>
