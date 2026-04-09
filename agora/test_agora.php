<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/Util.php';
require_once __DIR__ . '/AccessToken2.php';
require_once __DIR__ . '/RtcTokenBuilder2.php';

header('Content-Type: text/plain');

echo "AGORA_APP_ID: " . AGORA_APP_ID . "\n";
echo "AGORA_APP_CERT: " . (empty(AGORA_APP_CERT) ? "MISSING" : "PRESENT (" . strlen(AGORA_APP_CERT) . " chars)") . "\n";

$channelName = "test_channel";
$uid = 123456;
$expiry = 3600;

try {
    $token = RtcTokenBuilder2::buildTokenWithUid(
        AGORA_APP_ID,
        AGORA_APP_CERT,
        $channelName,
        $uid,
        RtcTokenBuilder2::ROLE_PUBLISHER,
        $expiry,
        $expiry
    );
    echo "\nToken Generated Successfully:\n";
    echo $token . "\n";
} catch (Exception $e) {
    echo "\nERROR generating token: " . $e->getMessage() . "\n";
}
