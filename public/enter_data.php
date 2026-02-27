<?php

require_once __DIR__ . '/../app/auth/require_login.php';
include __DIR__ . '/templates/header.php';
include __DIR__ . '/templates/navbar.php';

?>
<main class="container mt-4 mb-5">

    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2><i class="fas fa-edit text-primary"></i> <?= __('data_config_title') ?></h2>
        <div class="d-flex gap-2 align-items-center">
            <button type="button" class="btn btn-outline-info btn-sm" data-bs-toggle="modal" data-bs-target="#helpModal">
                <i class="fas fa-question-circle me-1"></i> <?= __('help_guide') ?>
            </button>
            <div id="status_badge" class="badge bg-secondary"><?= __('mode_new') ?></div>
        </div>
    </div>

    <!-- TAB NAVIGATION -->
    <ul class="nav nav-tabs mb-4" id="dataTabs" role="tablist">
        <li class="nav-item">
            <button class="nav-link active" id="upload-tab-btn" onclick="showTab('upload_tab');">
                <i class="fas fa-file-upload"></i> <?= __('tab_upload') ?>
            </button>
        </li>
        <li class="nav-item">
            <button class="nav-link" id="manual-tab-btn" onclick="showTab('manual_tab')">
                <i class="fas fa-keyboard"></i> <?= __('tab_manual') ?>
            </button>
        </li>
        <li class="nav-item">
            <button class="nav-link" id="history-tab-btn" onclick="showTab('history_tab')">
                <i class="fas fa-history"></i> <?= __('tab_history') ?>
            </button>
        </li>
    </ul>

    <!-- SECTION: UPLOAD EXCEL -->
    <div id="upload_tab" class="tab-pane fade show active section-box">
        <div class="card shadow-sm">
            <div class="card-body p-4">
                <h4 class="card-title mb-3"><?= __('upload_title') ?></h4>
                <p class="text-muted"><?= __('upload_desc') ?></p>
                <form id="excelUploadForm" method="POST" enctype="multipart/form-data">
                    <div class="input-group mb-3">
                        <input type="file" name="excel_file" class="form-control" accept=".xlsx" required>
                        <button type="submit" class="btn btn-success">
                            <i class="fas fa-upload"></i> <?= __('upload_btn') ?>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- SECTION: MANUAL ENTRY -->
    <div id="manual_tab" class="tab-pane fade hidden section-box">
        <form id="manualInputForm" method="POST">
            
            <!-- Dataset Name -->
            <div class="card shadow-sm mb-4 border-primary">
                <div class="card-body py-3">
                    <div class="row align-items-center">
                        <div class="col-md-3">
                            <label class="form-label fw-bold mb-0 text-primary"><i class="fas fa-tag me-2"></i><?= __('dataset_name') ?></label>
                        </div>
                        <div class="col-md-9">
                            <input type="text" id="dataset_name" name="dataset_name" class="form-control" placeholder="<?= __('dataset_placeholder') ?>" required title="<?= __('dataset_name_tooltip') ?>">
                        </div>
                    </div>
                </div>
            </div>

            <!-- General Parameters Groups -->
            <div id="generalParamsContainer">
                <!-- Parameters will be injected here into categorized cards -->
            </div>

            <!-- Complex Data (Catalogs) - Collapsible -->
            <div class="card shadow-sm mb-4 border-info">
                <div class="card-header bg-info text-white d-flex justify-content-between align-items-center" style="cursor: pointer;" data-bs-toggle="collapse" data-bs-target="#catalogCollapse">
                    <h5 class="mb-0"><i class="fas fa-boxes"></i> <?= __('catalogs_title') ?></h5>
                    <i class="fas fa-chevron-down"></i>
                </div>
                <div id="catalogCollapse" class="collapse">
                    <div class="card-body" id="complexParamsForm">
                        <!-- Textareas will be injected here -->
                    </div>
                </div>
            </div>

            <!-- Floor Repetition Utility -->
            <div class="card shadow-sm mb-4 border-primary">
                <div class="card-header bg-light">
                    <h5 class="mb-0"><i class="fas fa-copy"></i> <?= __('repetition_title') ?></h5>
                </div>
                <div class="card-body">
                    <p class="small text-muted"><?= __('repetition_desc') ?></p>
                    <div class="row g-3 align-items-end">
                        <div class="col-md-3">
                            <label class="form-label small fw-bold"><?= __('source_floor') ?></label>
                            <input type="number" name="sourceFloorId" class="form-control form-control-sm" placeholder="e.g. 1" title="<?= __('source_floor_tooltip') ?>">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label small fw-bold"><?= __('target_start') ?></label>
                            <input type="number" name="targetStartFloor" class="form-control form-control-sm" placeholder="e.g. 2" title="<?= __('target_start_floor_tooltip') ?>">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label small fw-bold"><?= __('target_end') ?></label>
                            <input type="number" name="targetEndFloor" class="form-control form-control-sm" placeholder="e.g. 10" title="<?= __('target_end_floor_tooltip') ?>">
                        </div>
                        <div class="col-md-3">
                            <button type="button" id="applyRepetitionBtn" class="btn btn-primary btn-sm w-100">
                                <i class="fas fa-magic"></i> <?= __('apply_range_btn') ?>
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Topology Tables -->
            <div class="row">
                <div class="col-xl-6">
                    <div class="card shadow-sm mb-4">
                        <div class="card-header d-flex justify-content-between align-items-center bg-white">
                            <h5 class="mb-0"><?= __('apt_config_title') ?></h5>
                            <button type="button" class="btn btn-outline-primary btn-sm" onclick="addApartmentRow()">
                                <i class="fas fa-plus"></i> <?= __('add_row') ?>
                            </button>
                        </div>
                        <div class="card-body p-0">
                            <div class="table-responsive" style="max-height: 500px;">
                                <table class="table table-hover table-sm mb-0" id="apartmentsTable">
                                    <thead class="table-light sticky-top">
                                        <tr>
                                            <th><?= __('col_piso') ?></th>
                                            <th><?= __('col_apto') ?></th>
                                            <th><?= __('col_tus') ?></th>
                                            <th><?= __('col_deriv_rep') ?></th>
                                            <th class="text-center"><?= __('col_action') ?></th>
                                        </tr>
                                    </thead>
                                    <tbody id="apartmentsBody"></tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-xl-6">
                    <div class="card shadow-sm mb-4">
                        <div class="card-header d-flex justify-content-between align-items-center bg-white">
                            <h5 class="mb-0"><?= __('tu_lengths_title') ?></h5>
                            <button type="button" class="btn btn-outline-primary btn-sm" onclick="addTuRow()">
                                <i class="fas fa-plus"></i> <?= __('add_row') ?>
                            </button>
                        </div>
                        <div class="card-body p-0">
                            <div class="table-responsive" style="max-height: 500px;">
                                <table class="table table-hover table-sm mb-0" id="tuTable">
                                    <thead class="table-light sticky-top">
                                        <tr>
                                            <th><?= __('col_piso') ?></th>
                                            <th><?= __('col_apto') ?></th>
                                            <th><?= __('col_tu_idx') ?></th>
                                            <th><?= __('col_length') ?></th>
                                            <th class="text-center"><?= __('col_action') ?></th>
                                        </tr>
                                    </thead>
                                    <tbody id="tuBody"></tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Global Actions -->
            <div class="card shadow-sm sticky-bottom bg-white border-top border-primary mt-4">
                <div class="card-body d-flex justify-content-end gap-3 p-3">
                    <input type="hidden" id="current_dataset_id" name="dataset_id">
                    
                    <button id="saveBtn" class="btn btn-primary px-4" type="submit">
                        <i class="fas fa-save"></i> <?= __('save_config_btn') ?>
                    </button>
                    
                    <button id="runOptimizationBtn" class="btn btn-warning px-4 hidden">
                        <i class="fas fa-play"></i> <?= __('run_opt_btn') ?>
                    </button>
                </div>
            </div>
        </form>
    </div>
    
    <!-- SECTION: LOAD FROM HISTORY -->
    <div id="history_tab" class="tab-pane fade hidden section-box">
        <div class="card shadow-sm">
            <div class="card-body p-4">
                <h4 class="card-title mb-3"><?= __('load_history_title') ?></h4>
                <form id="historyLoadForm">
                    <div class="mb-3">
                        <label class="form-label"><?= __('select_prev_config') ?></label>
                        <select id="historySelect" class="form-select" required>
                            <option value=""><?= __('choose_dataset') ?></option>
                        </select>
                    </div>
                    <button type="submit" class="btn btn-info text-white">
                        <i class="fas fa-folder-open"></i> <?= __('load_config_btn') ?>
                    </button>
                </form>
            </div>
        </div>
    </div>

