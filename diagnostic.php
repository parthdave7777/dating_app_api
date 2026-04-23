<?php
/**
 * diagnostic.php
 * Health check for DB, Redis, and Worker Queue.
 */
require_once 'config.php';

$results = [
    'status' => 'healthy',
    'timestamp' => date('Y-m-d H:i:s'),
    'checks' => []
];

// 1. DB CHECK
try {
    $db = getDB();
    if ($db) {
        $db->query("SELECT 1");
        $results['checks']['database'] = 'OK';
    } else {
        throw new Exception("getDB returned null");
    }
} catch (Throwable $e) {
    $results['status'] = 'degraded';
    $results['checks']['database'] = 'ERROR: ' . $e->getMessage();
}

// 2. REDIS CHECK
try {
    $redis = getRedis();
    if ($redis) {
        $redis->ping();
        $results['checks']['redis'] = 'OK';
        
        // 3. QUEUE CHECK
        $pending = $redis->lLen('nitro_tasks');
        $results['checks']['pending_tasks'] = $pending;
        if ($pending > 50) {
            $results['checks']['worker_status'] = 'WARNING: Heavy queue. Is worker running?';
        } else {
            $results['checks']['worker_status'] = 'OK';
        }
    } else {
        $results['checks']['redis'] = 'OFFLINE (Graceful Fallback Active)';
    }
} catch (Throwable $e) {
    $results['checks']['redis'] = 'ERROR: ' . $e->getMessage();
}

// 4. SOKETI CHECK (Connectivity)
try {
    $ch = curl_init("https://" . SOKETI_HOST . "/");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 3);
    curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    $results['checks']['soketi_http'] = ($code >= 200 && $code < 500) ? 'OK' : "UNREACHABLE (HTTP $code)";
} catch (Throwable $e) {
    $results['checks']['soketi_http'] = 'ERROR: ' . $e->getMessage();
}

header('Content-Type: application/json');
echo json_encode($results, JSON_PRETTY_PRINT);
