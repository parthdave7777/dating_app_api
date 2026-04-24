<?php
require_once __DIR__ . '/../config.php';
header('Content-Type: application/json');

$userId = getAuthUserId();
if (!$userId) {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$purchaseToken = $input['purchase_token'] ?? '';
$productId = $input['product_id'] ?? '';

if (empty($purchaseToken) || empty($productId)) {
    echo json_encode(['status' => 'error', 'message' => 'Missing purchase details']);
    exit;
}

// Map Product IDs to Credit Amounts
$productMapping = [
    'credits_100'  => 100,
    'credits_500'  => 500,
    'credits_1200' => 1200,
    'credits_3000' => 3000,
];

if (!isset($productMapping[$productId])) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid Product ID: ' . $productId]);
    exit;
}

$amount = $productMapping[$productId];

// --- PRODUCTION NOTE ---
// In real production, you would use the Google Play Developer API to verify the $purchaseToken.
// For TESTING, we will trust the token and add the credits immediately.
// -----------------------

$db = getDB();
$db->begin_transaction();

try {
    // 1. Add to premium_credits
    $stmt = $db->prepare("UPDATE users SET premium_credits = premium_credits + ? WHERE id = ?");
    $stmt->bind_param('ii', $amount, $userId);
    $stmt->execute();
    $stmt->close();

    // 2. Log transaction
    $log = $db->prepare("INSERT INTO credit_logs (user_id, amount, reason, is_purchase) VALUES (?, ?, ?, 1)");
    $reason = "Google Play Purchase: $productId (Token: " . substr($purchaseToken, 0, 10) . "...)";
    $log->bind_param('iis', $userId, $amount, $reason);
    $log->execute();
    $log->close();

    $db->commit();

    // Get new total
    $res = $db->query("SELECT credits, premium_credits FROM users WHERE id = $userId")->fetch_assoc();
    $total = (int)($res['credits'] ?? 0) + (int)($res['premium_credits'] ?? 0);
    
    // NITRO CACHE CLEANUP
    clearProfileCache($userId);

    echo json_encode([
        'status' => 'success',
        'message' => "Successfully purchased $amount credits!",
        'new_balance' => $total,
        'added' => $amount
    ]);

} catch (Exception $e) {
    $db->rollback();
    echo json_encode(['status' => 'error', 'message' => 'Database error: ' . $e->getMessage()]);
}
?>
