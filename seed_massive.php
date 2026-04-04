<?php
require_once __DIR__ . '/config.php';
$db = getDB();

header('Content-Type: text/plain');
echo "--- SEEDING 600 REALISTIC USERS ---\n";

// 1. Data Pools
$firstNamesFemale = ["Ananya", "Diya", "Isha", "Kavya", "Myra", "Sanya", "Zara", "Aavya", "Bhavna", "Chhavi", "Esha", "Gauri", "Hina", "Jiya", "Kiara", "Leila", "Megha", "Nora", "Ojaswi", "Pari", "Sneha", "Tanya", "Urvi", "Vanya", "Yashvi"];
$firstNamesMale = ["Arjun", "Kabir", "Rohan", "Siddharth", "Vihaan", "Yuvraj", "Ishaan", "Arav", "Aryan", "Dev", "Hrithik", "Imran", "Kunal", "Lakshay", "Madhav", "Naman", "Pranav", "Rishi", "Sameer", "Tushar", "Varun", "Yash", "Zaid"];
$lastNames = ["Sharma", "Verma", "Gupta", "Malhotra", "Kapoor", "Khan", "Singh", "Iyer", "Reddy", "Patel", "Chopra", "Joshi", "Bose", "Das", "Nagpal", "Mehta", "Shah", "Trivedi", "Mishra", "Pandey"];
$cities = [
    ["Mumbai", 19.0760, 72.8777],
    ["Delhi", 28.6139, 77.2090],
    ["Bangalore", 12.9716, 77.5946],
    ["Hyderabad", 17.3850, 78.4867],
    ["Ahmedabad", 23.0225, 72.5714],
    ["Chennai", 13.0827, 80.2707],
    ["Kolkata", 22.5726, 88.3639],
    ["Surat", 21.1702, 72.8311],
    ["Pune", 18.5204, 73.8567],
    ["Jaipur", 26.9124, 75.7873],
    ["Lucknow", 26.8467, 80.9462],
    ["Kanpur", 26.4499, 80.3319],
    ["Nagpur", 21.1458, 79.0882],
    ["Indore", 22.7196, 75.8577],
    ["Thane", 19.2183, 72.9781],
    ["Rajkot", 22.3039, 70.8022]
];
$interestsPool = ["Yoga", "Cooking", "Photography", "Trekking", "Reading", "Dancing", "Gaming", "Swimming", "Movies", "Netflix", "Music", "Art", "Travel", "Dogs", "Cats", "Gym", "Foodie", "Coffee", "Tea", "Wine", "Cricket", "Football", "Anime", "Startups", "Investing"];
$bios = [
    "I'm a sunset chaser and a coffee enthusiast.", "Looking for a partner in crime for weekend adventures.",
    "Lover of all things vintage and vinyl records.", "Let's skip the small talk and share our Spotify playlists.",
    "Simple person with big dreams.", "Always down for a spontaneous road trip.",
    "In search of the best pizza in the city.", "Introvert but loves deep conversations.",
    "A fitness freak who also loves excessive amounts of dessert.", "Let's create stories we can tell our grandkids one day."
];

$femaleUnsplash = [
    "https://images.unsplash.com/photo-1494790108377-be9c29b29330?w=800",
    "https://images.unsplash.com/photo-1517841905240-472988babdf9?w=800",
    "https://images.unsplash.com/photo-1534528741775-53994a69daeb?w=800",
    "https://images.unsplash.com/photo-1524504388940-b1c1722653e1?w=800",
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
    "https://images.unsplash.com/photo-1503443207922-dff7d543fd0e?w=800"
];

// 2. Generate 600 Users
$targetCount = 600;

for ($i = 1; $i <= $targetCount; $i++) {
    $isFemale = ($i % 2 === 0);
    $firstName = $isFemale ? $firstNamesFemale[array_rand($firstNamesFemale)] : $firstNamesMale[array_rand($firstNamesMale)];
    $lastName = $lastNames[array_rand($lastNames)];
    $fullName = "$firstName $lastName";
    $phone = "+91" . rand(6000000000, 9999999999);
    $age = rand(18, 35);
    $gender = $isFemale ? "woman" : "man";
    $looking = $isFemale ? "man" : "woman";
    $bio = $bios[array_rand($bios)];
    
    // Pick a city and add small random offsets for varied local testing
    $cityData = $cities[array_rand($cities)];
    $cityName = $cityData[0];
    $baseLat = $cityData[1];
    $baseLng = $cityData[2];
    $lat = $baseLat + (rand(-100, 100) / 1000);
    $lng = $baseLng + (rand(-100, 100) / 1000);

    // Random interests
    $ints = array_rand(array_flip($interestsPool), rand(3, 6));
    $interestsStr = implode(",", $ints);

    $uStmt = $db->prepare("INSERT INTO users (phone_number, full_name, age, gender, looking_for, bio, interests, city, latitude, longitude, is_verified, profile_complete, show_in_discovery) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, 1, 1)");
    $uStmt->bind_param('ssisssssdd', $phone, $fullName, $age, $gender, $looking, $bio, $interestsStr, $cityName, $lat, $lng);
    $uStmt->execute();
    $userId = $db->insert_id;

    if ($i % 50 === 0) echo "Generated $i users...\n";

    // Add 1 photo per user for speed (using pools)
    $pool = $isFemale ? $femaleUnsplash : $maleUnsplash;
    $url = $pool[array_rand($pool)] . "&sig=" . ($userId * 10);
    $phStmt = $db->prepare("INSERT INTO user_photos (user_id, photo_url, is_dp) VALUES (?, ?, 1)");
    $phStmt->bind_param('is', $userId, $url);
    $phStmt->execute();
}

$db->close();
echo "\n--- SEEDING COMPLETE! 600 USERS ADDED ---\n";
?>
