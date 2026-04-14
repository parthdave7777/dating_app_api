<?php
/**
 * scripts/test_api_flow.php
 * Benchmarking script to measure live API response times.
 */

$baseUrl = "https://dating-app-api-2t98.onrender.com/";
$phoneA  = "1234567890";
$phoneB  = "0987654321";
$otp     = "123456";

function callApi($url, $payload = [], $token = null) {
    global $baseUrl;
    $ch = curl_init($baseUrl . $url);
    $headers = ['Content-Type: application/json'];
    if ($token) $headers[] = "Authorization: Bearer $token";
    
    curl_setopt_array($ch, [
        CURLOPT_POST           => !empty($payload),
        CURLOPT_POSTFIELDS     => !empty($payload) ? json_encode($payload) : null,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER     => $headers,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_TIMEOUT        => 30,
        CURLOPT_SSL_VERIFYPEER => false, // Bypass SSL verification for testing
    ]);
    
    $start = microtime(true);
    $response = curl_exec($ch);
    $err = curl_error($ch);
    $end = microtime(true);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($err) echo " (CURL ERROR: $err) ";
    if ($httpCode !== 200) {
        echo " [HTTP $httpCode RAW: " . substr($response, 0, 500) . "] ";
    }
    
    $jsonData = json_decode($response, true);
    $procTime = $jsonData['process_time'] ?? ($jsonData['data']['process_time'] ?? 'N/A');

    return [
        'data' => $jsonData,
        'time' => round(($end - $start), 3),
        'proc' => $procTime,
        'code' => $httpCode
    ];
}

echo "--- DATING APP API BENCHMARK ---\n\n";

// 1. LOGIN
echo "Logging in User A ($phoneA)... ";
$resA = callApi("auth/verify_otp.php", ['phone_number' => $phoneA, 'otp' => $otp]);
$tokenA = $resA['data']['token'] ?? null;
$uidA = $resA['data']['user_id'] ?? null;
if (!$tokenA) {
    echo "FAILED (" . $resA['time'] . "s)\n";
    echo "Response: " . json_encode($resA['data']) . " (HTTP " . $resA['code'] . ")\n";
} else {
    echo "SUCCESS (" . $resA['time'] . "s)\n";
}

echo "Logging in User B ($phoneB)... ";
$resB = callApi("auth/verify_otp.php", ['phone_number' => $phoneB, 'otp' => $otp]);
$tokenB = $resB['data']['token'] ?? null;
$uidB = $resB['data']['user_id'] ?? null;
if (!$tokenB) {
    echo "FAILED (" . $resB['time'] . "s)\n";
    echo "Response: " . json_encode($resB['data']) . " (HTTP " . $resB['code'] . ")\n";
} else {
    echo "SUCCESS (" . $resB['time'] . "s)\n";
}

if (!$tokenA || !$tokenB) exit("Login failed. Cannot proceed.\n");

// 2. SETUP (Ensure they match)
echo "Setting up User A profile (Male)... ";
$setA = callApi("profile/setup.php", [
    'full_name' => 'User A (Test)', 'age' => 25, 'gender' => 'man', 'looking_for' => 'woman',
    'bio' => 'Benchmarking test', 'latitude' => 12.9716, 'longitude' => 77.5946, 'city' => 'Bengaluru'
], $tokenA);
    echo $setA['code'] === 200 ? "SUCCESS" : "FAILED";
    echo " (" . $setA['time'] . "s) [Proc: " . $setA['proc'] . "]\n";

    echo "Setting up User B profile (Female)... ";
    $setB = callApi("profile/setup.php", [
        'full_name' => 'User B (Test)', 'age' => 23, 'gender' => 'woman', 'looking_for' => 'man',
        'bio' => 'Benchmarking test', 'latitude' => 12.9716, 'longitude' => 77.5946, 'city' => 'Bengaluru'
    ], $tokenB);
    echo $setB['code'] === 200 ? "SUCCESS" : "FAILED";
    echo " (" . $setB['time'] . "s) [Proc: " . $setB['proc'] . "]\n\n";

    // 3. DISCOVERY & INTERACTION
    echo "User A views User B's profile... ";
    $viewB = callApi("profile/get_profile.php?target_id=$uidB", [], $tokenA);
    echo $viewB['code'] === 200 ? "SUCCESS" : "FAILED";
    echo " (" . $viewB['time'] . "s) [Proc: " . $viewB['proc'] . "]\n";
    if ($viewB['code'] !== 200 || ($viewB['data']['status'] ?? '') === 'error') {
        echo "   Error: " . ($viewB['data']['message'] ?? 'Unknown Error') . "\n";
    }

    echo "User A swipes right on User B... ";
    $swipeA = callApi("profile/swipe.php", ['target_id' => $uidB, 'action' => 'like'], $tokenA);
    echo $swipeA['code'] === 200 ? "SUCCESS" : "FAILED";
    echo " (" . $swipeA['time'] . "s) [Proc: " . $swipeA['proc'] . "]\n";

    echo "User B swipes right on User A (Match!)... ";
    $swipeB = callApi("profile/swipe.php", ['target_id' => $uidA, 'action' => 'like'], $tokenB);
    echo $swipeB['code'] === 200 ? "SUCCESS" : "FAILED";
    echo " (" . $swipeB['time'] . "s) [Proc: " . $swipeB['proc'] . "]\n";

    echo "User A sends a compliment to User B... ";
    $compA = callApi("compliments/send.php", ['receiver_id' => $uidB, 'message' => 'Speed test!'], $tokenA);
    echo $compA['code'] === 200 ? "SUCCESS" : "FAILED";
    echo " (" . $compA['time'] . "s) [Proc: " . $compA['proc'] . "]\n\n";

    // 4. CHAT
    echo "User A sends a chat message to User B... ";
    $msgA = callApi("chat/send_message.php", ['receiver_id' => $uidB, 'message' => 'How fast was that?'], $tokenA);
    echo $msgA['code'] === 200 ? "SUCCESS" : "FAILED";
    echo " (" . $msgA['time'] . "s) [Proc: " . $msgA['proc'] . "]\n\n";

echo "--- BENCHMARK COMPLETE ---\n";
?>
