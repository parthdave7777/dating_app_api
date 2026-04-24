<?php
// DEPRECATED: This file (purchase.php) is no longer used.
// The app now uses verify_google_purchase.php for Google Play Billing.
// You can safely delete this file from your server.
http_response_code(410); // Gone
echo json_encode(['status' => 'error', 'message' => 'This endpoint is deprecated.']);
?>
