<?php
/**
 * Canonical Results Viewer (Polished)
 * ----------------------------------
 * Pure view template with charts and DataTables enhancements.
 */
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Handle Infeasible Results first
if (isset($_SESSION['optimization_result'])) {
    $result = $_SESSION['optimization_result'];
    unset($_SESSION['optimization_result']); // Clear the session variable

    if ($result['status'] === 'infeasible') {
        include __DIR__ . '/templates/header.php';
        include __DIR__ . '/templates/navbar.php';
        ?>
        <div class="container my-4">
            <div class="card border-warning shadow-sm">
                <div class="card-header bg-warning text-dark">
                    <h4 class="mb-0"><i class="fas fa-exclamation-triangle"></i> <?= __('opt_infeasible') ?></h4>
                </div>
                <div class="card-body">
                    <p class="lead"><?= __('infeasible_desc') ?></p>
                    <hr>
                    <dl class="row">
                        <dt class="col-sm-3"><?= __('dataset_id') ?></dt>
                        <dd class="col-sm-9"><?= htmlspecialchars($result['dataset_id'] ?? 'N/A') ?></dd>

                        <dt class="col-sm-3"><?= __('opt_id') ?></dt>
                        <dd class="col-sm-9"><?= htmlspecialchars($result['opt_id'] ?? 'N/A') ?></dd>

                        <dt class="col-sm-3"><?= __('solver_message') ?></dt>
                        <dd class="col-sm-9">
                            <code class="text-danger"><?= htmlspecialchars($result['message'] ?? __('no_message')) ?></code>
                        </dd>
                    </dl>
                    <hr>
                    <h5><?= __('possible_reasons') ?></h5>
                    <ul>
                        <li><?= __('reason_cable') ?></li>
                        <li><?= __('reason_trunk') ?></li>
                        <li><?= __('reason_passive') ?></li>
                        <li><?= __('reason_min_high') ?></li>
                        <li><?= __('reason_max_low') ?></li>
                    </ul>
                    <a href="enter-data/<?= htmlspecialchars($result['dataset_id'] ?? 0) ?>" class="btn btn-primary mt-3">
                        <i class="fas fa-edit"></i> <?= __('adjust_data_btn') ?>
                    </a>
                </div>
            </div>
        </div>
        <?php
        include __DIR__ . '/templates/footer.php';
        exit; // IMPORTANT: Stop the script here
    }
}


require_once __DIR__ . '/../app/auth/require_login.php';
require_once __DIR__ . '/../app/controllers/ResultsController.php';

include __DIR__ . '/templates/header.php';
include __DIR__ . '/templates/navbar.php';

use app\controllers\ResultsController;
use app\helpers\ResultParser;

$opt_id = intval($_GET['opt_id'] ?? 0);
$controller = new ResultsController($opt_id);
$response = $controller->execute();

if (($response['status'] ?? 'error') === 'infeasible') {
    include __DIR__ . '/templates/header.php';
    include __DIR__ . '/templates/navbar.php';
    ?>
    <div class="container my-4">
        <div class="card border-warning shadow-sm">
            <div class="card-header bg-warning text-dark">
                <h4 class="mb-0"><i class="fas fa-exclamation-triangle"></i> <?= __('opt_infeasible') ?></h4>
            </div>
            <div class="card-body">
                <p class="lead"><?= __('infeasible_desc') ?></p>
                <hr>
                <dl class="row">
                    <dt class="col-sm-3"><?= __('dataset_id') ?></dt>
                    <dd class="col-sm-9"><?= htmlspecialchars((string)($response['dataset_id'] ?? 'N/A')) ?></dd>

                    <dt class="col-sm-3"><?= __('opt_id') ?></dt>
                    <dd class="col-sm-9"><?= htmlspecialchars((string)($response['opt_id'] ?? 'N/A')) ?></dd>

                    <dt class="col-sm-3"><?= __('solver_message') ?></dt>
                    <dd class="col-sm-9">
                        <code class="text-danger"><?= htmlspecialchars($response['message'] ?? __('no_message')) ?></code>
                    </dd>
                </dl>
                <hr>
                <h5><?= __('possible_reasons') ?></h5>
                <ul>
                    <li><?= __('reason_cable') ?></li>
                    <li><?= __('reason_trunk') ?></li>
                    <li><?= __('reason_passive') ?></li>
                    <li><?= __('reason_min_high') ?></li>
                    <li><?= __('reason_max_low') ?></li>
                </ul>
                <a href="enter-data/<?= htmlspecialchars((string)($response['dataset_id'] ?? 0)) ?>" class="btn btn-primary mt-3">
                    <i class="fas fa-edit"></i> <?= __('adjust_data_btn') ?>
                </a>
            </div>
        </div>
    </div>
    <?php
    include __DIR__ . '/templates/footer.php';
    return;
}

