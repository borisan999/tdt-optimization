<?php

require_once __DIR__ . '/../app/auth/require_login.php';
include __DIR__ . '/templates/header.php';
include __DIR__ . '/templates/navbar.php';

?>
<main class="container mt-4">

    <h2 class="mb-4">Enter Data</h2>

    <!-- TAB BUTTONS -->
    <div class="tab-bar">
        <button class="btn btn-primary tab-btn active" onclick="showTab('upload_tab');">Upload Excel</button>
        <button class="btn btn-secondary tab-btn" onclick="showTab('manual_tab')">Manual Entry</button>
        <button class="btn btn-info tab-btn" onclick="showTab('history_tab')">Load From History</button>
    </div>

    <!-- SECTION: UPLOAD EXCEL -->
    <div id="upload_tab" class="section-box mt-3">
        <h4>Upload Excel File</h4>
        <form id="excelUploadForm" method="POST" enctype="multipart/form-data">
            <div class="mb-3 mt-3">
                <label class="form-label">Select .xlsx file</label>
                <input type="file" name="excel_file" class="form-control" accept=".xlsx" required>
            </div>
            <button type="submit" class="btn btn-success">Upload & Process</button>
        </form>
    </div>

    <!-- SECTION: MANUAL ENTRY -->
    <div id="manual_tab" class="section-box mt-3 hidden">
        <form id="manualInputForm" method="POST">
            <h3 class="mb-3">Manual Data Entry</h3>
            <h4 class="text-muted mb-3">General Parameters</h4>

            <div class="row" id="generalParamsForm">
                <!-- General parameters will be dynamically loaded here by JS -->
                <!-- Example:
                <div class="col-md-3 mb-3">
                    <label class="form-label">Piso Máximo</label>
                    <input type="number" step="any" class="form-control validate-field" name="param_Piso_Maximo" value="">
                </div>
                -->
            </div>
           <h4>Repetir configuración de pisos</h4>

            <div class="row g-2 align-items-end mb-3">
                <div class="col-md-3">
                    <label class="form-label">Piso Fuente (Template)</label>
                    <input type="number" name="sourceFloorId" class="form-control" min="1">
                </div>

                <div class="col-md-3">
                    <label class="form-label">Piso Inicio (Destino)</label>
                    <input type="number" name="targetStartFloor" class="form-control" min="1">
                </div>

                <div class="col-md-3">
                    <label class="form-label">Piso Fin (Destino)</label>
                    <input type="number" name="targetEndFloor" class="form-control" min="1">
                </div>

                <div class="col-md-3">
                    <button
                        type="button"
                        id="applyRepetitionBtn"
                        class="btn btn-outline-primary">
                        Aplicar Configuración
                    </button>
                </div>
            </div>

            <h4>Apartments</h4>
            <button type="button" class="btn btn-primary" onclick="addApartmentRow()">+ Add Apartment</button>

            <table class="table table-bordered mt-2" id="apartmentsTable">
                <thead>
                    <tr>
                        <th>Piso</th>
                        <th>Apartamento</th>
                        <th>TUs Requeridos</th>
                        <th>Cable Derivador (m)</th>
                        <th>Cable Repartidor (m)</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody id="apartmentsBody"></tbody>
            </table>


            <h4>TU Cables</h4>
            <button type="button" class="btn btn-primary" onclick="addTuRow()">+ Add TU</button>

            <table class="table table-bordered mt-2" id="tuTable">
                <thead>
                    <tr>
                        <th>Piso</th>
                        <th>Apartamento</th>
                        <th>TU Index</th>
                        <th>Longitud Cable TU (m)</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody id="tuBody"></tbody>
            </table>

            <!-- Hidden input for the canonical JSON payload -->
            <input type="hidden" id="canonical_payload" name="canonical_payload">
            <input type="hidden" id="current_dataset_id" name="dataset_id">

            <button id="saveBtn" class="btn btn-success" type="submit">Save Dataset</button>
            
            <button id="runOptimizationBtn" class="btn btn-warning mt-3 hidden">
                ▶ Run Optimization
            </button>
        </form>
    </div>
    
    <!-- SECTION: LOAD FROM HISTORY -->
    <div id="history_tab" class="section-box mt-3 hidden">
        <h4>Load Previous Configuration</h4>
        <form id="historyLoadForm">
            <div class="mb-3">
                <label class="form-label">Select a previous optimization</label>
                <select id="historySelect" class="form-select" required>
                    <option value="">-- Select --</option>
                    <!-- History items will be loaded here by JS -->
                </select>
            </div>
            <button type="submit" class="btn btn-info">Load Configuration</button>
        </form>
    </div>

</main>

<script src="assets/js/enter-data.js"></script>
<?php include __DIR__ . '/templates/footer.php'; ?>
