<?php
// public/view_dataset.php
/**
 * Modern Dataset Dashboard - View/Manage a single dataset
 */
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . "/../app/auth/require_login.php";
require_once __DIR__ . "/../app/config/db.php";
require_once __DIR__ . "/../app/models/Dataset.php";

$dataset_id = (int)($_GET['id'] ?? 0);
if (!$dataset_id) {
    header("Location: history");
    exit;
}

$datasetModel = new Dataset();
$dataset = $datasetModel->get($dataset_id);

if (!$dataset) {
    include __DIR__ . "/templates/header.php";
    include __DIR__ . "/templates/navbar.php";
    echo "<div class='container mt-4'><div class='alert alert-danger'>Dataset #$dataset_id not found.</div></div>";
    include __DIR__ . "/templates/footer.php";
    exit;
}

$canonical = json_decode($dataset['canonical_json'] ?? '{}', true);
$opt = $datasetModel->getLatestOptimization($dataset_id);

include __DIR__ . "/templates/header.php";
include __DIR__ . "/templates/navbar.php";

// Simple helper to humanize keys
function humanize($key) {
    return ucwords(str_replace('_', ' ', $key));
}
?>

<div class="container my-5">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="mb-0 text-primary"><?= htmlspecialchars($dataset['dataset_name'] ?? __('unnamed_dataset')) ?></h2>
            <div class="text-muted small"><?= __('id') ?>: #<?= $dataset_id ?> • <?= __('status') ?>: <span class="badge bg-<?= ($dataset['status']==='processed'?'success':'info') ?>"><?= __('status_' . strtolower($dataset['status'])) ?></span> • <?= __('created_at') ?>: <?= $dataset['created_at'] ?></div>
        </div>
        <div class="d-flex gap-2">
            <a href="enter-data/<?= $dataset_id ?>" class="btn btn-primary">
                <i class="fas fa-edit"></i> <?= __('edit_config') ?>
            </a>
            <a href="history" class="btn btn-outline-secondary">
                <i class="fas fa-chevron-left"></i> <?= __('back_to_history') ?>
            </a>
        </div>
    </div>

    <div class="row">
        <!-- Sidebar: Summary Stats -->
        <div class="col-lg-4">
            <!-- Current Optimization Result -->
            <div class="card shadow-sm mb-4 border-0">
                <div class="card-header bg-white py-3">
                    <h5 class="mb-0"><i class="fas fa-poll text-warning me-2"></i><?= __('current_result') ?></h5>
                </div>
                <div class="card-body">
                    <?php if (!$opt): ?>
                        <div class="text-center py-3">
                            <p class="text-muted small"><?= __('no_results_yet') ?></p>
                            <a href="enter-data/<?= $dataset_id ?>" class="btn btn-sm btn-primary"><?= __('go_to_editor') ?></a>
                        </div>
                    <?php else: ?>
                        <?php 
                            $summary = json_decode($opt['summary_json'] ?? '{}', true);
                            $statusColor = ($opt['status'] === 'finished' ? 'success' : ($opt['status'] === 'failed' ? 'danger' : 'warning'));
                        ?>
                        <div class="mb-3 text-center">
                            <span class="badge bg-<?= $statusColor ?> fs-6"><?= __('status_' . strtolower($opt['status'])) ?></span>
                            <div class="text-muted small mt-1"><?= __('created_at') ?>: <?= (new DateTime($opt['created_at']))->format('M j, H:i') ?></div>
                        </div>

                        <?php if($opt['status'] === 'finished'): ?>
                            <div class="list-group list-group-flush mb-3 small">
                                <?php if(isset($summary['avg_nivel_tu'])): ?>
                                    <div class="list-group-item d-flex justify-content-between px-0">
                                        <span><?= __('avg_level') ?>:</span> <span class="fw-bold"><?= number_format($summary['avg_nivel_tu'], 2) ?> dBuV</span>
                                    </div>
                                <?php endif; ?>
                                <?php if(isset($summary['total_tus'])): ?>
                                    <div class="list-group-item d-flex justify-content-between px-0">
                                        <span><?= __('total_tus') ?>:</span> <span class="fw-bold"><?= $summary['total_tus'] ?></span>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <a href="view-result/<?= $opt['opt_id'] ?>" class="btn btn-outline-primary w-100">
                                <i class="fas fa-external-link-alt me-1"></i> <?= __('view_result') ?>
                            </a>
                        <?php elseif($opt['status'] === 'failed'): ?>
                            <div class="alert alert-danger small py-2"><?= htmlspecialchars($opt['error_message'] ?: __('unknown_error')) ?></div>
                            <p class="text-muted small text-center"><?= __('fix_config_hint') ?></p>
                        <?php else: ?>
                            <div class="text-center py-2">
                                <div class="spinner-border spinner-border-sm text-warning mb-2"></div>
                                <p class="small text-muted mb-0"><?= __('opt_running_hint') ?></p>
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>

            <div class="card shadow-sm mb-4 border-0">
                <div class="card-header bg-white py-3">
                    <h5 class="mb-0"><i class="fas fa-info-circle text-info me-2"></i><?= __('dataset_summary') ?></h5>
                </div>
                <div class="card-body">
                    <ul class="list-group list-group-flush">
                        <li class="list-group-item d-flex justify-content-between px-0">
                            <span><?= __('max_floor') ?></span>
                            <span class="fw-bold"><?= $canonical['Piso_Maximo'] ?? '—' ?></span>
                        </li>
                        <li class="list-group-item d-flex justify-content-between px-0">
                            <span><?= __('apts_per_floor') ?></span>
                            <span class="fw-bold"><?= $canonical['apartamentos_por_piso'] ?? '—' ?></span>
                        </li>
                        <li class="list-group-item d-flex justify-content-between px-0">
                            <span><?= __('tech_input_power') ?></span>
                            <span class="fw-bold"><?= $canonical['potencia_entrada'] ?? '—' ?> dBuV</span>
                        </li>
                    </ul>
                </div>
            </div>
        </div>

        <!-- Main Content: Configuration Viewer -->
        <div class="col-lg-8">
            <div class="card shadow-sm border-0 mb-4">
                <div class="card-header bg-white py-3">
                    <h5 class="mb-0"><i class="fas fa-cogs text-primary me-2"></i><?= __('config_snapshot') ?></h5>
                </div>
                <div class="card-body">
                    
                    <!-- General Parameters Grid -->
                    <h6 class="border-bottom pb-2 mb-3 text-uppercase small fw-bold text-muted"><?= __('tech_params') ?></h6>
                        <div class="row row-cols-2 row-cols-md-3 g-3 mb-4">
                            <?php 
                                $skip = ['largo_cable_derivador_repartidor', 'tus_requeridos_por_apartamento', 'largo_cable_tu', 'derivadores_data', 'repartidores_data', 'contract_version'];
                                foreach ($canonical as $key => $value): 
                                    if (in_array($key, $skip) || is_array($value)) continue;
                            ?>
                                <div class="col">
                                    <div class="p-2 border rounded bg-light h-100">
                                        <div class="small text-muted mb-1"><?= __('param_' . $key) ?></div>
                                        <div class="fw-bold"><?= $value ?></div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>

                        <!-- Complex Data Previews -->
                        <h6 class="border-bottom pb-2 mb-3 text-uppercase small fw-bold text-muted"><?= __('catalogs_title') ?></h6>
                        <div class="row g-3 mb-4">
                            <div class="col-md-6">
                                <div class="card h-100 border-light">
                                    <div class="card-header small py-1"><?= __('derivadores') ?></div>
                                    <div class="card-body p-0">
                                        <table class="table table-sm table-hover mb-0 x-small">
                                            <thead><tr><th><?= __('col_model') ?></th><th>Derv.</th><th><?= __('col_pass') ?></th></tr></thead>
                                            <tbody>
                                                <?php foreach(($canonical['derivadores_data'] ?? []) as $m => $s): ?>
                                                <tr><td><?= $m ?></td><td><?= $s['derivacion'] ?></td><td><?= $s['paso'] ?></td></tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="card h-100 border-light">
                                    <div class="card-header small py-1"><?= __('repartidores') ?></div>
                                    <div class="card-body p-0">
                                        <table class="table table-sm table-hover mb-0 x-small">
                                            <thead><tr><th><?= __('col_model') ?></th><th><?= __('loss') ?></th><th><?= __('col_outs') ?></th></tr></thead>
                                            <tbody>
                                                <?php foreach(($canonical['repartidores_data'] ?? []) as $m => $s): ?>
                                                <tr><td><?= $m ?></td><td><?= $s['perdida_insercion'] ?></td><td><?= $s['salidas'] ?></td></tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <h6 class="border-bottom pb-2 mb-3 text-uppercase small fw-bold text-muted"><?= __('building_mapping') ?></h6>
                        <div class="row g-2">
                             <div class="col-md-4">
                                <div class="alert alert-secondary py-2 px-3 mb-0">
                                    <div class="small fw-bold"><?= __('applicable_apts') ?></div>
                                    <div><?= count($canonical['tus_requeridos_por_apartamento'] ?? []) ?> <?= __('units_configured') ?></div>
                                </div>
                             </div>
                             <div class="col-md-4">
                                <div class="alert alert-secondary py-2 px-3 mb-0">
                                    <div class="small fw-bold"><?= __('tu_lengths_title') ?></div>
                                    <div><?= count($canonical['largo_cable_tu'] ?? []) ?> <?= __('tomas_defined') ?></div>
                                </div>
                             </div>
                        </div>

                    </div>
                </div>
            </div>
        </div>
    </div>
<style>
.x-small { font-size: 0.75rem; }
.truncate { white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.italic { font-style: italic; }
</style>

<?php include __DIR__ . "/templates/footer.php"; ?>
