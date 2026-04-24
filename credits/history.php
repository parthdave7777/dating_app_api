<?php
require_once __DIR__ . '/../config.php';
header('Content-Type: application/json');

$userId = getAuthUserId();
if (!$userId) {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}

$db = getDB();

try {
    // Optimized query to fetch logs with potential purchase metadata
    $stmt = $db->prepare("
        SELECT 
            cl.amount, cl.reason, cl.created_at, cl.is_purchase,
            pt.product_id, pt.order_id
        FROM credit_logs cl
        LEFT JOIN purchase_tokens pt ON (cl.reason = CONCAT('Play purchase: ', pt.product_id) AND pt.user_id = cl.user_id)
        WHERE cl.user_id = ? 
        ORDER BY cl.created_at DESC 
        LIMIT 50");
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    $stmt->close();

    $history = [];
    while ($row = $result->fetch_assoc()) {
        $reason = $row['reason'];
        $source = 'other';
        
        if (strpos($reason, 'Play purchase:') !== false) {
            $source = 'google_play';
        } elseif (strpos($reason, 'Purchase:') !== false) {
            $source = 'razorpay';
        }

        $history[] = [
            'amount'      => (int)$row['amount'],
            'reason'      => $reason,
            'date'        => $row['created_at'],
            'is_purchase' => (bool)$row['is_purchase'],
            'source'      => $source,
            'product_id'  => $row['product_id'] ?? null,
            'order_id'    => $row['order_id'] ?? null
        ];
    }

    echo json_encode([
        'status'  => 'success',
        'history' => $history
    ]);

} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => 'Failed to load history']);
}
?>
