<?php
/**
 * Serve optimization log files
 * Access: /optimization-logs-view.php?file=optimization_1234567890.log
 */

require_once __DIR__ . '/../app/auth/require_login.php';

$logDir = '/var/www/html/storage/optimization_logs';
$file = $_GET['file'] ?? '';

// Security: prevent path traversal attacks
if (empty($file) || basename($file) !== $file || pathinfo($file, PATHINFO_EXTENSION) !== 'log') {
    http_response_code(400);
    echo "Invalid file parameter";
    exit;
}

$filepath = $logDir . '/' . $file;

if (!file_exists($filepath)) {
    http_response_code(404);
    echo "File not found: " . htmlspecialchars($file);
    exit;
}

// Serve the file
header('Content-Type: text/plain; charset=utf-8');
header('Content-Length: ' . filesize($filepath));
readfile($filepath);
