<?php

require_once __DIR__ . '/../app/auth/require_login.php';
//echo password_hash('your_password', PASSWORD_DEFAULT);
include __DIR__ . '/templates/header.php';
include __DIR__ . '/templates/navbar.php';
/*ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);*/

// List of parameters we support
$paramLabels = [
    "Piso_Maximo" => "Piso Máximo",
    "Apartamentos_Piso" => "Apartamentos por Piso",
    "Largo_Cable_Amplificador_Ultimo_Piso" => "Largo Cable Amplificador Último Piso (m)",
    "Potencia_Entrada_dBuV" => "Potencia Entrada (dBuV)",
    "Nivel_Minimo_dBuV" => "Nivel Mínimo (dBuV)",
    "Nivel_Maximo_dBuV" => "Nivel Máximo (dBuV)",
    "Potencia_Objetivo_TU_dBuV" => "Potencia Objetivo TU (dBuV)",
    "Largo_Feeder_Bloque_m" => "Largo Feeder Bloque (m)"
];

$loaded_params = $_SESSION['loaded_params'] ?? [];
$loaded_rows   = $_SESSION['loaded_dataset'] ?? null;
$loaded_id     = $_SESSION['loaded_dataset_id'] ?? null;

$formData = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    require_once __DIR__ . '/../app/controllers/DatasetController.php';

    // Persist changes
    manual_entry($_POST);

    // UI must reflect what the user just edited
    $formData = $_POST;

} elseif (!empty($loaded_params)) {

    // Initial load from Excel or previous dataset
    $formData = [
        'params' => $loaded_params,
        'rows'   => $loaded_rows
    ];
}
// Normalize flat POST params into structured params
if (!empty($formData)) {
    foreach ($paramLabels as $key => $_label) {
        if (isset($formData['param_' . $key])) {
            $formData['params'][$key] = $formData['param_' . $key];
        }
    }
}/* 
echo '<pre>';
echo "POST:\n";
var_dump($_POST);
echo "\nSESSION loaded_params:\n";
var_dump($_SESSION['loaded_params'] ?? null);
echo '</pre>';
exit; */

?>
<script>
    window.LOADED_ROWS = <?= json_encode($loaded_rows, JSON_HEX_TAG) ?>;
</script>
<?php if (!empty($_SESSION['manual_errors'])): ?>
<div class="alert alert-danger">
    <h4>❌ Manual Entry Errors</h4>
    <ul>
        <?php foreach ($_SESSION['manual_errors'] as $err): ?>
            <li><?= htmlspecialchars($err) ?></li>
        <?php endforeach; ?>
    </ul>
</div>
<?php unset($_SESSION['manual_errors']); endif; ?>

<?php if (!empty($_SESSION['manual_warnings'])): ?>
<div class="alert alert-warning">
    <h4>⚠ Manual Entry Warnings</h4>
    <ul>
        <?php foreach ($_SESSION['manual_warnings'] as $war): ?>
            <li><?= htmlspecialchars($war) ?></li>
        <?php endforeach; ?>
    </ul>
</div>
<?php unset($_SESSION['manual_warnings']); endif; ?>
<?php if (isset($_GET['loaded'])) ?>
<?php if (!empty($_SESSION['upload_warnings'])): ?>
<div class="alert alert-warning">
    <h4 class="alert-heading">⚠ Excel Upload Warnings</h4>
    <ul>
        <?php foreach ($_SESSION['upload_warnings'] as $w): ?>
            <li><?= htmlspecialchars($w) ?></li>
        <?php endforeach; ?>
    </ul>
