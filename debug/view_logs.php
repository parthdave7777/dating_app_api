<?php
header('Content-Type: text/plain');
$logFile = ini_get('error_log');
if ($logFile && file_exists($logFile)) {
    echo file_get_contents($logFile, false, null, max(0, filesize($logFile) - 5000));
} else {
    echo "No error log found or accessible at: " . ($logFile ?: 'unknown');
}
