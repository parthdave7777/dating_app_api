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
    // 🔍 FILTER: Only show positive amounts (Purchases)
    $stmt = $db->prepare("SELECT amount, reason, created_at FROM credit_logs WHERE user_id = ? AND amount > 0 ORDER BY created_at DESC LIMIT 50");
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    $stmt->close();

    $history = [];
    while ($row = $result->fetch_assoc()) {
        $history[] = [
            'amount' => (int)$row['amount'],
            'reason' => $row['reason'],
            'date'   => $row['created_at'],
            'is_purchase' => true
        ];
    }

    echo json_encode([
        'status' => 'success',
        'history' => $history
    ]);

} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => 'Failed to load history']);
}
?>
