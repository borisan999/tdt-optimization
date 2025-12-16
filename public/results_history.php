<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
// prefer composer autoload
$autoload = __DIR__ . '/../vendor/autoload.php';
if (is_file($autoload)) {
    require_once $autoload;
}

$incHelper = __DIR__ . '/../app/helpers/IncludeHelper.php';
if (is_file($incHelper)) {
    require_once $incHelper;
} else {
    if (!function_exists('require_one_of')) {
        function require_one_of(array $candidates, $throw = true)
        {
            foreach ($candidates as $p) {
                if (is_file($p)) {
                    require_once $p;
                    return $p;
                }
            }
            if ($throw) {
                http_response_code(500);
                echo "<h2>Internal server error</h2><p>Missing include helper.</p>";
                exit;
            }
            return false;
        }
    }
}

require_one_of([__DIR__ . '/../app/config/db.php']);

// Load DB config (may not be needed but kept for consistency)
require_once __DIR__ . '/../app/config/db.php';
require_once __DIR__ . '/../app/services/ResultsService.php';

$db = new Database();
$pdo = $db->getConnection();



try {
    $results = ResultsService::listAll($pdo);
} catch (Throwable $e) {
    http_response_code(500);
    echo "<h2 style='color:red;'>Error loading results</h2>";
    if (ini_get('display_errors')) {
        echo "<pre>" . htmlspecialchars($e->getMessage()) . "</pre>";
    }
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Optimization History</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
</head>

<body class="bg-light">

<div class="container py-4">

    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2 class="fw-bold">Optimization History</h2>
        <a href="index.php" class="btn btn-secondary">Back</a>
    </div>

    <div class="card shadow-sm">
        <div class="card-body">

            <table class="table table-bordered table-striped align-middle">
                <thead class="table-dark">
                <tr>
                    <th>ID</th>
                    <th>Status</th>
                    <th>Created At</th>
                    <th>Min Level</th>
                    <th>Max Level</th>
                    <th>Avg Level</th>
                    <th>Min Loss</th>
                    <th>Max Loss</th>
                    <th>Action</th>
                </tr>
                </thead>

                <tbody>
                <?php foreach ($results as $row): ?>

                    <?php
                        // Status badge
                        $badgeClass = "secondary";
                        if ($row["status"] === "running") $badgeClass = "warning";
                        if ($row["status"] === "completed") $badgeClass = "success";
                        if ($row["status"] === "failed") $badgeClass = "danger";

                        $stats = $row["stats"];
                    ?>

                    <tr>
                        <td><?= htmlspecialchars($row["opt_id"]) ?></td>

                        <td>
                            <span class="badge bg-<?= $badgeClass ?>">
                                <?= htmlspecialchars($row["status"]) ?>
                            </span>
                        </td>

                        <td>
                            <?= $row["created_at"] !== null
                                ? htmlspecialchars($row["created_at"])
                                : '-' ?>
                        </td>

                        <td><?= $stats["min_level"] !== null ? number_format($stats["min_level"], 2) : "-" ?></td>
                        <td><?= $stats["max_level"] !== null ? number_format($stats["max_level"], 2) : "-" ?></td>
                        <td><?= $stats["avg_level"] !== null ? number_format($stats["avg_level"], 2) : "-" ?></td>
                        <td><?= $stats["min_loss"] !== null ? number_format($stats["min_loss"], 2) : "-" ?></td>
                        <td><?= $stats["max_loss"] !== null ? number_format($stats["max_loss"], 2) : "-" ?></td>

                        <td>
                            <a href="view_result.php?opt_id=<?= $row["opt_id"] ?>"
                               class="btn btn-primary btn-sm">
                                View Details
                            </a>
                        </td>
                    </tr>

                <?php endforeach; ?>
                </tbody>
            </table>

        </div>
    </div>

</div>

</body>
</html>
