<?php
declare(strict_types=1);

require_once __DIR__ . '/session.php';

if (empty($_SESSION['user_id'])) {
    header('Location: /tdt-optimization/login');
    exit;
}

/**
 * Ensures the current user has access to a specific dataset.
 * Admins have access to everything.
 * Engineers only have access to datasets they uploaded.
 */
function ensureDatasetAccess(int $datasetId, PDO $pdo): void {
    if (($_SESSION['role'] ?? 'admin') === 'admin') {
        return;
    }

    $stmt = $pdo->prepare(\"SELECT uploaded_by FROM datasets WHERE dataset_id = :id\");
    $stmt->execute(['id' => $datasetId]);
    $dataset = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$dataset || (int)$dataset['uploaded_by'] !== (int)$_SESSION['user_id']) {
        http_response_code(403);
        die(\"Access Denied: You do not have permission to access this dataset.\");
    }
}

/**
 * Ensures the current user has access to a specific result (optimization).
 * Admins have access to everything.
 * Engineers only have access to results from datasets they uploaded.
 */
function ensureResultAccess(int $optId, PDO $pdo): void {
    if (($_SESSION['role'] ?? 'admin') === 'admin') {
        return;
    }

    $stmt = $pdo->prepare(\"
        SELECT d.uploaded_by 
        FROM results r
        JOIN datasets d ON r.dataset_id = d.dataset_id
        WHERE r.opt_id = :id
    \");
    $stmt->execute(['id' => $optId]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$result || (int)$result['uploaded_by'] !== (int)$_SESSION['user_id']) {
        http_response_code(403);
        die(\"Access Denied: You do not have permission to access this result.\");
    }
}

/**
 * Check if current user is admin
 */
function isAdmin(): bool {
    return ($_SESSION['role'] ?? 'admin') === 'admin';
}
