<?php
require_once __DIR__ . '/../config.php';
$db = getDB();

$dummies = [
    [
        'full_name' => 'Sara Williams',
        'age' => 24,
        'gender' => 'woman',
        'looking_for' => 'man',
        'bio' => 'Coffee lover, world traveler, and part-time yoga instructor. Looking for someone to share morning sunsets and late-night talks with.',
        'interests' => 'Yoga,Travel,Coffee,Music,Art',
        'job_title' => 'Graphic Designer',
        'company' => 'Creative Studio',
        'education' => 'Bachelor of Fine Arts',
        'lifestyle_pets' => 'Dog lover',
        'lifestyle_drinking' => 'Socially',
        'lifestyle_smoking' => 'Non-smoker',
        'lifestyle_workout' => 'Every day',
        'lifestyle_diet' => 'Vegetarian',
        'lifestyle_schedule' => 'Flexible',
        'relationship_goal' => 'Long-term partner',
        'communication_style' => 'Texting',
        'photos' => [
            'https://images.unsplash.com/photo-1494790108377-be9c29b29330',
            'https://images.unsplash.com/photo-1524504388940-b1c1722653e1',
            'https://images.unsplash.com/photo-1517841905240-472988babdf9'
        ]
    ],
    [
        'full_name' => 'Jessica Chen',
        'age' => 26,
        'gender' => 'woman',
        'looking_for' => 'man',
        'bio' => 'I love hiking, tech startups, and making the perfect matcha. I am a beach person who occasionally enjoys a rainy day in.',
        'interests' => 'Hiking,Matcha,Coding,Reading,Beach',
        'job_title' => 'Software Engineer',
        'company' => 'Tech Hub',
        'education' => 'CS Master',
        'lifestyle_pets' => 'Cat person',
        'lifestyle_drinking' => 'Rarely',
        'lifestyle_smoking' => 'Non-smoker',
        'lifestyle_workout' => '3 times a week',
        'lifestyle_diet' => 'Vegan',
        'lifestyle_schedule' => 'Mon-Fri 9-5',
        'relationship_goal' => 'Life partner',
        'communication_style' => 'In person',
        'photos' => [
            'https://images.unsplash.com/photo-1534528741775-53994a69daeb',
            'https://images.unsplash.com/photo-1507003211169-0a1dd7228f2d',
            'https://images.unsplash.com/photo-1521119989659-a83eee488004'
        ]
    ],
    [
        'full_name' => 'Elena Rodriguez',
        'age' => 22,
        'gender' => 'woman',
        'looking_for' => 'man',
        'bio' => 'Architecture student by day, amateur chef by night. Tell me your favorite pasta recipe! 🍝',
        'interests' => 'Cooking,Architecture,Wine,Dance,Movies',
        'job_title' => 'Student',
        'company' => 'University',
        'education' => 'Architecture Degree',
        'lifestyle_pets' => 'No pets',
        'lifestyle_drinking' => 'Frequently',
        'lifestyle_smoking' => 'Social smoker',
        'lifestyle_workout' => 'Occasionally',
        'lifestyle_diet' => 'Everything',
        'lifestyle_schedule' => 'Active nights',
        'relationship_goal' => 'Fun & Casual',
        'communication_style' => 'Calling',
        'photos' => [
            'https://images.unsplash.com/photo-1488426862026-3ee34a7d66df',
            'https://images.unsplash.com/photo-1520813792240-56fc4a3765a7'
        ]
    ],
    [
        'full_name' => 'Chloe Bennett',
        'age' => 28,
        'gender' => 'woman',
        'looking_for' => 'man',
        'bio' => 'Financial analyst who escapes to the mountains on weekends. I value honesty and a good sense of humor.',
        'interests' => 'Skiing,Finance,Comedy,Fitness,Sushi',
        'job_title' => 'Financial Analyst',
        'company' => 'Global Bank',
        'education' => 'MBA',
        'lifestyle_pets' => 'Birds',
        'lifestyle_drinking' => 'Rarely',
        'lifestyle_smoking' => 'Non-smoker',
        'lifestyle_workout' => 'Every day',
        'lifestyle_diet' => 'High protein',
        'lifestyle_schedule' => 'Strict',
        'relationship_goal' => 'Marriage-minded',
        'communication_style' => 'Texting',
        'photos' => [
            'https://images.unsplash.com/photo-1506794778202-cad84cf45f1d',
            'https://images.unsplash.com/photo-1529626455594-4ff0802cfb7e'
        ]
    ],
    [
        'full_name' => 'Rachel Stern',
        'age' => 25,
        'gender' => 'woman',
        'looking_for' => 'man',
        'bio' => 'Just moved to the city! Looking for a guide to the best hidden gems and someone to share them with.',
        'interests' => 'Exploration,Nightlife,Photography,Gym,Food',
        'job_title' => 'Content Creator',
        'company' => 'Self-employed',
        'education' => 'Marketing Degree',
        'lifestyle_pets' => 'Dog lover',
        'lifestyle_drinking' => 'Socially',
        'lifestyle_smoking' => 'Non-smoker',
        'lifestyle_workout' => 'Yoga',
        'lifestyle_diet' => 'Flexitarian',
        'lifestyle_schedule' => 'Flexible',
        'relationship_goal' => 'Dating but open',
        'communication_style' => 'Texting',
        'photos' => [
            'https://images.unsplash.com/photo-1517365830460-955ce3ccd263',
            'https://images.unsplash.com/photo-1524250502761-1ac6f2e30d43'
        ]
    ]
];

foreach ($dummies as $d) {
    $phone = '+1' . rand(1000000000, 9999999999);
    $city = 'Bhavnagar'; // Default for Bhavnagar discovery testing
    
    // 1. Insert User
    $stmt = $db->prepare("INSERT INTO users (full_name, phone_number, age, gender, looking_for, bio, interests, job_title, company, education, lifestyle_pets, lifestyle_drinking, lifestyle_smoking, lifestyle_workout, lifestyle_diet, lifestyle_schedule, relationship_goal, communication_style, city, is_verified, show_in_discovery, last_active) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, 1, NOW())");
    $stmt->bind_param('ssissssssssssssssss', 
        $d['full_name'], $phone, $d['age'], $d['gender'], $d['looking_for'], 
        $d['bio'], $d['interests'], $d['job_title'], $d['company'], $d['education'],
        $d['lifestyle_pets'], $d['lifestyle_drinking'], $d['lifestyle_smoking'],
        $d['lifestyle_workout'], $d['lifestyle_diet'], $d['lifestyle_schedule'],
        $d['relationship_goal'], $d['communication_style'], $city
    );
    $stmt->execute();
    $uid = $stmt->insert_id;
    $stmt->close();

    // 2. Insert Photos
    foreach ($d['photos'] as $idx => $url) {
        $isDp = ($idx === 0) ? 1 : 0;
        $db->query("INSERT INTO user_photos (user_id, photo_url, is_dp, created_at) VALUES ($uid, '$url', $isDp, NOW())");
    }
}

echo "Successfully injected 5 premium dummy profiles.";
?>