if (($response['status'] ?? 'error') !== 'success') {
    echo "<div class='container my-4'><div class='alert alert-danger'><strong>Error:</strong> " . htmlspecialchars($response['message'] ?? 'An unknown error occurred.') . " (Type: " . htmlspecialchars($response['error_type'] ?? 'unknown') . ")</div></div>";
    include __DIR__ . '/templates/footer.php';
    return;
}

/** @var \app\viewmodels\ResultViewModel $viewModel */
$viewModel = $response['viewModel'];
$complianceMin = $viewModel->inputs['compliance_min'] ?? 48; // fallback
$complianceMax = $viewModel->inputs['compliance_max'] ?? 69;

// Calculate summary metrics
$totalTUs = count($viewModel->details);
$numCumple = count(array_filter($viewModel->details, fn($d) => ($d['cumple'] ?? 0)));
$compliancePct = $totalTUs > 0 ? round(($numCumple / $totalTUs) * 100, 2) : 0;
$tuLow = count(array_filter($viewModel->details, fn($d) => ($d['nivel_tu'] ?? 0) < ($d['nivel_min'] ?? 0)));
$tuHigh = count(array_filter($viewModel->details, fn($d) => ($d['nivel_tu'] ?? 0) > ($d['nivel_max'] ?? 0)));

$summaryMetrics = [
    'tu_total' => $totalTUs,
    'compliance_pct' => $compliancePct,
    'tu_low' => $tuLow,
    'tu_high' => $tuHigh,
];

// Correctly check for inventory availability
$canonicalAvailable = false;
if (!empty($viewModel->results)) {
    $parser = ResultParser::fromDbRow($viewModel->results);
    if (!$parser->hasErrors()) {
        $canonical = $parser->canonical(); // <-- This is where $canonical is defined
        /*echo '<pre>Canonical data for debugging:';
        print_r($canonical);
        echo '</pre>';*/
        $canonicalAvailable = !empty($canonical) && isset($canonical['vertical_distribution']) && isset($canonical['floors']);
    }
}
$isInventoryAvailable = $canonicalAvailable;
?>

