<?php
/**
 * public/results.php
 * Legacy endpoint — now permanently redirects to the new Bootstrap viewer.
 */

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

$opt_id = intval($_GET['opt_id'] ?? 0);

if ($opt_id <= 0) {
    http_response_code(400);
    die("Missing or invalid parameter: opt_id");
}

$newUrl = "/tdt-optimization/public/view_result.php?opt_id=" . $opt_id;

// Issue permanent redirect
header("Location: {$newUrl}", true, 301);
exit;
