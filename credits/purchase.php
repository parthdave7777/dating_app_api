<?php
require_once __DIR__ . '/../config.php';
header('Content-Type: application/json');

$userId = getAuthUserId();
if (!$userId) {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}

// Support for JSON input (common in Flutter http.post)
$input = json_decode(file_get_contents('php://input'), true);
$data = (!empty($input)) ? $input : $_POST;

$action = $data['action'] ?? ''; // 'verify' for now

if ($action === 'verify') {
    $razorpayId = $data['razorpay_payment_id'] ?? '';
    // Support both 'amount_credits' (String/Int) and 'credits'
    $amount = (int)($data['amount_credits'] ?? $data['credits'] ?? 0);
    
    if (empty($razorpayId) || $amount <= 0) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid data (ID or Amount missing)', 'received' => $data]);
        exit;
    }
    
    $db = getDB();
    
    // 🔍 SELF-HEALING: Ensure columns and tables exist
    // 1. Premium Credits Column
    $checkCol = $db->query("SHOW COLUMNS FROM users LIKE 'premium_credits'");
    if ($checkCol->num_rows == 0) {
        $db->query("ALTER TABLE users ADD COLUMN premium_credits INT DEFAULT 0 AFTER credits");
    }
    
    // 2. Credit Logs Table
    $db->query("CREATE TABLE IF NOT EXISTS credit_logs (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        amount INT NOT NULL,
        reason VARCHAR(255),
        is_purchase TINYINT(1) DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");

    // Ensure is_purchase column exists if table was created previously
    $checkLogCol = $db->query("SHOW COLUMNS FROM credit_logs LIKE 'is_purchase'");
    if ($checkLogCol->num_rows == 0) {
        $db->query("ALTER TABLE credit_logs ADD COLUMN is_purchase TINYINT(1) DEFAULT 0 AFTER reason");
    }

    $db->begin_transaction();
    try {
        // Add to premium_credits (Persists across daily refreshes)
        $stmt = $db->prepare("UPDATE users SET premium_credits = premium_credits + ? WHERE id = ?");
        $stmt->bind_param('ii', $amount, $userId);
        $stmt->execute();
        $stmt->close();
        
        // Log transaction as a purchase
        $log = $db->prepare("INSERT INTO credit_logs (user_id, amount, reason, is_purchase) VALUES (?, ?, ?, 1)");
        $reason = "Purchase: $amount credits (ID: $razorpayId)";
        $log->bind_param('iis', $userId, $amount, $reason);
        $log->execute();
        $log->close();
        
        $db->commit();
        
        // Get new total balance
        $check = $db->prepare("SELECT credits, premium_credits FROM users WHERE id = ?");
        $check->bind_param('i', $userId);
        $check->execute();
        $res = $check->get_result()->fetch_assoc();
        $check->close();
        
        $total = (int)($res['credits'] ?? 0) + (int)($res['premium_credits'] ?? 0);
        
        echo json_encode([
            'status' => 'success',
            'message' => 'Credits added successfully!',
            'new_balance' => $total,
            'added' => $amount
        ]);
        
    } catch (Exception $e) {
        $db->rollback();
        echo json_encode(['status' => 'error', 'message' => 'Database error: ' . $e->getMessage()]);
    }
    exit;
}

echo json_encode(['status' => 'error', 'message' => 'Invalid action', 'received' => $data]);
?>