<div class="container my-4">

    <div class="mb-3">
        <h2 class="mb-0 text-primary"><?= htmlspecialchars($viewModel->dataset_name ?? 'Unnamed Dataset') ?></h2>
        <div class="text-muted small"><?= __('result_details') ?> • <?= __('dataset_id') ?>: #<?= htmlspecialchars((string)$viewModel->meta['dataset_id']) ?></div>
    </div>

    <div class="mb-4">
        <div class="row g-2">
            <div class="col-auto">
                <div class="btn-group shadow-sm" role="group" aria-label="Download & Export">
                    <a class="btn btn-outline-success btn-sm"
                       href="export_input_excel.php?opt_id=<?= urlencode($viewModel->meta['opt_id'] ?? 0) ?>"
                       title="<?= __('export_xlsx') ?>">
                       <i class="fas fa-file-excel me-1"></i> <?= __('export_xlsx') ?>
                    </a>
                    <a class="btn btn-outline-success btn-sm"
                       href="export_csv.php?opt_id=<?= urlencode($viewModel->meta['opt_id'] ?? 0) ?>&type=detail"
                       title="<?= __('export_tu_csv') ?>">
                       <i class="fas fa-file-csv me-1"></i> <?= __('export_tu_csv') ?>
                    </a>
                    <a class="btn btn-outline-success btn-sm <?= !$isInventoryAvailable ? 'disabled' : '' ?>"
                       href="<?= $isInventoryAvailable ? 'export_csv.php?opt_id=' . urlencode($viewModel->meta['opt_id'] ?? 0) . '&type=inventory' : '#' ?>"
                       <?= !$isInventoryAvailable ? 'title="' . __('inventory_not_available') . '"' : 'title="' . __('export_inventory_csv') . '"' ?>>
                       <i class="fas fa-boxes me-1"></i> <?= __('export_inventory_csv') ?>
                    </a>
                    <a class="btn btn-outline-primary btn-sm"
                       href="export_docx.php?opt_id=<?= urlencode($viewModel->meta['opt_id'] ?? 0) ?>"
                       title="<?= __('export_docx') ?>">
                       <i class="fas fa-file-word me-1"></i> <?= __('export_docx') ?>
                    </a>
                </div>
            </div>
            <div class="col-auto">
                <div class="btn-group shadow-sm" role="group" aria-label="Interactive Tools">
                    <a class="btn btn-info btn-sm text-white"
                       href="results-tree/<?= urlencode((string)($viewModel->meta['opt_id'] ?? 0)) ?>"
                       title="<?= __('view_tree_btn') ?>">
                       <i class="fas fa-project-diagram me-1"></i> <?= __('view_tree_btn') ?>
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Summary Cards -->
    <div class="row mb-4 text-center justify-content-center">
    <?php 
        $summaryLabels = [
            'tu_total' => __('total_tus'),
            'compliance_pct' => __('compliance_pct_label'),
        ];
        foreach (['tu_total','compliance_pct'] as $key): 
          $value = $summaryMetrics[$key] ?? '—';
          $color = 'primary'; ?>
        <div class="col-md-4">
            <div class="card shadow-sm border-<?= $color ?>">
                <div class="card-body">
                    <div class="fw-bold text-muted"><?= htmlspecialchars($summaryLabels[$key]) ?></div>
                    <div class="fs-3"><?= htmlspecialchars($value) ?><?= $key==='compliance_pct' ? '%' : '' ?></div>
                    <?php if($key==='compliance_pct' && is_numeric($value)): ?>
                    <div class="progress mt-2">
                        <div class="progress-bar bg-<?= $color ?>" role="progressbar"
                            style="width: <?= $value ?>%" aria-valuenow="<?= $value ?>" aria-valuemin="0" aria-valuemax="100"></div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
    </div>

    <!-- Warnings -->
    <?php if (!empty($viewModel->warnings)): ?>
        <div class="alert alert-warning">
            <strong><?= __('parsing_warnings') ?></strong>
            <ul>
                <?php foreach ($viewModel->warnings as $w): ?>
                    <li><?= htmlspecialchars($w) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <?php if (!empty($viewModel->details)): ?>

        <!-- Summary Metrics -->
        <h4><?= __('summary_metrics') ?></h4>
        <table class="table table-bordered table-sm shadow-sm">
            <tbody>
            <?php foreach ($viewModel->summary as $k => $v): 
                $label = __('metric_' . $k);
                // If translation equals the key, it means it's missing, so use raw key as fallback
                if ($label === 'metric_' . $k) $label = $k;
            ?>
                <tr>
                    <th class="bg-light" style="width: 40%;"><?= htmlspecialchars($label) ?></th>
                    <td><?= is_scalar($v) ? htmlspecialchars((string)$v) : htmlspecialchars(json_encode($v, JSON_UNESCAPED_UNICODE)) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>

        <!-- Charts in Cards -->
        <div class="row mb-4">
            <div class="col-md-6 mb-3">
                <div class="card shadow-sm">
                    <div class="card-body">
                        <h5 class="card-title"><?= __('summary_kpis') ?></h5>
                        <canvas id="summaryChart"></canvas>
                    </div>
                </div>
            </div>
            <div class="col-md-6 mb-3">
                <div class="card shadow-sm">
                    <div class="card-body">
                        <h5 class="card-title"><?= __('tu_histogram') ?></h5>
                        <canvas id="nivelChart" height="120"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <!-- Violations -->
        <h4><?= __('tu_violations') ?></h4>
        <?php if(empty($viewModel->violations)): ?>
            <div class="alert alert-success"><?= __('all_tus_ok') ?></div>
        <?php else: ?>
            <div class="d-flex justify-content-between align-items-center mb-2">
                <div class="text-muted"><?= count($viewModel->violations) ?> <?= __('tus_out_of_norm') ?></div>
                <a class="btn btn-outline-danger btn-sm" href="export_csv.php?opt_id=<?= $viewModel->meta['opt_id'] ?>&type=violations"><?= __('export_violations_csv') ?></a>
            </div>
            <table class="table table-sm table-bordered table-striped">
            <thead>
            <tr>
                <th><?= __('col_tu') ?></th><th><?= __('col_piso') ?></th><th><?= __('col_apto') ?></th><th><?= __('col_tu_id') ?> (dBµV)</th><th><?= __('col_type') ?></th><th><?= __('col_delta') ?></th>
            </tr>
            </thead>
            <tbody>
            <?php foreach($viewModel->violations as $v): ?>
            <tr class="<?= ($v['_violation_type'] ?? '')==='LOW'?'table-warning':'table-danger' ?>">
                <td><?= htmlspecialchars($v['tu_id'] ?? '—') ?></td>
                <td><?= htmlspecialchars($v['piso'] ?? '—') ?></td>
                <td><?= htmlspecialchars($v['apto'] ?? '—') ?></td>
                <td><?= number_format((float)($v['nivel_tu'] ?? 0),2) ?></td>
                <td><?= ($v['_violation_type'] ?? '')==='LOW'? __('low_norm') : __('high_norm') ?></td>
                <td><?= number_format((float)($v['_violation_delta'] ?? 0),2) ?></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
            </table>
        <?php endif; ?>

        <!-- Detail TUs Table -->
        <h4><?= __('detail_tus') ?> (<?= count($viewModel->details) ?>)</h4>
        <div class="table-responsive">
            <table id="detailTable" class="table table-striped table-bordered table-sm nowrap">
                <thead class="table-light">
                    <tr>
                        <th><?= __('col_tu_id') ?></th>
                        <th><?= __('col_piso') ?></th>
                        <th><?= __('col_apto') ?></th>
                        <th><?= __('col_bloque') ?></th>
                        <th><?= __('col_tu_id') ?> (dBµV)</th>
                        <th><?= __('col_min') ?></th>
                        <th><?= __('col_max') ?></th>
                        <th><?= __('col_cumple') ?></th>
                        <th><?= __('col_losses') ?></th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($viewModel->details as $row): ?>
                    <?php
                        $cumpleClass = ($row['cumple'] ?? 0) ? 'table-success' : 'table-danger';
                    ?>
                    <tr class="<?= $cumpleClass ?>">
                        <td><?= htmlspecialchars($row['tu_id'] ?? '—') ?></td>
                        <td><?= htmlspecialchars($row['piso'] ?? '—') ?></td>
                        <td><?= htmlspecialchars($row['apto'] ?? '—') ?></td>
                        <td><?= htmlspecialchars($row['bloque'] ?? '—') ?></td>
                        <td><?= number_format((float)($row['nivel_tu'] ?? 0),2) ?></td>
                        <td><?= number_format((float)($row['nivel_min'] ?? 0),2) ?></td>
                        <td><?= number_format((float)($row['nivel_max'] ?? 0),2) ?></td>
                        <td class="text-center"><?= ($row['cumple'] ?? 0) ? '✔' : '✖' ?></td>
                        <td>
                            <?php if(!empty($row['losses']) && is_array($row['losses'])): ?>
                                <details>
                                    <summary><?= __('show_losses') ?></summary>
                                    <div class="table-responsive mt-2 mb-2" style="max-height:300px; overflow:auto;">
                                        <table class="table table-sm table-bordered mb-0">
                                            <thead class="table-light">
                                                <tr>
                                                    <th><?= __('col_segment') ?></th>
                                                    <th><?= __('col_value') ?></th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach($row['losses'] as $loss): ?>
                                                <tr>
                                                    <td><?= htmlspecialchars($loss['segment'] ?? '—') ?></td>
                                                    <td><?= number_format((float)($loss['value'] ?? 0), 2) ?></td>
                                                </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </details>
                            <?php else: ?>
                                —
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- Inputs JSON -->
        <div class="card shadow-sm mb-4 mt-4">
            <div class="card-header bg-light d-flex justify-content-between align-items-center" style="cursor: pointer;" data-bs-toggle="collapse" data-bs-target="#structuredInputsCollapse">
                <h5 class="mb-0 text-muted small"><i class="fas fa-cog me-2"></i><?= __('structured_inputs') ?></h5>
                <i class="fas fa-chevron-down"></i>
            </div>
            <div id="structuredInputsCollapse" class="collapse">
                <div class="card-body">
                    <?php
                    // Function to render all inputs structurally
                    function renderStructuredInputs($data) {
                        foreach ($data as $key => $value) {
                            echo "<h6 class='mt-3 mb-2 fw-bold text-secondary'>" . htmlspecialchars($key) . "</h6>";

                            if (is_array($value)) {
                                $isTuTable = false;

                                // Detect TU table keys like "(1,1,1)"
                                if (count($value) && preg_match('/^\(\d+,\d+,\d+\)$/', array_keys($value)[0])) {
                                    $isTuTable = true;
                                }

                                if ($isTuTable) {
                                    // TU table
                                    echo '<table class="table table-sm table-bordered mb-2 small">';
                                    echo '<thead><tr><th>' . __('col_piso') . '</th><th>' . __('col_apto') . '</th><th>' . __('col_tu_index') . '</th><th>' . __('col_value_m') . '</th></tr></thead><tbody>';
                                    foreach ($value as $tuple => $v) {
                                        $parts = explode(',', trim($tuple, '()'));
                                        echo '<tr>';
                                        echo '<td>' . htmlspecialchars($parts[0]) . '</td>';
                                        echo '<td>' . htmlspecialchars($parts[1]) . '</td>';
                                        echo '<td>' . htmlspecialchars($parts[2]) . '</td>';
                                        echo '<td>' . htmlspecialchars($v) . '</td>';
                                        echo '</tr>';
                                    }
                                    echo '</tbody></table>';
                                } else {
                                    // Regular array of arrays / objects
                                    $firstRow = reset($value);
                                    if (is_array($firstRow) || is_object($firstRow)) {
                                        $firstRow = (array)$firstRow;
                                        echo '<table class="table table-sm table-bordered mb-2 small">';
                                        echo '<thead><tr>';
                                        foreach ($firstRow as $col => $_) {
                                            echo '<th>' . htmlspecialchars($col) . '</th>';
                                        }
                                        echo '</tr></thead><tbody>';
                                        foreach ($value as $row) {
                                            $row = (array)$row;
                                            echo '<tr>';
                                            foreach ($row as $cell) {
                                                echo '<td>' . htmlspecialchars($cell) . '</td>';
                                            }
                                            echo '</tr>';
                                        }
                                        echo '</tbody></table>';
                                    } else {
                                        // Scalar array: show as table
                                        echo '<table class="table table-sm table-bordered mb-2 small">';
                                        echo '<tbody>';
                                        foreach ($value as $i => $v) {
                                            echo '<tr><td>' . htmlspecialchars($i) . '</td><td>' . htmlspecialchars($v) . '</td></tr>';
                                        }
                                        echo '</tbody></table>';
                                    }
                                }

                            } else {
                                // Scalar value: show as key-value row
                                echo '<table class="table table-sm table-bordered mb-2 small">';
                                echo '<tbody><tr><td style="width: 40%">' . htmlspecialchars($key) . '</td><td>' . htmlspecialchars($value) . '</td></tr></tbody></table>';
                            }
                        }
                    }

                    renderStructuredInputs($viewModel->inputs);
                    ?>
                </div>
            </div>
        </div>

        <!-- Solver Info -->
        <?php if (!empty($viewModel->meta['solver_status']) || !empty($viewModel->meta['solver_log'])): ?>
        <div class="card shadow-sm mb-4 mt-4">
            <div class="card-header bg-light d-flex justify-content-between align-items-center" style="cursor: pointer;" data-bs-toggle="collapse" data-bs-target="#solverInfoCollapse">
                <h5 class="mb-0 text-muted small"><i class="fas fa-info-circle me-2"></i><?= __('solver_status_title') ?> (Debug)</h5>
                <i class="fas fa-chevron-down"></i>
            </div>
            <div id="solverInfoCollapse" class="collapse">
                <div class="card-body">
                    <dl class="row mb-0">
                        <dt class="col-sm-3 small"><?= __('solver_status_title') ?></dt>
                        <dd class="col-sm-9"><span class="badge bg-secondary"><?= htmlspecialchars($viewModel->meta['solver_status'] ?? 'N/A') ?></span></dd>
                    </dl>
                    <?php if (!empty($viewModel->meta['solver_log'])): ?>
                    <hr>
                    <h6 class="small fw-bold"><?= __('solver_log_title') ?></h6>
                    <pre class="bg-light p-2 rounded small" style="max-height: 300px; overflow-y: auto; font-size: 0.75rem;"><?= htmlspecialchars($viewModel->meta['solver_log']) ?></pre>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php endif; ?>

    <?php endif; ?>

