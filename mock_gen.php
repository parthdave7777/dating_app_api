<?php
$names = ['Aarav', 'Vihaan', 'Rohan', 'Arjun', 'Siddharth', 'Dev', 'Advait', 'Kabir', 'Aryan', 'Rishi', 'Kush', 'Darshil', 'Samir', 'Yash', 'Hardik', 'Manav', 'Pratik', 'Jay', 'Uday', 'Atharv'];
$fNames = ['Isha', 'Ananya', 'Kiara', 'Diya', 'Myra', 'Pari', 'Zara', 'Saanvi', 'Avni', 'Amaira', 'Heena', 'Naina', 'Riya', 'Mansi', 'Bhavini', 'Ishani', 'Tanvi', 'Dhara', 'Zalak', 'Prisha'];
$surnames = ['Patel', 'Shah', 'Mehta', 'Desai', 'Gandhi', 'Jani', 'Vora', 'Trivedi', 'Joshi', 'Pathak', 'Kapadia', 'Mistry', 'Parekh', 'Kothari', 'Choksi', 'Tailor', 'Rana', 'Vyas', 'Rao', 'Gaekwad', 'Parmar', 'Dave', 'Sanghavi', 'Jadeja', 'Bhatt', 'Jha'];
$jobs = ['Engineer', 'Designer', 'CEO', 'Manager', 'Architect', 'Blogger', 'Trainer', 'Writer', 'Student', 'Doctor', 'Lawyer', 'Analyst', 'HR', 'Merchant', 'Chef', 'Developer', 'Photographer', 'Trader', 'Clerk'];
$interests = ['Traveling, Foodie, Cricket', 'Art, Design, Photography', 'Business, Hiking, Coffee', 'Reading, Music, Chai', 'Photography, History', 'Fashion, Books, Travel', 'Gym, Cooking, Movies', 'Dancing, Garba, Music', 'Tech, Gadgets, Gaming', 'Poetry, Nature, Tea'];

$cities = [
    ['name' => 'Ahmedabad', 'lat' => 23.0225, 'lng' => 72.5714],
    ['name' => 'Surat', 'lat' => 21.1702, 'lng' => 72.8311],
    ['name' => 'Vadodara', 'lat' => 22.3072, 'lng' => 73.1812],
    ['name' => 'Rajkot', 'lat' => 22.3039, 'lng' => 70.8022],
    ['name' => 'Gandhinagar', 'lat' => 23.2156, 'lng' => 72.6369]
];

$sql = "INSERT INTO `users` (
    `phone_number`, `full_name`, `age`, `gender`, `looking_for`, `bio`, `interests`, 
    `latitude`, `longitude`, `city`, `state`, `country`, `is_verified`, `profile_complete`, 
    `setup_completed`, `job_title`, `company`, `credits`, `discovery_min_dist`, `discovery_max_dist`, `stealth_radius`
) VALUES\n";

$rows = [];
for ($i = 1; $i <= 200; $i++) {
    $genderNum = rand(0, 1);
    $gender = ($genderNum == 0) ? 'man' : 'woman';
    $target = ($gender == 'man') ? 'woman' : 'man';
    
    $nameArr = ($gender == 'man') ? $names : $fNames;
    $firstName = $nameArr[array_rand($nameArr)];
    $lastName = $surnames[array_rand($surnames)];
    $fullName = $firstName . ' ' . $lastName;
    
    $phone = '+919000000' . str_pad($i, 3, '0', STR_PAD_LEFT);
    $age = rand(19, 35);
    $city = $cities[array_rand($cities)];
    
    // Add small random offset to lat/lng for distribution
    $lat = $city['lat'] + (rand(-500, 500) / 10000.0);
    $lng = $city['lng'] + (rand(-500, 500) / 10000.0);
    
    $job = $jobs[array_rand($jobs)];
    $interest = $interests[array_rand($interests)];
    $bio = "Passionate about " . strtolower(explode(',', $interest)[0]) . " and living in " . $city['name'] . ".";
    
    $rows[] = sprintf(
        "('%s', '%s', %d, '%s', '%s', '%s', '%s', %.4f, %.4f, '%s', 'Gujarat', 'India', 1, 1, 1, '%s', 'Local Business', 100, 0, 50, 0)",
        $phone, addslashes($fullName), $age, $gender, $target, addslashes($bio), addslashes($interest), $lat, $lng, $city['name'], $job
    );
}

$sql .= implode(",\n", $rows) . ";\n";
echo $sql;
