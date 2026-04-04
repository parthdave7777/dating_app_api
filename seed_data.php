<?php
require_once __DIR__ . '/config.php';
$db = getDB();

echo "Generating 40 dummy users for testing...\n";

// Remove old dummy users first if any (optional, but keep it clean)
// $db->query("DELETE FROM users WHERE phone_number LIKE '+919%'");

$names_men = ['Aarav', 'Vihaan', 'Arjun', 'Sai', 'Ishaan', 'Aaryan', 'Rohan', 'Kabir', 'Aryan', 'Vivaan', 'Reyansh', 'Shaurya', 'Ansh', 'Krishna', 'Kartik', 'Yuvraj', 'Dev', 'Aditya', 'Moksh', 'Atharv'];
$names_women = ['Aanya', 'Saanvi', 'Ananya', 'Aadhya', 'Pari', 'Avni', 'Diya', 'Myra', 'Ira', 'Anaya', 'Kaira', 'Kiara', 'Shanaya', 'Sara', 'Zoya', 'Alia', 'Riya', 'Ishani', 'Tanya', 'Vanya'];

$photos_men = [
    'https://images.unsplash.com/photo-1500648767791-00dcc994a43e?w=500',
    'https://images.unsplash.com/photo-1506794778202-cad84cf45f1d?w=500',
    'https://images.unsplash.com/photo-1507003211169-0a1dd7228f2d?w=500',
    'https://images.unsplash.com/photo-1492562080023-ab3db95bfbce?w=500',
    'https://images.unsplash.com/photo-1504257432389-52343af06ae3?w=500'
];

$photos_women = [
    'https://images.unsplash.com/photo-1494790108377-be9c29b29330?w=500',
    'https://images.unsplash.com/photo-1517841905240-472988babdf9?w=500',
    'https://images.unsplash.com/photo-1524504388940-b1c1722653e1?w=500',
    'https://images.unsplash.com/photo-1531746020798-e6953c6e8e04?w=500',
    'https://images.unsplash.com/photo-1438761681033-6461ffad8d80?w=500'
];

$base_cities = [
    ['name' => 'Rajkot', 'lat' => 22.3039, 'lng' => 70.8022],
    ['name' => 'Surat', 'lat' => 21.1702, 'lng' => 72.8311],
    ['name' => 'Ahmedabad', 'lat' => 23.0225, 'lng' => 72.5714],
    ['name' => 'Mumbai', 'lat' => 19.0760, 'lng' => 72.8777],
    ['name' => 'New York', 'lat' => 40.7128, 'lng' => -74.0060]
];

for ($i = 0; $i < 40; $i++) {
    $is_man = ($i % 2 === 0);
    $name = $is_man ? $names_men[array_rand($names_men)] : $names_women[array_rand($names_women)];
    $gender = $is_man ? 'man' : 'woman';
    $looking = $is_man ? 'women' : 'men';
    $phone = '+91' . rand(7000000000, 9999999999);
    $age = rand(18, 32);
    
    // Pick a city
    $city_data = $base_cities[array_rand($base_cities)];
    $city = $city_data['name'];
    $lat = $city_data['lat'] + (rand(-100, 100) / 2000.0);
    $lng = $city_data['lng'] + (rand(-100, 100) / 2000.0);
    
    $photo = $is_man ? $photos_men[array_rand($photos_men)] : $photos_women[array_rand($photos_women)];

    $stmt = $db->prepare("INSERT INTO users (phone_number, full_name, age, gender, looking_for, bio, interests, city, latitude, longitude, is_verified, profile_complete, setup_completed, show_in_discovery) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, 1, 1, 1)");
    $bio = "Testing the discovery algorithm from " . $city;
    $interests = "Coffee,Music,Tech";
    $stmt->bind_param('ssisssssdd', $phone, $name, $age, $gender, $looking, $bio, $interests, $city, $lat, $lng);
    
    if ($stmt->execute()) {
        $userId = $db->insert_id;
        $pStmt = $db->prepare("INSERT INTO user_photos (user_id, photo_url, is_dp) VALUES (?, ?, 1)");
        $pStmt->bind_param('is', $userId, $photo);
        $pStmt->execute();
        $pStmt->close();
        echo "Added $name ($age, $gender) in $city\n";
    }
    $stmt->close();
}

$db->close();
echo "Done! 40 test users generated.";
?>
