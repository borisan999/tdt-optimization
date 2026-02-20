<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../app/auth/require_login.php';
require_once __DIR__ . '/../app/config/db.php';
require_once __DIR__ . '/../app/services/ResultsService.php';

$db = new Database();
$pdo = $db->getConnection();

try {
    $results = ResultsService::listAll($pdo);
} catch (Throwable $e) {
    http_response_code(500);
    die("Error loading results: " . $e->getMessage());
}

include __DIR__ . '/templates/header.php';
include __DIR__ . '/templates/navbar.php';
?>

<div class="container py-4">

    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2 class="fw-bold">Optimization History</h2>
        <a href="dashboard" class="btn btn-secondary">Back</a>
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
                            <a href="view-result/<?= $row["opt_id"] ?>"
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

<?php include __DIR__ . '/templates/footer.php'; ?>
