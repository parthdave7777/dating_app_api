<?php
header("Content-Type: application/json");
echo json_encode([
    "status" => "success",
    "message" => "Dating API is live on Render!",
    "version" => "1.0.0",
    "security" => "Anti-bot challenge bypassed ✅"
]);