</main>

<!-- Help Modal -->
<div class="modal fade" id="helpModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-info text-dark">
                <h5 class="modal-title fw-bold"><i class="fas fa-lightbulb me-2"></i><?= __('data_guide_title') ?></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <h6><strong><?= __('data_concept_1_title') ?></strong></h6>
                <p><?= __('data_concept_1_desc') ?></p>
                
                <hr>
                
                <h6><strong><?= __('data_concept_2_title') ?></strong></h6>
                <p><?= __('data_concept_2_desc') ?></p>
                
                <hr>
                
                <h6><strong><?= __('data_concept_3_title') ?></strong></h6>
                <p><?= __('data_concept_3_desc') ?></p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?= __('got_it') ?></button>
            </div>
        </div>
    </div>
</div>

<script>
    // Pass translations to JS
    window.LANG = <?= json_encode(\app\helpers\Translation::getDictionary(), JSON_UNESCAPED_UNICODE) ?>;
    
    // Helper function for JS
    function __(key, placeholders = {}) {
        let text = window.LANG[key] || key;
        for (const [k, v] of Object.entries(placeholders)) {
            text = text.replace(`{${k}}`, v);
        }
        return text;
    }
</script>
<script src="assets/js/enter-data.js"></script>
<?php include __DIR__ . '/templates/footer.php'; ?>
