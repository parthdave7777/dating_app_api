<?php
require_once 'config.php';
$ch = curl_init("https://" . SOKETI_HOST . "/");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 5);
$res = curl_exec($ch);
$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
echo "Soketi Check: Code $code\n";
echo "Response: $res\n";
curl_close($ch);
