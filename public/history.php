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
        <div class="card p-3 mt-3 shadow-sm border-0">No datasets found.</div>
    <?php else: ?>
        <div class="card shadow-sm border-0 mt-3">
            <div class="card-body p-0">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-3">ID</th>
                            <th>Name</th>
                            <th>Status</th>
                            <th>Created Date</th>
                            <th class="text-end pe-3">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($history as $d): ?>
                            <?php 
                                $statusColor = 'secondary';
                                if($d['status'] === 'processed') $statusColor = 'success';
                                if($d['status'] === 'error') $statusColor = 'danger';
                                if($d['status'] === 'pending' || $d['status'] === 'processing') $statusColor = 'warning';
                            ?>
                            <tr>
                                <td class="ps-3 fw-bold">#<?= htmlspecialchars($d['dataset_id']) ?></td>
                                <td><?= htmlspecialchars($d['dataset_name'] ?? 'Unnamed Dataset') ?></td>
                                <td>
                                    <span class="badge bg-<?= $statusColor ?>"><?= strtoupper(htmlspecialchars($d['status'])) ?></span>
                                </td>
                                <td><?= (new DateTime($d['created_at']))->format('M j, Y H:i') ?></td>
                                <td class="text-end pe-3">
                                    <?php if ($d['latest_opt_id']): ?>
                                        <a href="view-result/<?= urlencode($d['latest_opt_id']) ?>"
                                           class="btn btn-primary btn-sm px-3">
                                            <i class="fas fa-poll me-1"></i> View Result
                                        </a>
                                    <?php endif; ?>
                                    <a href="view-dataset/<?= urlencode($d['dataset_id']) ?>"
                                       class="btn btn-outline-secondary btn-sm px-3 ms-1">
                                        <i class="fas fa-eye me-1"></i> View Inputs
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/templates/footer.php'; ?>
