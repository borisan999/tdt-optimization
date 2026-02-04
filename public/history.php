<?php
// public/history.php

require_once __DIR__ . '/../app/auth/require_login.php';

// Bootstrap / models
require_once __DIR__ . '/../app/config/db.php';
require_once __DIR__ . '/../app/models/Dataset.php';

include __DIR__ . '/templates/header.php';
include __DIR__ . '/templates/navbar.php';

$datasetModel = new Dataset();
$history = $datasetModel->getHistory(); // returns array (may be empty)
?>

<div class="container mt-4">
    <h2>📁 Dataset History</h2>

    <?php if (empty($history)): ?>
        <div class="card p-3 mt-3">No datasets found.</div>
    <?php else: ?>
        <table class="table table-bordered mt-3">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>User</th>
                    <th>Status</th>
                    <th>Created</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($history as $d): ?>
                    <tr>
                        <td><?= htmlspecialchars($d['dataset_id']) ?></td>
                        <td><?= htmlspecialchars($d['uploaded_by'] ?? '—') ?></td>
                        <td><?= htmlspecialchars($d['status']) ?></td>
                        <td><?= htmlspecialchars($d['created_at']) ?></td>
                        <td>
                            <a href="view_dataset.php?id=<?= urlencode($d['dataset_id']) ?>"
                               class="btn btn-primary btn-sm">View</a>
                        </td>
                    </tr>
                <?php endforeach ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/templates/footer.php'; ?>