</div>
<?php 
// clear warnings so they only show once
unset($_SESSION['upload_warnings']);
endif; ?>
<main class="container mt-4">

    <h2 class="mb-4">Enter Data</h2>

    <!-- TAB BUTTONS -->
    <div class="tab-bar">
        <button class="btn btn-primary tab-btn" onclick="showTab('upload_tab');">Upload Excel</button>
        <button class="btn btn-secondary tab-btn" onclick="showTab('manual_tab')">Manual Entry</button>
        <button class="btn btn-info tab-btn" onclick="showTab('history_tab')">Load From History</button>
    </div>

    <!-- SECTION: UPLOAD EXCEL -->
    <div id="upload_tab" class="section-box mt-3 hidden" >
        <h4>Upload Excel File</h4>
        <form action="../app/controllers/DatasetController.php?action=upload_excel" method="POST" enctype="multipart/form-data">
            <div class="mb-3 mt-3">
                <label class="form-label">Select .xlsx file</label>
                <input type="file" name="excel_file" class="form-control" accept=".xlsx" required>
            </div>
            <button type="submit" class="btn btn-success">Upload & Process</button>
        </form>
    </div>

    <!-- SECTION: MANUAL ENTRY -->
    <div id="manual_tab" class="section-box mt-3">
        <form id="manualInputForm" 
        method="POST" 
        action="../app/controllers/DatasetController.php?action=manual_entry">
        <!-- <form
            id="manualInputForm"
            method="POST"
            action="enter_data.php"> -->
            <h3 class="mb-3">Manual Data Entry</h3>
            <h4 class="text-muted mb-3">General Parameters</h4>

            <div class="row">
                <?php

                    // Loaded from session if dataset was loaded or Excel uploaded
                ?>
                <?php foreach ($paramLabels as $name => $label): ?>
                    <div class="col-md-3 mb-3">
                        <label class="form-label"><?= $label ?></label>
                        <input type="number"
                            step="any"
                            class="form-control"
                            name="param_<?= $name ?>"
                            value="<?= htmlspecialchars(
                                $formData['params'][$name]
                                ?? $loaded_params[$name]
                                ?? ''
                            ) ?>">
                    </div>
                    
                <?php endforeach; ?>
            </div>
            <h4>Repetir configuración de pisos</h4>

            <div class="row g-2 align-items-end mb-3">
                <div class="col-md-3">
                    <label class="form-label">Desde piso (X)</label>
                    <input type="number" id="repeatFromFloor" class="form-control" min="0">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Hasta piso (Y)</label>
                    <input type="number" id="repeatToFloor" class="form-control" min="0">
                </div>
                <div class="col-md-3">
                    <button type="button"
                            class="btn btn-outline-primary"
                            onclick="repeatFloorConfig()">
                        Repetir configuración
                    </button>
                </div>
                <div class="col-md-3">
                    <div id="repeatInfo" class="text-muted small"></div>
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

            <button id="saveBtn" class="btn btn-success" type="submit">Save Dataset</button>
            <?php if (!empty($_GET['dataset_id'])): ?>
                <a href="../app/controllers/DatasetController.php?action=run_python&dataset_id=<?= $_GET['dataset_id'] ?>"
                class="btn btn-warning mt-3">
                    ▶ Run Optimization
                </a>
            <?php endif; ?>
        </form>
    </div>
    
    <!-- SECTION: LOAD FROM HISTORY -->
    <div id="history_tab" class="section-box mt-3 hidden">
        <h4>Load Previous Dataset</h4>
        <form action="../app/controllers/DatasetController.php?action=history" method="POST">
            <div class="mb-3">
                <label class="form-label">Select a previous dataset</label>
                <select name="dataset_id" class="form-select" required>
                    <option value="">-- Select --</option>
                    <?php
                        require_once __DIR__ . "/../app/config/db.php";
                        require_once __DIR__ . "/../app/models/Dataset.php";

                        $datasetModel = new Dataset();
                        $list = $datasetModel->getHistory();

                        foreach ($list as $d) {
                            echo "<option value='{$d['dataset_id']}'>Dataset #{$d['dataset_id']} - {$d['created_at']}</option>";
                        }
                    ?>
                </select>
            </div>
            <button type="submit" class="btn btn-info">Load Dataset</button>
        </form>
    </div>

</main>

<script src="/tdt-optimization/public/assets/js/enter-data.js"></script>

<?php include __DIR__ . '/templates/footer.php'; ?>
