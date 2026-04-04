<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

echo "<pre>";
echo "PHP version: " . PHP_VERSION . "\n";
echo "zlib available: " . (function_exists('gzdeflate') ? 'YES' : 'NO') . "\n";
echo "hash_hmac available: " . (function_exists('hash_hmac') ? 'YES' : 'NO') . "\n\n";

require_once __DIR__ . '/../config.php';
echo "config.php loaded OK\n";

require_once __DIR__ . '/AccessToken2.php';
echo "AccessToken2.php loaded OK\n";

require_once __DIR__ . '/RtcTokenBuilder2.php';
echo "RtcTokenBuilder2.php loaded OK\n\n";

$token = RtcTokenBuilder2::buildTokenWithUid(
    '73a7b8a2569844fc99f597ce9fb21612',
    '809aec0bdc06445ea01ee3a211b40ff3',
    'match_1',
    123,
    RtcTokenBuilder2::ROLE_PUBLISHER,
    3600,
    3600
);

echo "Token generated OK!\n";
echo "Token: " . $token . "\n";
echo "</pre>";
