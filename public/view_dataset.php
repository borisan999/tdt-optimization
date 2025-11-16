<?php
// public/view_dataset.php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . "/../app/config/db.php";
require_once __DIR__ . "/../app/models/Dataset.php";
require_once __DIR__ . "/../app/models/DatasetRow.php";

$dataset_id = $_GET['id'] ?? null;
if (!$dataset_id) {
    header("Location: history.php");
    exit;
}

$datasetModel = new Dataset();
$rowModel = new DatasetRow();

$dataset = $datasetModel->get($dataset_id);
$rows = $rowModel->getRowsByDataset($dataset_id);

include __DIR__ . "/templates/header.php";
?>

<div class="container mt-4">
    <h2>Dataset #<?= htmlspecialchars($dataset['dataset_id']) ?></h2>
    <p><strong>Status:</strong> <?= htmlspecialchars($dataset['status']) ?></p>
    <p><strong>Created:</strong> <?= htmlspecialchars($dataset['created_at']) ?></p>

    <h4>Data Rows</h4>
    <?php if (empty($rows)): ?>
        <div class="card p-3">No rows found for this dataset.</div>
    <?php else: ?>
        <table class="table table-striped mt-3">
            <thead>
                <tr>
                    <th>Index</th>
                    <th>Field</th>
                    <th>Value</th>
                    <th>Unit</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($rows as $r): ?>
                    <tr>
                        <td><?= htmlspecialchars($r['record_index']) ?></td>
                        <td><?= htmlspecialchars($r['field_name']) ?></td>
                        <td><?= htmlspecialchars($r['field_value']) ?></td>
                        <td><?= htmlspecialchars($r['unit']) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>

    <a href="history.php" class="btn btn-secondary mt-3">Back to History</a>
</div>

<?php include __DIR__ . "/templates/footer.php"; ?>
