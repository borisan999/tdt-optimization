<?php
/**
 * public/results.php
 * Legacy endpoint — now permanently redirects to the new Bootstrap viewer.
 */

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . "/../app/config/db.php";

$opt_id = intval($_GET['opt_id'] ?? 0);
$dataset_id = intval($_GET['dataset_id'] ?? 0);

if ($opt_id <= 0 && $dataset_id > 0) {
    // Attempt to find the latest successful optimization for this dataset
    $db = new Database();
    $pdo = $db->getConnection();
    $stmt = $pdo->prepare("SELECT opt_id FROM optimizations WHERE dataset_id = ? AND status = 'finished' ORDER BY created_at DESC LIMIT 1");
    $stmt->execute([$dataset_id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($row) {
        $opt_id = intval($row['opt_id']);
    }
}

if ($opt_id <= 0) {
    http_response_code(400);
    die("Missing or invalid parameter: opt_id (and no finished optimization found for dataset_id)");
}

$newUrl = "/tdt-optimization/public/view-result/" . $opt_id;

// Issue temporary redirect
header("Location: {$newUrl}", true, 302);
exit;
