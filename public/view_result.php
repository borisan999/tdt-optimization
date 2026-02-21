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
                    <h4 class="mb-0"><i class="fas fa-exclamation-triangle"></i> Optimization Infeasible</h4>
                </div>
                <div class="card-body">
                    <p class="lead">The solver could not find a feasible solution for the given input parameters.</p>
                    <hr>
                    <dl class="row">
                        <dt class="col-sm-3">Dataset ID</dt>
                        <dd class="col-sm-9"><?= htmlspecialchars($result['dataset_id'] ?? 'N/A') ?></dd>

                        <dt class="col-sm-3">Optimization ID</dt>
                        <dd class="col-sm-9"><?= htmlspecialchars($result['opt_id'] ?? 'N/A') ?></dd>

                        <dt class="col-sm-3">Solver Message</dt>
                        <dd class="col-sm-9">
                            <code class="text-danger"><?= htmlspecialchars($result['message'] ?? 'No message provided.') ?></code>
                        </dd>
                    </dl>
                    <hr>
                    <h5>Possible Reasons:</h5>
                    <ul>
                        <li>Excessive cable lengths causing high attenuation.</li>
                        <li>Trunk power level is insufficient for the building's size.</li>
                        <li>The selected passive components (splitters, taps) are not suitable.</li>
                        <li>Minimum required signal level (Nivel Mínimo) is set too high.</li>
                        <li>Maximum allowed signal level (Nivel Máximo) is set too low.</li>
                    </ul>
                    <a href="enter-data/<?= htmlspecialchars($result['dataset_id'] ?? 0) ?>" class="btn btn-primary mt-3">
                        <i class="fas fa-edit"></i> Review and Adjust Input Data
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
                <h4 class="mb-0"><i class="fas fa-exclamation-triangle"></i> Optimization Infeasible</h4>
            </div>
            <div class="card-body">
                <p class="lead">The solver could not find a feasible solution for the given input parameters.</p>
                <hr>
                <dl class="row">
                    <dt class="col-sm-3">Dataset ID</dt>
                    <dd class="col-sm-9"><?= htmlspecialchars((string)($response['dataset_id'] ?? 'N/A')) ?></dd>

                    <dt class="col-sm-3">Optimization ID</dt>
                    <dd class="col-sm-9"><?= htmlspecialchars((string)($response['opt_id'] ?? 'N/A')) ?></dd>

                    <dt class="col-sm-3">Solver Message</dt>
                    <dd class="col-sm-9">
                        <code class="text-danger"><?= htmlspecialchars($response['message'] ?? 'No message provided.') ?></code>
                    </dd>
                </dl>
                <hr>
                <h5>Possible Reasons:</h5>
                <ul>
                    <li>Excessive cable lengths causing high attenuation.</li>
                    <li>Trunk power level is insufficient for the building's size.</li>
                    <li>The selected passive components (splitters, taps) are not suitable.</li>
                    <li>Minimum required signal level (Nivel Mínimo) is set too high.</li>
                    <li>Maximum allowed signal level (Nivel Máximo) is set too low.</li>
                </ul>
                <a href="enter-data/<?= htmlspecialchars((string)($response['dataset_id'] ?? 0)) ?>" class="btn btn-primary mt-3">
                    <i class="fas fa-edit"></i> Review and Adjust Input Data
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

    <h2 class="mb-3">Optimization Result Details</h2>

    <div class="mb-3 d-flex gap-2 flex-wrap">
        <a class="btn btn-success btn-sm"
           href="export_input_excel.php?opt_id=<?= urlencode($viewModel->meta['opt_id'] ?? 0) ?>">
           <i class="fas fa-file-excel"></i> Export Inputs (XLSX)
        </a>
        <a class="btn btn-outline-success btn-sm"
           href="export_csv.php?opt_id=<?= urlencode($viewModel->meta['opt_id'] ?? 0) ?>&type=detail">
           Export TUs Detail (CSV)
        </a>
        <a class="btn btn-outline-secondary btn-sm <?= !$isInventoryAvailable ? 'disabled' : '' ?>"
           href="<?= $isInventoryAvailable ? 'export_csv.php?opt_id=' . urlencode($viewModel->meta['opt_id'] ?? 0) . '&type=inventory' : '#' ?>"
           <?= !$isInventoryAvailable ? 'title="Canonical inventory data not available for this result. Run optimization to generate it."' : '' ?>>
           Export Inventory (CSV)
        </a>
        <a class="btn btn-primary btn-sm"
           href="export_docx.php?opt_id=<?= urlencode($viewModel->meta['opt_id'] ?? 0) ?>">
           Export Memoria de Diseño (DOCX)
        </a>
    </div>

    <!-- Summary Cards -->
    <div class="row mb-4 text-center">
    <?php foreach (['tu_total','compliance_pct','tu_low','tu_high'] as $key): 
          $value = $summaryMetrics[$key] ?? '—';
          $color = ($key==='tu_low') ? 'warning' : (($key==='tu_high') ? 'danger' : 'primary'); ?>
        <div class="col-md-3">
            <div class="card shadow-sm border-<?= $color ?>">
                <div class="card-body">
                    <div class="fw-bold text-muted"><?= ucfirst(str_replace('_',' ',$key)) ?></div>
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
            <strong>Parsing warnings:</strong>
            <ul>
                <?php foreach ($viewModel->warnings as $w): ?>
                    <li><?= htmlspecialchars($w) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <?php if (!empty($viewModel->details)): ?>

        <!-- Summary Metrics -->
        <h4>Summary Metrics</h4>
        <table class="table table-bordered table-sm">
            <tbody>
            <?php foreach ($viewModel->summary as $k => $v): ?>
                <tr>
                    <th><?= htmlspecialchars($k) ?></th>
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
                        <h5 class="card-title">Summary KPIs</h5>
                        <canvas id="summaryChart"></canvas>
                    </div>
                </div>
            </div>
            <div class="col-md-6 mb-3">
                <div class="card shadow-sm">
                    <div class="card-body">
                        <h5 class="card-title">TU Histogram</h5>
                        <canvas id="nivelChart" height="120"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <!-- Violations -->
        <h4>Violaciones de Nivel TU</h4>
        <?php if(empty($viewModel->violations)): ?>
            <div class="alert alert-success">Todas las tomas cumplen con la banda normativa.</div>
        <?php else: ?>
            <div class="d-flex justify-content-between align-items-center mb-2">
                <div class="text-muted"><?= count($viewModel->violations) ?> tomas fuera de norma</div>
                <a class="btn btn-outline-danger btn-sm" href="export_csv.php?opt_id=<?= $viewModel->meta['opt_id'] ?>&type=violations">Exportar violaciones (CSV)</a>
            </div>
            <table class="table table-sm table-bordered table-striped">
            <thead>
            <tr>
                <th>Toma</th><th>Piso</th><th>Apto</th><th>Nivel TU (dBµV)</th><th>Tipo</th><th>Δ vs Norma (dB)</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach($viewModel->violations as $v): ?>
            <tr class="<?= ($v['_violation_type'] ?? '')==='LOW'?'table-warning':'table-danger' ?>">
                <td><?= htmlspecialchars($v['tu_id'] ?? '—') ?></td>
                <td><?= htmlspecialchars($v['piso'] ?? '—') ?></td>
                <td><?= htmlspecialchars($v['apto'] ?? '—') ?></td>
                <td><?= number_format((float)($v['nivel_tu'] ?? 0),2) ?></td>
                <td><?= ($v['_violation_type'] ?? '')==='LOW'?'Bajo norma':'Sobre norma' ?></td>
                <td><?= number_format((float)($v['_violation_delta'] ?? 0),2) ?></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
            </table>
        <?php endif; ?>

        <!-- Detail TUs Table -->
        <h4>Detail TUs (<?= count($viewModel->details) ?>)</h4>
        <div class="table-responsive">
            <table id="detailTable" class="table table-striped table-bordered table-sm nowrap">
                <thead class="table-light">
                    <tr>
                        <th>TU ID</th>
                        <th>Piso</th>
                        <th>Apto</th>
                        <th>Bloque</th>
                        <th>Nivel TU (dBµV)</th>
                        <th>Min</th>
                        <th>Max</th>
                        <th>Cumple</th>
                        <th>Losses</th>
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
                                    <summary>Show losses</summary>
                                    <div class="table-responsive mt-2 mb-2" style="max-height:300px; overflow:auto;">
                                        <table class="table table-sm table-bordered mb-0">
                                            <thead class="table-light">
                                                <tr>
                                                    <th>Segment</th>
                                                    <th>Value (dB)</th>
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
        <details>
            <summary>Structured Inputs (Form-style)</summary>
            <div class="mt-2">

                <?php
                // Function to render all inputs structurally
                function renderStructuredInputs($data) {
                    foreach ($data as $key => $value) {
                        echo "<h5 class='mt-3 mb-2'>" . htmlspecialchars($key) . "</h5>";

                        if (is_array($value)) {
                            $isTuTable = false;

                            // Detect TU table keys like "(1,1,1)"
                            if (count($value) && preg_match('/^\(\d+,\d+,\d+\)$/', array_keys($value)[0])) {
                                $isTuTable = true;
                            }

                            if ($isTuTable) {
                                // TU table
                                echo '<table class="table table-sm table-bordered mb-2">';
                                echo '<thead><tr><th>Piso</th><th>Apto</th><th>TU Index</th><th>Value (m)</th></tr></thead><tbody>';
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
                                    echo '<table class="table table-sm table-bordered mb-2">';
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
                                    echo '<table class="table table-sm table-bordered mb-2">';
                                    echo '<tbody>';
                                    foreach ($value as $i => $v) {
                                        echo '<tr><td>' . htmlspecialchars($i) . '</td><td>' . htmlspecialchars($v) . '</td></tr>';
                                    }
                                    echo '</tbody></table>';
                                }
                            }

                        } else {
                            // Scalar value: show as key-value row
                            echo '<table class="table table-sm table-bordered mb-2">';
                            echo '<tbody><tr><td>' . htmlspecialchars($key) . '</td><td>' . htmlspecialchars($value) . '</td></tr></tbody></table>';
                        }
                    }
                }

                renderStructuredInputs($viewModel->inputs);
                ?>
            </div>
        </details>

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
    const summaryLabels = <?= json_encode(array_keys($viewModel->summary)) ?>;
    const summaryValues = <?= json_encode(array_values($viewModel->summary)) ?>;
    const summaryEl = document.getElementById('summaryChart');
    if (summaryEl) {
        new Chart(summaryEl, {
            type: 'bar',
            data: { labels: summaryLabels, datasets:[{ label:'Summary KPIs', data: summaryValues }] },
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
                    label: 'Cantidad de TUs',
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
                        text: 'Distribución de Nivel TU Final (dBµV)'
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
                    y: { beginAtZero: true, title: { display:true, text:'Cantidad de TUs' } },
                    x: { title: { display:true, text:'Nivel TU (dBµV)' } }
                }
            }
        });
    }
});
</script>
