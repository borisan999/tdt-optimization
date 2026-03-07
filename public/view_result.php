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
                <div class="mt-3">
                    <a href="enter-data/<?= htmlspecialchars((string)($response['dataset_id'] ?? 0)) ?>" class="btn btn-primary me-2">
                        <i class="fas fa-edit"></i> <?= __('adjust_data_btn') ?>
                    </a>
                    <a href="optimization-logs" class="btn btn-secondary">
                        <i class="fas fa-file-alt"></i> Ver Logs del Solver
                    </a>
                </div>
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
        $canonical = $parser->canonical(); 
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
                       href="export_excel.php?opt_id=<?= urlencode($viewModel->meta['opt_id'] ?? 0) ?>&mode=detail"
                       title="<?= __('xls_btn_detail') ?>">
                       <i class="fas fa-table me-1"></i> <?= __('xls_btn_detail') ?>
                    </a>
                    <a class="btn btn-outline-success btn-sm"
                       href="export_excel.php?opt_id=<?= urlencode($viewModel->meta['opt_id'] ?? 0) ?>&mode=resumido"
                       title="<?= __('xls_btn_resumido') ?>">
                       <i class="fas fa-list-alt me-1"></i> <?= __('xls_btn_resumido') ?>
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
                    <a class="btn btn-primary btn-sm"
                       href="export_docx.php?opt_id=<?= urlencode($viewModel->meta['opt_id'] ?? 0) ?>&mode=report"
                       title="<?= __('export_technical_report') ?? 'Exportar Memoria Técnica' ?>">
                       <i class="fas fa-file-invoice me-1"></i> <?= __('export_technical_report') ?? 'Memoria Técnica' ?>
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
                    <a class="btn btn-secondary btn-sm"
                       href="optimization-logs"
                       title="<?= __('solver_logs') ?>">
                       <i class="fas fa-file-alt me-1"></i> <?= __('solver_logs') ?? 'Solver Logs' ?>
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
            'min_nivel_tu' => __('metric_min_nivel_tu'),
            'max_nivel_tu' => __('metric_max_nivel_tu'),
        ];
        $summaryMetrics['min_nivel_tu'] = $viewModel->summary['min_nivel_tu'] ?? '—';
        $summaryMetrics['max_nivel_tu'] = $viewModel->summary['max_nivel_tu'] ?? '—';

        foreach (['tu_total','compliance_pct','min_nivel_tu','max_nivel_tu'] as $key): 
          $value = $summaryMetrics[$key] ?? '—';
          $color = 'primary'; 
          if ($key === 'min_nivel_tu' || $key === 'max_nivel_tu') $color = 'info';
          if ($key === 'compliance_pct') $color = ($value >= 100) ? 'success' : 'warning';
          
          $displayValue = is_numeric($value) ? number_format((float)$value, ($key === 'tu_total' ? 0 : 2)) : $value;
          ?>
        <div class="col-md-3">
            <div class="card shadow-sm border-start border-4 border-<?= $color ?>">
                <div class="card-body">
                    <div class="fw-bold text-muted small mb-1"><?= htmlspecialchars($summaryLabels[$key]) ?></div>
                    <div class="fs-4 fw-bold"><?= htmlspecialchars($displayValue) ?><?= $key==='compliance_pct' ? '%' : '' ?><?= ($key === 'min_nivel_tu' || $key === 'max_nivel_tu') ? ' <small class="fs-6 text-muted">dBµV</small>' : '' ?></div>
                    <?php if($key==='compliance_pct' && is_numeric($value)): ?>
                    <div class="progress mt-2" style="height: 5px;">
                        <div class="progress-bar bg-<?= $color ?>" role="progressbar"
                            style="width: <?= $value ?>%" aria-valuenow="<?= $value ?>" aria-valuemin="0" aria-valuemax="100"></div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
    </div>

    <!-- Section Divider: Materiales -->
    <div class="d-flex align-items-center my-4">
        <hr class="flex-grow-1">
        <span class="px-3 text-muted fw-bold">📦 <?= __('materials_section') ?? 'Materiales' ?></span>
        <hr class="flex-grow-1">
    </div>

    <!-- Materials & Inventory Section -->
    <?php if ($isInventoryAvailable && isset($canonical)): 
        $aggregator = new \app\helpers\InventoryAggregator($canonical);
        $inventoryData = $aggregator->aggregate();
        $scopeSummaries = $inventoryData['totals']['Scope Summaries'] ?? [];
        $equipmentBreakdown = $inventoryData['totals']['Grand Total'] ?? [];
    ?>
    <div class="row mb-4">
        <div class="col-lg-7">
            <div class="card shadow-sm h-100 border-start border-4 border-warning">
                <div class="card-header bg-success text-white">
                    <h5 class="mb-0"><i class="fas fa-boxes me-2"></i><?= __('material_summary_by_layer') ?? 'Resumen de Materiales por Capa' ?></h5>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover table-sm mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th><?= __('col_layer') ?? 'Capa de Distribución' ?></th>
                                    <th class="text-center"><?= __('col_cable_m') ?? 'Cable Total (m)' ?></th>
                                    <th class="text-center"><?= __('col_equipment_uds') ?? 'Equipos (uds)' ?></th>
                                    <th class="text-center"><?= __('col_connectors_uds') ?? 'Conectores (uds)' ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                $layers = [
                                    'Vertical' => ['label' => 'Distribución Vertical', 'icon' => 'fa-arrows-alt-v'],
                                    'Horizontal' => ['label' => 'Distribución Horizontal', 'icon' => 'fa-arrows-alt-h'],
                                    'Apartamento' => ['label' => 'Interior de Apartamento', 'icon' => 'fa-home'],
                                ];
                                foreach ($layers as $key => $info): 
                                    $data = $scopeSummaries[$key] ?? ['cable_m' => 0, 'equipment_uds' => 0, 'connectors_uds' => 0];
                                ?>
                                <tr>
                                    <td class="ps-3"><i class="fas <?= $info['icon'] ?> me-2 text-muted"></i><strong><?= $info['label'] ?></strong></td>
                                    <td class="text-center"><?= number_format($data['cable_m'], 2) ?> m</td>
                                    <td class="text-center"><?= number_format($data['equipment_uds'], 0) ?> uds.</td>
                                    <td class="text-center"><?= number_format($data['connectors_uds'], 0) ?> uds.</td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                            <tfoot class="table-light fw-bold">
                                <?php 
                                $grandTotal = ['cable' => 0, 'equip' => 0, 'conn' => 0];
                                foreach ($scopeSummaries as $s) {
                                    $grandTotal['cable'] += $s['cable_m'];
                                    $grandTotal['equip'] += $s['equipment_uds'];
                                    $grandTotal['conn'] += $s['connectors_uds'];
                                }
                                ?>
                                <tr>
                                    <td class="ps-3">TOTAL PROYECTO</td>
                                    <td class="text-center text-primary"><?= number_format($grandTotal['cable'], 2) ?> m</td>
                                    <td class="text-center text-primary"><?= number_format($grandTotal['equip'], 0) ?> uds.</td>
                                    <td class="text-center text-primary"><?= number_format($grandTotal['conn'], 0) ?> uds.</td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-lg-5">
            <div class="card shadow-sm h-100 border-start border-4 border-warning">
                <div class="card-header bg-secondary text-white">
                    <h5 class="mb-0"><i class="fas fa-list-ul me-2"></i><?= __('equipment_breakdown') ?? 'Desglose de Equipos' ?></h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <h6><?= __('equipment') ?? 'Equipos' ?></h6>
                            <div class="table-responsive">
                                <table class="table table-sm small mb-0">
                                    <tbody>
                                    <?php 
                                        $equipmentList = array_filter($equipmentBreakdown, fn($item) => $item['Tipo'] === 'Equipo');
                                        usort($equipmentList, fn($a, $b) => strcmp($a['Componente'], $b['Componente']));
                                        foreach ($equipmentList as $item):
                                    ?>
                                        <tr>
                                            <td><?= htmlspecialchars($item['Componente']) ?></td>
                                            <td class="text-end fw-bold"><?= is_float($item['Cantidad']) ? number_format($item['Cantidad'], 1) : $item['Cantidad'] ?></td>
                                            <td><?= htmlspecialchars($item['Unidad']) ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <h6><?= __('cables_and_connectors') ?? 'Cableado y Conectores' ?></h6>
                            <div class="table-responsive">
                                <table class="table table-sm small mb-0">
                                    <tbody>
                                    <?php 
                                        $materialList = array_filter($equipmentBreakdown, fn($item) => $item['Tipo'] === 'Cable' || $item['Tipo'] === 'Conector');
                                        usort($materialList, fn($a, $b) => strcmp($a['Componente'], $b['Componente']));
                                        foreach ($materialList as $item):
                                    ?>
                                        <tr>
                                            <td><?= htmlspecialchars($item['Componente']) ?></td>
                                            <td class="text-end fw-bold"><?= is_float($item['Cantidad']) ? number_format($item['Cantidad'], 1) : $item['Cantidad'] ?></td>
                                            <td><?= htmlspecialchars($item['Unidad']) ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Section Divider: Señal -->
    <div class="d-flex align-items-center my-4">
        <hr class="flex-grow-1">
        <span class="px-3 text-muted fw-bold">📡 <?= __('signal_section') ?? 'Señal' ?></span>
        <hr class="flex-grow-1">
    </div>

    <!-- Signal Distribution Chain -->
    <div class="card shadow-sm mb-4 border-start border-4 border-primary">
        <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center" style="cursor: pointer;" data-bs-toggle="collapse" data-bs-target="#signalChainCollapse">
            <h5 class="mb-0"><i class="fas fa-project-diagram me-2"></i><?= __('signal_distribution_chain') ?? 'Cadena de Distribución de Señal (para Dibujo Técnico)' ?></h5>
            <i class="fas fa-chevron-down"></i>
        </div>
        <div id="signalChainCollapse" class="collapse show">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover table-sm mb-0">
                        <thead class="table-light">
                            <tr>
                                <th><?= __('col_node') ?></th>
                                <th><?= __('col_equipment') ?></th>
                                <th class="text-center"><?= __('col_input_level') ?></th>
                                <th class="text-center"><?= __('col_output_level') ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $raw_details = json_decode($viewModel->results['detail_json'] ?? '[]', true);
                            if (!empty($raw_details)): 
                                $first = $raw_details[0];
                                $piso_troncal = $viewModel->inputs['p_troncal'] ?? 0;
                                
                                $p_in_general = (float)($first['P_in (entrada) (dBµV)'] ?? 0);
                                $loss_ant_cable = (float)($first['Pérdida Antena→Troncal (cable) (dB)'] ?? 0);
                                $loss_ant_conn  = (float)($first['Pérdida Antena↔Troncal (conectores) (dB)'] ?? 0);
                                $p_in_troncal = $p_in_general - $loss_ant_cable - $loss_ant_conn;
                                $p_out_troncal = $p_in_troncal - (float)($first['Pérdida Repartidor Troncal (dB)'] ?? 0);
                            ?>
                                <!-- Headend & Trunk -->
                                <tr class="table-info">
                                    <td class="ps-3"><strong><?= __('headend') ?></strong></td>
                                    <td>—</td>
                                    <td class="text-center">—</td>
                                    <td class="text-center"><strong><?= number_format($p_in_general, 1) ?></strong></td>
                                </tr>
                                <tr>
                                    <td class="ps-3"><?= __('trunk') ?> (P<?= $piso_troncal ?>)</td>
                                    <td><?= htmlspecialchars($first['Repartidor Troncal'] ?? 'N/A') ?></td>
                                    <td class="text-center"><?= number_format($p_in_troncal, 1) ?></td>
                                    <td class="text-center">
                                        <?php 
                                            $badgeColor = 'success';
                                            if ($p_out_troncal < ($complianceMin + 5) || $p_out_troncal > ($complianceMax - 5)) {
                                                $badgeColor = 'warning';
                                            }
                                        ?>
                                        <span class="badge bg-<?= $badgeColor ?>"><?= number_format($p_out_troncal, 1) ?></span>
                                    </td>
                                </tr>

                                <?php 
                                $floors = [];
                                foreach ($raw_details as $row) {
                                    $p = $row['Piso'] ?? $row['piso'] ?? 0;
                                    if (!isset($floors[$p])) $floors[$p] = $row;
                                }
                                krsort($floors);

                                foreach ($floors as $p => $f):
                                    $p_in_bloque_path = $p_out_troncal - ((float)($f['Pérdida Feeder (cable) (dB)'] ?? 0) + (float)($f['Pérdida Feeder (conectores) (dB)'] ?? 0));
                                    $p_in_deriv = $p_in_bloque_path - (float)($f['Pérdida Riser dentro del Bloque (dB)'] ?? 0) - (float)($f['Riser Atenuación Taps (dB)'] ?? 0);
                                    $p_deriv_out = $p_in_deriv - (float)($f['Pérdida Derivador Piso (dB)'] ?? 0);
                                ?>
                                    <tr class="bg-light">
                                        <td class="ps-3"><strong><?= __('floor') ?> <?= $p ?> (DER)</strong></td>
                                        <td><?= htmlspecialchars($f['Derivador Piso'] ?? 'N/A') ?></td>
                                        <td class="text-center"><?= number_format($p_in_deriv, 1) ?></td>
                                        <td class="text-center">
                                            <?php 
                                                $badgeColor = 'success';
                                                if ($p_deriv_out < ($complianceMin + 5) || $p_deriv_out > ($complianceMax - 5)) {
                                                    $badgeColor = 'warning';
                                                }
                                            ?>
                                            <span class="badge bg-<?= $badgeColor ?>"><?= number_format($p_deriv_out, 1) ?></span> <small class="text-muted">(deriv)</small>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="card-footer bg-white small text-muted">
                <i class="fas fa-info-circle me-1"></i> <?= __('click_expand_hint') ?? 'Esta tabla muestra los niveles clave para el dibujo del plano unifilar.' ?>
            </div>
        </div>
    </div>

    <!-- Section Divider: Instalación -->
    <div class="d-flex align-items-center my-4">
        <hr class="flex-grow-1">
        <span class="px-3 text-muted fw-bold">🔌 <?= __('installation_section') ?? 'Instalación' ?></span>
        <hr class="flex-grow-1">
    </div>

    <!-- Cable Runs: Physical Topology -->
    <div class="card shadow-sm mb-4 border-start border-4 border-secondary">
        <div class="card-header bg-dark text-white d-flex justify-content-between align-items-center" style="cursor: pointer;" data-bs-toggle="collapse" data-bs-target="#cableRunsCollapse">
            <h5 class="mb-0"><i class="fas fa-route me-2"></i><?= __('cable_runs_installation_plan') ?? 'Plan de Instalación de Cableado' ?></h5>
            <span class="small">
                <?php
                $num_pisos = count(array_unique(array_column($raw_details, 'Piso')));
                echo sprintf('%d tomas en %d pisos — %s', count($raw_details), $num_pisos, 'Ver Plan de Cableado');
                ?>
                <i class="fas fa-chevron-down ms-2"></i>
            </span>
        </div>
        <div id="cableRunsCollapse" class="collapse <?= count($raw_details) < 20 ? 'show' : '' ?>">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover table-sm mb-0">
                        <thead class="table-light">
                            <tr>
                                <th><?= __('col_piso') ?></th>
                                <th><?= __('col_apto') ?></th>
                                <th><?= __('col_repartidor_model') ?? 'Modelo REP' ?></th>
                                <th class="text-center"><?= __('col_der_rep') ?? 'DER → REP (m)' ?> <i class="fas fa-info-circle text-muted" title="Derivador → Repartidor"></i></th>
                                <th class="text-center"><?= __('col_rep_tu') ?? 'REP → TU (m)' ?> <i class="fas fa-info-circle text-muted" title="Repartidor → TU"></i></th>
                                <th class="text-center"><?= __('col_tu_id') ?></th>
                                <th class="text-center"><?= __('output_signal') ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            if (!empty($raw_details)): 
                                foreach ($raw_details as $row):
                                    $p = $row['Piso'] ?? $row['piso'] ?? 0;
                                    $a = $row['Apto'] ?? $row['apto'] ?? 0;
                                    $tu_code = $row['Toma'] ?? $row['tu_id'] ?? '—';
                                    
                                    $d_seg1 = (float)($viewModel->inputs['largo_cable_derivador_repartidor']["{$p}|{$a}"] ?? 0);
                                    
                                    $tu_idx = 1;
                                    if (preg_match('/TU(\d+)$/i', $tu_code, $m)) {
                                        $tu_idx = (int)$m[1];
                                    } elseif (preg_match('/(\d+)$/', $tu_code, $m)) {
                                        $tu_idx = (int)$m[1];
                                    }
                                    
                                    $d_seg2 = (float)($viewModel->inputs['largo_cable_tu']["{$p}|{$a}|{$tu_idx}"] ?? 0);
                                    
                                    $nivel_final = (float)($row['Nivel TU Final (dBµV)'] ?? 0);
                            ?>
                                <tr>
                                    <td class="ps-3"><?= $p ?></td>
                                    <td><?= $a ?></td>
                                    <td><small><?= htmlspecialchars($row['Repartidor Apt'] ?? 'N/A') ?></small></td>
                                    <td class="text-center"><?= number_format($d_seg1, 1) ?></td>
                                    <td class="text-center"><?= number_format($d_seg2, 1) ?></td>
                                    <td class="text-center"><small><?= htmlspecialchars($tu_code) ?></small></td>
                                    <td class="text-center"><strong><?= number_format($nivel_final, 1) ?></strong></td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="card-footer bg-white small text-muted">
                <i class="fas fa-info-circle me-1"></i> <?= __('cable_run_hint') ?? 'DER → REP: Derivador de piso a repartidor de apto. REP → TU: Repartidor de apto a toma (TU).' ?>
            </div>
        </div>
    </div>

    <!-- Signal Level Distribution (Histogram) -->
    <div class="row mb-4">
        <div class="col-12 mb-3">
            <div class="card shadow-sm">
                <div class="card-body">
                    <h5 class="card-title"><?= __('signal_level_distribution') ?? 'Distribución de Niveles de Señal' ?></h5>
                    <canvas id="nivelChart" height="80"></canvas>
                </div>
            </div>
        </div>
    </div>

    <!-- Signal Compliance Issues -->
    <h4><?= __('signal_compliance_issues') ?? 'Problemas de Cumplimiento de Señal' ?></h4>
    <?php if(empty($viewModel->violations)): ?>
        <div class="alert alert-success shadow-sm"><i class="fas fa-check-circle me-2"></i><?= __('all_tus_ok') ?></div>
    <?php else: ?>
        <div class="d-flex justify-content-between align-items-center mb-2">
            <div class="text-muted"><?= count($viewModel->violations) ?> <?= __('tus_out_of_norm') ?></div>
            <a class="btn btn-outline-danger btn-sm" href="export_csv.php?opt_id=<?= $viewModel->meta['opt_id'] ?>&type=violations"><?= __('export_violations_csv') ?></a>
        </div>
        <table class="table table-sm table-bordered table-striped shadow-sm">
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
    <?php if (!empty($viewModel->details)): ?>
    <div class="d-flex justify-content-between align-items-center mt-4 mb-2">
        <h4 class="mb-0"><?= __('detail_tus') ?> (<?= count($viewModel->details) ?>)</h4>
    </div>
    <div class="table-responsive">
        <table id="detailTable" class="table table-striped table-bordered table-sm nowrap shadow-sm">
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
    <?php endif; ?>

    <hr class="my-5">

    <!-- ADVANCED ENGINEERING DATA (Collapsed by default) -->
    <div class="bg-light p-4 rounded-3 border">
        <h4 class="text-muted mb-4"><i class="fas fa-microchip me-2"></i><?= __('advanced_engineering_data') ?? 'Datos Avanzados de Ingeniería' ?></h4>

        <!-- Engineering Input Parameters -->
        <div class="card shadow-sm mb-4">
            <div class="card-header bg-light d-flex justify-content-between align-items-center" style="cursor: pointer;" data-bs-toggle="collapse" data-bs-target="#structuredInputsCollapse">
                <h5 class="mb-0 text-muted small fw-bold"><i class="fas fa-cog me-2"></i><?= __('engineering_input_parameters') ?? 'Parámetros de Entrada de Ingeniería' ?></h5>
                <i class="fas fa-chevron-down"></i>
            </div>
            <div id="structuredInputsCollapse" class="collapse">
                <div class="card-body">
                    <?php
                    // Function to render all inputs structurally
                    function renderStructuredInputs($data) {
                        foreach ($data as $key => $value) {
                            echo "<h6 class='mt-3 mb-2 fw-bold text-secondary'>" . htmlspecialchars((string)$key) . "</h6>";

                            if (is_array($value)) {
                                $isTuTable = false;

                                if (count($value) > 0) {
                                    $firstKey = (string)array_keys($value)[0];
                                    if (preg_match('/^\(\d+,\d+,\d+\)$/', $firstKey) || preg_match('/^\d+\|\d+\|\d+$/', $firstKey)) {
                                        $isTuTable = true;
                                    }
                                }

                                if ($isTuTable) {
                                    echo '<table class="table table-sm table-bordered mb-2 small">';
                                    echo '<thead><tr><th>' . __('col_piso') . '</th><th>' . __('col_apto') . '</th><th>' . __('col_tu_index') . '</th><th>' . __('col_value_m') . '</th></tr></thead><tbody>';
                                    foreach ($value as $tuple => $v) {
                                        $parts = preg_split('/[,|]/', trim((string)$tuple, '()'));
                                        echo '<tr>';
                                        echo '<td>' . htmlspecialchars($parts[0] ?? '?') . '</td>';
                                        echo '<td>' . htmlspecialchars($parts[1] ?? '?') . '</td>';
                                        echo '<td>' . htmlspecialchars($parts[2] ?? '?') . '</td>';
                                        echo '<td>' . htmlspecialchars((string)$v) . '</td>';
                                        echo '</tr>';
                                    }
                                    echo '</tbody></table>';
                                } else {
                                    $firstRow = reset($value);
                                    if (is_array($firstRow) || is_object($firstRow)) {
                                        $firstRow = (array)$firstRow;
                                        echo '<table class="table table-sm table-bordered mb-2 small">';
                                        echo '<thead><tr>';
                                        foreach ($firstRow as $col => $_) {
                                            echo '<th>' . htmlspecialchars((string)$col) . '</th>';
                                        }
                                        echo '</tr></thead><tbody>';
                                        foreach ($value as $row) {
                                            $row = (array)$row;
                                            echo '<tr>';
                                            foreach ($row as $cell) {
                                                echo '<td>' . htmlspecialchars((string)$cell) . '</td>';
                                            }
                                            echo '</tr>';
                                        }
                                        echo '</tbody></table>';
                                    } else {
                                        echo '<table class="table table-sm table-bordered mb-2 small">';
                                        echo '<tbody>';
                                        foreach ($value as $i => $v) {
                                            echo '<tr><td>' . htmlspecialchars((string)$i) . '</td><td>' . htmlspecialchars((string)$v) . '</td></tr>';
                                        }
                                        echo '</tbody></table>';
                                    }
                                }

                            } else {
                                echo '<table class="table table-sm table-bordered mb-2 small">';
                                echo '<tbody><tr><td style="width: 40%">' . htmlspecialchars((string)$key) . '</td><td>' . htmlspecialchars((string)$value) . '</td></tr></tbody></table>';
                            }
                        }
                    }

                    renderStructuredInputs($viewModel->inputs);
                    ?>
                </div>
            </div>
        </div>

        <!-- Advanced Solver Diagnostics -->
        <div class="card shadow-sm border-info">
            <div class="card-header bg-light d-flex justify-content-between align-items-center" style="cursor: pointer;" data-bs-toggle="collapse" data-bs-target="#solverInfoCollapse">
                <h5 class="mb-0 text-info small fw-bold"><i class="fas fa-terminal me-2"></i><?= __('advanced_solver_diagnostics') ?? 'Diagnósticos Avanzados del Solver' ?></h5>
                <i class="fas fa-chevron-down text-info"></i>
            </div>
            <div id="solverInfoCollapse" class="collapse">
                <div class="card-body">
                    <div class="row mb-3">
                        <div class="col-md-3">
                            <div class="small text-muted fw-bold"><?= __('solver_status_title') ?? 'Estado del Solver' ?></div>
                            <?php 
                                $sStatus = $viewModel->meta['solver_status'] ?? 'N/A';
                                $badgeClass = 'bg-secondary';
                                if (stripos($sStatus, 'Optimal') !== false) $badgeClass = 'bg-success';
                                elseif (stripos($sStatus, 'Infeasible') !== false) $badgeClass = 'bg-danger';
                                elseif (stripos($sStatus, 'Feasible') !== false) $badgeClass = 'bg-primary';
                            ?>
                            <span class="badge <?= $badgeClass ?>"><?= htmlspecialchars($sStatus) ?></span>
                        </div>
                        <div class="col-md-3">
                            <div class="small text-muted fw-bold"><?= __('execution_time') ?></div>
                            <?php
                                $startTime = !empty($viewModel->meta['started_at']) ? new DateTime($viewModel->meta['started_at']) : null;
                                $endTime = !empty($viewModel->meta['finished_at']) ? new DateTime($viewModel->meta['finished_at']) : null;
                                if ($startTime && $endTime) {
                                    $interval = $startTime->diff($endTime);
                                    echo $interval->format('%H:%I:%S');
                                } else {
                                    echo 'N/A';
                                }
                            ?>
                        </div>
                        <div class="col-md-3">
                            <div class="small text-muted fw-bold"><?= __('started_at_label') ?></div>
                            <span class="small"><?= htmlspecialchars($viewModel->meta['started_at'] ?? 'N/A') ?></span>
                        </div>
                        <div class="col-md-3">
                            <div class="small text-muted fw-bold"><?= __('finished_at_label') ?></div>
                            <span class="small"><?= htmlspecialchars($viewModel->meta['finished_at'] ?? 'N/A') ?></span>
                        </div>
                    </div>

                    <?php if (!empty($viewModel->meta['error_message'])): ?>
                        <div class="alert alert-danger py-2 small">
                            <i class="fas fa-exclamation-circle me-1"></i> <?= htmlspecialchars($viewModel->meta['error_message']) ?>
                        </div>
                    <?php endif; ?>

                    <?php if (!empty($viewModel->meta['solver_log'])): ?>
                    <div class="mt-3">
                        <button class="btn btn-outline-secondary btn-sm mb-2" type="button" data-bs-toggle="collapse" data-bs-target="#solverLogContent">
                            <i class="fas fa-terminal me-1"></i> <?= __('view_solver_log') ?>
                        </button>
                        <div id="solverLogContent" class="collapse">
                            <pre class="bg-dark text-light p-3 rounded small mt-2" style="max-height: 400px; overflow-y: auto; font-size: 0.7rem; border-left: 4px solid #17a2b8;"><?= htmlspecialchars($viewModel->meta['solver_log']) ?></pre>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Raw Summary Metrics -->
        <div class="mt-4">
            <button class="btn btn-link btn-sm text-muted p-0" type="button" data-bs-toggle="collapse" data-bs-target="#rawMetricsCollapse">
                <?= __('view_raw_summary_metrics') ?? 'Ver Métricas de Resumen Crudas' ?>
            </button>
            <div id="rawMetricsCollapse" class="collapse">
                <table class="table table-bordered table-sm mt-2">
                    <tbody>
                    <?php foreach ($viewModel->summary as $k => $v): 
                        $label = __('metric_' . $k);
                        if ($label === 'metric_' . $k) $label = $k;
                    ?>
                        <tr>
                            <th class="bg-light" style="width: 40%;"><?= htmlspecialchars((string)$label) ?></th>
                            <td><?= is_numeric($v) && $label === __('metric_avg_nivel_tu') ? number_format((float)$v, 2) : (is_scalar($v) ? htmlspecialchars((string)$v) : htmlspecialchars(json_encode($v, JSON_UNESCAPED_UNICODE))) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

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
