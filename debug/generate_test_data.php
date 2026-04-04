<?php
// debug/generate_test_data.php — NUKES EVERYTHING and generates a fresh test dataset
require_once __DIR__ . '/../config.php';
$db = getDB();

// 1. CLEAR EVERYTHING
$db->query("SET FOREIGN_KEY_CHECKS = 0");
$db->query("TRUNCATE TABLE user_photos");
$db->query("TRUNCATE TABLE user_posts");
$db->query("TRUNCATE TABLE notifications");
$db->query("TRUNCATE TABLE messages");
$db->query("TRUNCATE TABLE matches");
$db->query("TRUNCATE TABLE swipes");
$db->query("TRUNCATE TABLE blocks");
$db->query("TRUNCATE TABLE profile_views");
$db->query("TRUNCATE TABLE call_logs");
$db->query("TRUNCATE TABLE users");
$db->query("SET FOREIGN_KEY_CHECKS = 1");

echo "DB Cleared. Starting generation...\n";

// 2. CREATE "ME" (The tester account)
// We'll use a fixed phone number for +911234567890
$meId = 1;
$meLat = 23.0225; // Ahmedabad center
$meLng = 72.5714;
$db->query("INSERT INTO users (id, phone_number, full_name, age, gender, profile_complete, setup_completed, latitude, longitude, city, show_in_discovery, discovery_min_age, discovery_max_age) 
            VALUES ($meId, '+911234567890', 'Tester Me', 25, 'Man', 1, 1, $meLat, $meLng, 'Ahmedabad', 1, 18, 50)");

// 3. GENERATE DUMMY USERS (500+ Users for Extreme Testing)
$firstNames = ['Anya', 'Bella', 'Chloe', 'Daisy', 'Elena', 'Fiona', 'Grace', 'Hanna', 'Ivy', 'Julia', 'Keira', 'Lily', 'Maya', 'Nora', 'Olivia', 'Piper', 'Quinn', 'Ruby', 'Sara', 'Tara', 'Uma', 'Vera', 'Willa', 'Xena', 'Yara', 'Zoe', 'Alice', 'Beatrice', 'Cora', 'Diana', 'Eva', 'Flora', 'Gigi', 'Hope', 'Isla', 'Jade', 'Kara', 'Lola', 'Mila', 'Nina', 'Opal', 'Pura', 'Rosa', 'Sky', 'Tessa', 'Uka', 'Vina', 'Wren', 'Xara', 'Yuna', 'Zara', 'Amelia', 'Brianna', 'Catherine', 'Danielle', 'Evelyn', 'Felicity', 'Gabriella', 'Harriet', 'Isabel', 'Jessica', 'Katherine', 'Leila', 'Madeline', 'Natalie', 'Ophelia', 'Penelope', 'Rosalie', 'Samantha', 'Tabitha', 'Ursula', 'Victoria', 'Winifred', 'Ximena', 'Yvonne', 'Zelda', 'Ada', 'Bernice', 'Claire', 'Dora', 'Edith', 'Frances', 'Gertrude', 'Hattie', 'Ida', 'Jean', 'Kezia', 'Lois', 'Mabel', 'Nellie', 'Olive', 'Pearl', 'Queenie', 'Ruth', 'Selina', 'Tilly', 'Una', 'Violet', 'Winnie'];
$lastNames = ['Sharma', 'Patel', 'Dave', 'Mehta', 'Shah', 'Joshi', 'Trivedi', 'Vyas', 'Pandya', 'Bhatt', 'Mishra', 'Gupta', 'Verma', 'Singh', 'Chopra', 'Malhotra', 'Kapoor'];

$photo_ids = [
    '1494790104870-555a13a286c3', '1524504388940-b1c1ec49b33c', '1531123845243-73194a858edc', 
    '1534524466941-bd3883a40733', '1517841905240-472988babdf9', '1513379233481-2da24b0bc8bc',
    '1488421774312-d615303a99e7', '1506794778202-cad84cf45f1d', '1504933350103-e84001850e01',
    '1511200057080-60b777a83d6a', '1516585427167-9f4af9e2886a', '1520333789090-1afc82db536a'
];
$interests_pool = ['Music', 'Travel', 'Fitness', 'Art', 'Coding', 'Gaming', 'Coffee', 'Movies', 'Foodie', 'Nature', 'Photography', 'Yoga', 'Reading', 'Dancing', 'Design', 'Swimming', 'Gardening', 'History', 'Politics', 'Finance'];
$jobs = ['Executive', 'Manager', 'Analyst', 'Doctor', 'Nurse', 'Architect', 'Actor', 'Founder', 'Pilot', 'Chef', 'Journalist', 'Blogger'];
$companies = ['TCS', 'Infosys', 'Reliance', 'LegitDate Labs', 'Amazon India', 'AIMS', 'Apollo Hospital', 'Self Employed', 'Creative Studio'];
$education = ['Gujarat University', 'Nirma University', 'IIT Bombay', 'Symbiosis Pune', 'Delhi University', 'Amity'];
$lifestyle_choices = ['Yes', 'No', 'Occasionally', 'Socially'];
$bios = [
    "Seeking adventure and meaningful connection. Love deep conversations over coffee.",
    "Fitness enthusiast. Looking for someone to travel the world with me.",
    "Tech lover by day, artist by night. Let's make something amazing.",
    "Foodie at heart. I know the best street food spots in Ahmedabad!",
    "Quiet soul, love books and nature. Looking for something genuine.",
    "Outgoing and fun. Life is too short for boring dates!",
    "Work hard, party harder. Tell me your favorite travel story."
];

// 3. PAN-INDIA GEOGRAPHIC CENTERS
$cityCenters = [
    ['name' => 'Ahmedabad',   'lat' => 23.0225, 'lng' => 72.5714],
    ['name' => 'Mumbai',      'lat' => 19.0760, 'lng' => 72.8777],
    ['name' => 'Delhi',       'lat' => 28.6139, 'lng' => 77.2090],
    ['name' => 'Bangalore',   'lat' => 12.9716, 'lng' => 77.5946],
    ['name' => 'Hyderabad',   'lat' => 17.3850, 'lng' => 78.4867],
    ['name' => 'Chennai',     'lat' => 13.0827, 'lng' => 80.2707],
    ['name' => 'Kolkata',     'lat' => 22.5726, 'lng' => 88.3639],
    ['name' => 'Pune',        'lat' => 18.5204, 'lng' => 73.8567],
    ['name' => 'Jaipur',      'lat' => 26.9124, 'lng' => 75.7873],
    ['name' => 'Surat',       'lat' => 21.1702, 'lng' => 72.8311],
    ['name' => 'Lucknow',     'lat' => 26.8467, 'lng' => 80.9462],
    ['name' => 'Kanpur',      'lat' => 26.4499, 'lng' => 80.3319],
    ['name' => 'Nagpur',      'lat' => 21.1458, 'lng' => 79.0882],
    ['name' => 'Indore',      'lat' => 22.7196, 'lng' => 75.8577],
    ['name' => 'Thane',       'lat' => 19.2183, 'lng' => 72.9781],
    ['name' => 'Bhopal',      'lat' => 23.2599, 'lng' => 77.4126],
    ['name' => 'Visakhapatnam','lat' => 17.6868, 'lng' => 83.2185],
    ['name' => 'Patna',       'lat' => 25.5941, 'lng' => 85.1376],
    ['name' => 'Vadodara',    'lat' => 22.3072, 'lng' => 73.1812],
    ['name' => 'Ghaziabad',   'lat' => 28.6692, 'lng' => 77.4538],
    ['name' => 'Ludhiana',    'lat' => 30.9010, 'lng' => 75.8573],
    ['name' => 'Agra',        'lat' => 27.1767, 'lng' => 78.0081],
    ['name' => 'Nashik',      'lat' => 19.9975, 'lng' => 73.7898],
    ['name' => 'Faridabad',   'lat' => 28.4089, 'lng' => 77.3178],
    ['name' => 'Rajkot',      'lat' => 22.3039, 'lng' => 70.8022],
    ['name' => 'Chandigarh',  'lat' => 30.7333, 'lng' => 76.7794],
    ['name' => 'Guwahati',    'lat' => 26.1445, 'lng' => 91.7362],
];

// Re-generate list for 505 users with Pan-India distributed locations
for ($i = 0; $i < 505; $i++) {
    // Pick center randomly from the entire list
    $c = $cityCenters[array_rand($cityCenters)];
    
    $cityName = $c['name'];
    $meLat = $c['lat'];
    $meLng = $c['lng'];

    $fname = $firstNames[array_rand($firstNames)];
    $lname = $lastNames[array_rand($lastNames)];
    $name = "$fname $lname";
    $age = rand(18, 65);
    $gender = ($i % 5 == 0) ? 'Man' : 'Woman'; 
    
    // Vary the coordinate slightly WITHIN the chosen city
    $lat = $meLat + (rand(-150, 150) / 2000); 
    $lng = $meLng + (rand(-150, 150) / 2000);

    $ints = implode(',', array_intersect_key($interests_pool, array_flip(array_rand($interests_pool, rand(3, 5)))));
    $job = $jobs[array_rand($jobs)];
    $company = $companies[array_rand($companies)];
    $edu = $education[array_rand($education)];
    $bio = $bios[array_rand($bios)];
    
    $drinking = $lifestyle_choices[array_rand($lifestyle_choices)];
    $smoking  = $lifestyle_choices[array_rand($lifestyle_choices)];
    $workout  = $lifestyle_choices[array_rand($lifestyle_choices)];

    $name_esc = $db->real_escape_string($name);
    $bio_esc = $db->real_escape_string($bio);
    $ints_esc = $db->real_escape_string($ints);
    $job_esc = $db->real_escape_string($job);
    $comp_esc = $db->real_escape_string($company);
    $edu_esc = $db->real_escape_string($edu);

    $db->query("INSERT INTO users (full_name, age, gender, bio, interests, profile_complete, setup_completed, show_in_discovery, latitude, longitude, city, last_active, phone_number, job_title, company, education, lifestyle_drinking, lifestyle_smoking, lifestyle_workout) 
                VALUES ('$name_esc', $age, '$gender', '$bio_esc', '$ints_esc', 1, 1, 1, $lat, $lng, '$cityName', NOW(), '+91".rand(1000000000, 9999999999)."', '$job_esc', '$comp_esc', '$edu_esc', '$drinking', '$smoking', '$workout')");
    
    $newId = $db->insert_id;
    
    // Add photo
    $pid = $photo_ids[array_rand($photo_ids)];
    $photoUrl = "https://images.unsplash.com/photo-$pid?w=400&h=600&fit=crop";
    $db->query("INSERT INTO user_photos (user_id, photo_url, is_dp) VALUES ($newId, '$photoUrl', 1)");
}

$db->close();
echo "Generated 505 Complete Profiles (Ahmedabad Base: 23.0225, 72.5714).\nLogin with +911234567890 (OTP: 123456)\n";
?>