</div>

<?php include __DIR__ . '/templates/footer.php'; ?>
<script>
$(document).ready(function() {
    // Initialize DataTable
    $('#detailTable').DataTable({
        pageLength: 15,
        lengthMenu: [15, 30, 50],
        scrollX: true,
        ordering: true,
        autoWidth: false
    });

    // Summary Chart
    const summaryData = <?= json_encode($viewModel->summary) ?>;
    
    // Helper to translate keys in JS (similar to PHP logic)
    function translateKey(k) {
        const translations = {
            'contract_version': <?= json_encode(__('metric_contract_version')) ?>,
            'piso_max': <?= json_encode(__('metric_piso_max')) ?>,
            'total_tus': <?= json_encode(__('metric_total_tus')) ?>,
            'status': <?= json_encode(__('metric_status')) ?>,
            'avg_nivel_tu': <?= json_encode(__('metric_avg_nivel_tu')) ?>,
            'min_nivel_tu': <?= json_encode(__('metric_min_nivel_tu')) ?>,
            'max_nivel_tu': <?= json_encode(__('metric_max_nivel_tu')) ?>
        };
        return translations[k] || k;
    }

    const summaryLabels = Object.keys(summaryData).map(translateKey);
    const summaryValues = Object.values(summaryData);
    const summaryEl = document.getElementById('summaryChart');
    if (summaryEl) {
        new Chart(summaryEl, {
            type: 'bar',
            data: { labels: summaryLabels, datasets:[{ label: <?= json_encode(__('summary_kpis')) ?>, data: summaryValues }] },
            options: { responsive:true, scales:{ y:{ beginAtZero:true } } }
        });
    }

    // TU Histogram (nivelChart) with per-bin violation coloring
    const nivelValues = <?= json_encode(array_map(fn($d)=>(float)($d['nivel_tu'] ?? 0), $viewModel->details)) ?>;
    const COMPLIANCE_MIN = <?= $complianceMin ?>;
    const COMPLIANCE_MAX = <?= $complianceMax ?>;

    function buildHistogram(values, binSize=1){
        const bins = {};
        values.forEach(v => {
            const b = Math.floor(v/binSize)*binSize;
            if (!bins[b]) bins[b] = { count: 0, values: [] };
            bins[b].count += 1;
            bins[b].values.push(v);
        });
        const keys = Object.keys(bins).map(Number).sort((a,b)=>a-b);
        return {
            labels: keys.map(k=>`${k}–${k+binSize}`),
            data: keys.map(k=>bins[k].count),
            values: keys.map(k=>bins[k].values), // keep TU values per bin for coloring
            keys
        };
    }

    const hist = buildHistogram(nivelValues, 1);
    const nivelEl = document.getElementById('nivelChart'); // Get the canvas element

    if (nivelEl) { // Check if the element exists
        const ctx = nivelEl.getContext('2d');
        new Chart(ctx, {
            type: 'bar',
            data: {
                labels: hist.labels,
                datasets: [{
                    label: <?= json_encode(__('total_tus')) ?>,
                    data: hist.data,
                    backgroundColor: hist.values.map(binVals => {
                        if (binVals.some(v => v < COMPLIANCE_MIN)) return 'rgba(255,193,7,0.8)'; // LOW
                        if (binVals.some(v => v > COMPLIANCE_MAX)) return 'rgba(220,53,69,0.8)'; // HIGH
                        return 'rgba(13,110,253,0.8)'; // OK
                    })
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: { display: false },
                    title: {
                        display: true,
                        text: <?= json_encode(__('tu_histogram') . ' (dBµV)') ?>
                    },
                    annotation: {
                        annotations: {
                            minLine: {
                                type: 'line',
                                xMin: COMPLIANCE_MIN,
                                xMax: COMPLIANCE_MIN,
                                borderColor: 'yellow',
                                borderWidth: 2,
                                label: { content: 'Min', enabled: true, position: 'start' }
                            },
                            maxLine: {
                                type: 'line',
                                xMin: COMPLIANCE_MAX,
                                xMax: COMPLIANCE_MAX,
                                borderColor: 'red',
                                borderWidth: 2,
                                label: { content: 'Max', enabled: true, position: 'start' }
                            }
                        }
                    }
                },
                scales: {
                    y: { beginAtZero: true, title: { display:true, text: <?= json_encode(__('total_tus')) ?> } },
                    x: { title: { display:true, text: <?= json_encode(__('col_tu_id') . ' (dBµV)') ?> } }
                }
            }
        });
    }
});
</script>
