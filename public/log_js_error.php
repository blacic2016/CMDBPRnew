<?php
/**
 * Log client-side JS and AJAX errors on the server for remote debugging
 */
require_once __DIR__ . '/../config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    if ($data) {
        $logFile = ROOT_PATH . '/storage/logs/js_errors.log';
        $logMessage = "[" . date('Y-m-d H:i:s') . "] [" . ($data['type'] ?? 'ERROR') . "] Message: " . ($data['message'] ?? '') . " | File: " . ($data['file'] ?? '') . " | Line: " . ($data['line'] ?? '') . "\n";
        file_put_contents($logFile, $logMessage, FILE_APPEND);
    }
}
header('Content-Type: application/json');
echo json_encode(['success' => true]);
