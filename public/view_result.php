<?php
/**
 * Canonical Results Viewer (Strict) - REFACTORED
 * ----------------------------------
 * This file is now a pure view template.
 * All logic is handled by ResultsController.
 */
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
require_once __DIR__ . '/../app/auth/require_login.php';
require_once __DIR__ . '/../app/controllers/ResultsController.php'; // Use the new controller

include __DIR__ . '/templates/header.php';
include __DIR__ . '/templates/navbar.php';

use app\controllers\ResultsController;

$opt_id = intval($_GET['opt_id'] ?? 0);
$controller = new ResultsController($opt_id);
$response = $controller->execute();

if (($response['status'] ?? 'error') !== 'success') {
    echo "<div class='container my-4'><div class='alert alert-danger'><strong>Error:</strong> " . htmlspecialchars($response['message'] ?? 'An unknown error occurred.') . " (Type: " . htmlspecialchars($response['error_type'] ?? 'unknown') . ")</div></div>";
    include __DIR__ . '/templates/footer.php';
    return; // Changed from die();
}

// Make controller variables available to the view template with defensive programming
$meta = $response['meta'] ?? [];
$summary = $response['summary'] ?? [];
$details = $response['details'] ?? [];
$violations = $response['violations'] ?? [];
$warnings = $response['warnings'] ?? [];
$inputs = $response['inputs'] ?? []; // Defensive
$status = $meta['status'] ?? 'unknown';
$created = $meta['created_at'] ?? null;
$dataset_id = $meta['dataset_id'] ?? null;

?>
<div class="container my-4">

    <h2 class="mb-3">Optimization Result Details</h2>

    <table class="table table-bordered table-sm">
        <tr>
            <th>Optimization ID</th>
            <td><?= htmlspecialchars((string)($meta['opt_id'] ?? '')) ?></td>
        </tr>
        <tr>
            <th>Status</th>
            <td><?= htmlspecialchars((string)$status) ?></td>
        </tr>
        <tr>
            <th>Dataset ID</th>
            <td><?= $dataset_id !== null ? htmlspecialchars((string)$dataset_id) : '—' ?></td>
        </tr>
        <tr>
            <th>Created</th>
            <td><?= $created !== null ? htmlspecialchars($created) : '—' ?></td>
        </tr>
    </table>

    <?php if (!empty($warnings)): ?>
        <div class="alert alert-warning">
            <strong>Parsing warnings:</strong>
            <ul>
                <?php foreach ($warnings as $w): ?>
                    <li><?= htmlspecialchars($w) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <?php if (empty($details)): ?>
        <div class="alert alert-info">
            No result details available for this optimization. The process may still be running or it might have completed without generating a detailed output.
        </div>
    <?php else: ?>
        <h4>Summary Metrics</h4>

        <table class="table table-bordered table-sm">
            <tbody>
            <?php foreach ($summary as $k => $v): ?>
                <tr>
                    <th><?= htmlspecialchars($k) ?></th>
                    <td><?= is_scalar($v) ? htmlspecialchars((string)$v) : json_encode($v) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>

        <h4>Violaciones de Nivel TU</h4>

        <?php if (count($violations) === 0): ?>
            <div class="alert alert-success">
                Todas las tomas cumplen con la banda normativa.
            </div>
        <?php else: ?>

        <div class="table-responsive">
            <table class="table table-sm table-bordered table-striped">
                <thead>
                    <tr>
                        <th>tu_id</th>
                        <th>piso</th>
                        <th>apto</th>
                        <th>nivel_tu</th>
                        <th>Tipo</th>
                        <th>Δ vs Norma (dB)</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($violations as $v): ?>
                    <tr class="<?= $v['_violation_type'] === 'LOW' ? 'table-warning' : 'table-danger' ?>">
                        <td><?= htmlspecialchars($v['tu_id'] ?? '—') ?></td>
                        <td><?= htmlspecialchars($v['piso'] ?? '—') ?></td>
                        <td><?= htmlspecialchars($v['apto'] ?? '—') ?></td>
                        <td><?= number_format($v['nivel_tu'], 2) ?></td>
                        <td><?= $v['_violation_type'] === 'LOW' ? 'Bajo norma' : 'Sobre norma' ?></td>
                        <td><?= number_format($v['_violation_delta'], 2) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <?php endif; ?>

        <h4>Detail JSON (<?= count($details) ?> TUs)</h4>

        <div class="table-responsive">
            <table class="table table-striped table-bordered table-sm">
                <thead>
                <tr>
                    <?php if (!empty($details)): ?>
                        <?php foreach (array_keys($details[0]) as $col): ?>
                            <th><?= htmlspecialchars($col) ?></th>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($details as $rowDetail): ?>
                    <tr>
                        <?php foreach ($rowDetail as $col_key => $val): ?>
                            <td>
                            <?php if (is_array($val)): ?>
                                <?php if ($col_key === 'losses'): ?>
                                    <?php foreach ($val as $loss_item): ?>
                                        <?php if (isset($loss_item['segment']) && isset($loss_item['value'])): ?>
                                            <?= htmlspecialchars($loss_item['segment'] . ': ' . $loss_item['value']) ?><br>
                                        <?php endif; ?>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <?= htmlspecialchars(json_encode($val, JSON_UNESCAPED_UNICODE)) ?>
                                <?php endif; ?>
                            <?php else: ?>
                                <?= htmlspecialchars((string)$val) ?>
                            <?php endif; ?>
                            </td>
                        <?php endforeach; ?>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <h4>Inputs JSON</h4>
        <pre class="bg-white p-3 border rounded">
<?= htmlspecialchars(json_encode($inputs, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) ?>
        </pre>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/templates/footer.php'; ?>