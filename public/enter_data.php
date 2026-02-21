<?php

require_once __DIR__ . '/../app/auth/require_login.php';
include __DIR__ . '/templates/header.php';
include __DIR__ . '/templates/navbar.php';

?>
<main class="container mt-4 mb-5">

    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2><i class="fas fa-edit text-primary"></i> Data Configuration</h2>
        <div id="status_badge" class="badge bg-secondary">Mode: New Dataset</div>
    </div>

    <!-- TAB NAVIGATION -->
    <ul class="nav nav-tabs mb-4" id="dataTabs" role="tablist">
        <li class="nav-item">
            <button class="nav-link active" id="upload-tab-btn" onclick="showTab('upload_tab');">
                <i class="fas fa-file-upload"></i> Upload Excel
            </button>
        </li>
        <li class="nav-item">
            <button class="nav-link" id="manual-tab-btn" onclick="showTab('manual_tab')">
                <i class="fas fa-keyboard"></i> Manual Entry
            </button>
        </li>
        <li class="nav-item">
            <button class="nav-link" id="history-tab-btn" onclick="showTab('history_tab')">
                <i class="fas fa-history"></i> Load History
            </button>
        </li>
    </ul>

    <!-- SECTION: UPLOAD EXCEL -->
    <div id="upload_tab" class="tab-pane fade show active section-box">
        <div class="card shadow-sm">
            <div class="card-body p-4">
                <h4 class="card-title mb-3">Upload Building Specification</h4>
                <p class="text-muted">Upload an Excel file following the authoritative template (Piso/Apto/TU Index must be explicit).</p>
                <form id="excelUploadForm" method="POST" enctype="multipart/form-data">
                    <div class="input-group mb-3">
                        <input type="file" name="excel_file" class="form-control" accept=".xlsx" required>
                        <button type="submit" class="btn btn-success">
                            <i class="fas fa-upload"></i> Upload & Process
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
                            <label class="form-label fw-bold mb-0 text-primary"><i class="fas fa-tag me-2"></i>Dataset Name</label>
                        </div>
                        <div class="col-md-9">
                            <input type="text" id="dataset_name" name="dataset_name" class="form-control" placeholder="e.g. Edificio Los Pinos - Bloque A" required>
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
                    <h5 class="mb-0"><i class="fas fa-boxes"></i> Equipment Catalogs & Advanced Data</h5>
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
                    <h5 class="mb-0"><i class="fas fa-copy"></i> Smart Floor Repetition</h5>
                </div>
                <div class="card-body">
                    <p class="small text-muted">Use this to quickly populate multiple floors based on a template floor.</p>
                    <div class="row g-3 align-items-end">
                        <div class="col-md-3">
                            <label class="form-label small fw-bold">Source Floor</label>
                            <input type="number" name="sourceFloorId" class="form-control form-control-sm" placeholder="e.g. 1">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label small fw-bold">Target Start</label>
                            <input type="number" name="targetStartFloor" class="form-control form-control-sm" placeholder="e.g. 2">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label small fw-bold">Target End</label>
                            <input type="number" name="targetEndFloor" class="form-control form-control-sm" placeholder="e.g. 10">
                        </div>
                        <div class="col-md-3">
                            <button type="button" id="applyRepetitionBtn" class="btn btn-primary btn-sm w-100">
                                <i class="fas fa-magic"></i> Apply to Range
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
                            <h5 class="mb-0">Apartments Configuration</h5>
                            <button type="button" class="btn btn-outline-primary btn-sm" onclick="addApartmentRow()">
                                <i class="fas fa-plus"></i> Add Row
                            </button>
                        </div>
                        <div class="card-body p-0">
                            <div class="table-responsive" style="max-height: 500px;">
                                <table class="table table-hover table-sm mb-0" id="apartmentsTable">
                                    <thead class="table-light sticky-top">
                                        <tr>
                                            <th>Piso</th>
                                            <th>Apto</th>
                                            <th>TUs</th>
                                            <th>Deriv→Rep (m)</th>
                                            <th class="text-center">Action</th>
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
                            <h5 class="mb-0">TU Cable Lengths</h5>
                            <button type="button" class="btn btn-outline-primary btn-sm" onclick="addTuRow()">
                                <i class="fas fa-plus"></i> Add Row
                            </button>
                        </div>
                        <div class="card-body p-0">
                            <div class="table-responsive" style="max-height: 500px;">
                                <table class="table table-hover table-sm mb-0" id="tuTable">
                                    <thead class="table-light sticky-top">
                                        <tr>
                                            <th>Piso</th>
                                            <th>Apto</th>
                                            <th>TU Idx</th>
                                            <th>Length (m)</th>
                                            <th class="text-center">Action</th>
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
                        <i class="fas fa-save"></i> Save Configuration
                    </button>
                    
                    <button id="runOptimizationBtn" class="btn btn-warning px-4 hidden">
                        <i class="fas fa-play"></i> Run Optimization
                    </button>
                </div>
            </div>
        </form>
    </div>
    
    <!-- SECTION: LOAD FROM HISTORY -->
    <div id="history_tab" class="tab-pane fade hidden section-box">
        <div class="card shadow-sm">
            <div class="card-body p-4">
                <h4 class="card-title mb-3">Load From History</h4>
                <form id="historyLoadForm">
                    <div class="mb-3">
                        <label class="form-label">Select a previous configuration</label>
                        <select id="historySelect" class="form-select" required>
                            <option value="">-- Choose Dataset --</option>
                        </select>
                    </div>
                    <button type="submit" class="btn btn-info text-white">
                        <i class="fas fa-folder-open"></i> Load Configuration
                    </button>
                </form>
            </div>
        </div>
    </div>

</main>

<script src="assets/js/enter-data.js"></script>
<?php include __DIR__ . '/templates/footer.php'; ?>
