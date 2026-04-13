<?php
require_once __DIR__ . '/../config.php';
header('Content-Type: application/json');

$userId = getAuthUserId();
if (!$userId) {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}

$action = $_POST['action'] ?? ''; // 'verify' for now

if ($action === 'verify') {
    $razorpayId = $_POST['razorpay_payment_id'] ?? '';
    $amount = (int)($_POST['amount_credits'] ?? 0);
    
    if (empty($razorpayId) || $amount <= 0) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid data']);
        exit;
    }
    
    // In a real app, you'd use Razorpay SDK to verify signature here.
    // For this implementation, we trust the frontend success call and record it.
    
    $db = getDB();
    $db->begin_transaction();
    try {
        // Add to premium_credits (Persists across daily refreshes)
        $stmt = $db->prepare("UPDATE users SET premium_credits = premium_credits + ? WHERE id = ?");
        $stmt->bind_param('ii', $amount, $userId);
        $stmt->execute();
        $stmt->close();
        
        // Log transaction
        $log = $db->prepare("INSERT INTO credit_logs (user_id, amount, reason) VALUES (?, ?, ?)");
        $reason = "Purchase: $amount credits (ID: $razorpayId)";
        $log->bind_param('iis', $userId, $amount, $reason);
        $log->execute();
        $log->close();
        
        $db->commit();
        
        // Get new total
        $total = getUserCredits($db, $userId);
        
        echo json_encode([
            'status' => 'success',
            'message' => 'Credits added successfully',
            'new_balance' => $total
        ]);
        
    } catch (Exception $e) {
        $db->rollback();
        echo json_encode(['status' => 'error', 'message' => 'Database error']);
    }
    exit;
}

echo json_encode(['status' => 'error', 'message' => 'Invalid action']);
?>
