<?php

require_once __DIR__ . '/../app/auth/require_login.php';
//echo password_hash('your_password', PASSWORD_DEFAULT);
include __DIR__ . '/templates/header.php';
include __DIR__ . '/templates/navbar.php';
/*ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);*/
?>
<?php

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
$loaded_id     = $_SESSION['loaded_dataset_id'] ?? null;

$formData = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['apply_repetition'])) {
    // Inputs
    $source = (int)$_POST['sourceFloorId'];
    $start  = (int)$_POST['targetStartFloor'];
    $end    = (int)$_POST['targetEndFloor'];

    // Validation for repetition fields
    if (empty($_POST['sourceFloorId']) || empty($_POST['targetStartFloor']) || empty($_POST['targetEndFloor'])) {
        $_SESSION['manual_errors'][] = 'Debe completar todos los campos para repetir la configuración de pisos.';
        header("Location: enter_data.php");
        exit;
    }

    // Data from session
    $dataset =& $_SESSION['loaded_canonical_dataset'];

    // Validate new canonical structure
    if (
        !isset($dataset['apartments']) || !is_array($dataset['apartments']) ||
        !isset($dataset['tus']) || !is_array($dataset['tus'])
    ) {
        // Updated error message for the new structure
        $_SESSION['manual_errors'][] = 'Estructura del dataset canónico inválida para repetición. Faltan arrays de apartamentos o TUs.';
        header("Location: enter_data.php");
        exit;
    }

    // Step 1 — Extract source floor data
    $sourceApartments = array_filter(
        $dataset['apartments'],
        fn($a) => (int)$a['piso'] === $source
    );

    $sourceTus = array_filter(
        $dataset['tus'],
        fn($t) => (int)$t['piso'] === $source
    );

    // Step 2 — Validate source data
    if (empty($sourceApartments)) {
        $_SESSION['manual_errors'][] = "El piso fuente no tiene apartamentos para repetir.";
        header("Location: enter_data.php");
        exit;
    }

    // B. Remove destination floors (overwrite rule) - from both apartments and TUs
    $dataset['apartments'] = array_values(array_filter(
        $dataset['apartments'],
        fn($ap) => (int)$ap['piso'] < $start || (int)$ap['piso'] > $end
    ));

    $dataset['tus'] = array_values(array_filter(
        $dataset['tus'],
        fn($tu) => (int)$tu['piso'] < $start || (int)$tu['piso'] > $end
    ));

    // C. Duplicate for each destination floor
    for ($floor = $start; $floor <= $end; $floor++) {
        // Duplicate apartments
        foreach ($sourceApartments as $ap) {
            $clone = $ap;
            $clone['piso'] = $floor;
            $dataset['apartments'][] = $clone;
        }

        // Duplicate TUs
        foreach ($sourceTus as $tu) {
            $clone = $tu;
            $clone['piso'] = $floor;
            $dataset['tus'][] = $clone;
        }
    }

    // D. Update Piso Máximo
    $dataset['inputs']['Piso_Maximo'] =
        max((int)($dataset['inputs']['Piso_Maximo'] ?? 0), $end);

    $_SESSION['loaded_params']['Piso_Maximo'] =
        $dataset['inputs']['Piso_Maximo'];

    // Clear repetition fields from POST to avoid re-population on reload
    unset($_POST['sourceFloorId']);
    unset($_POST['targetStartFloor']);
    unset($_POST['targetEndFloor']);

    // E. Redirect (PRG pattern)
    header("Location: enter_data.php?loaded=1");
    exit;
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
    window.LOADED_DATA = null;
    console.log('DEBUG: window.LOADED_DATA on page load:', window.LOADED_DATA); // NEW DEBUG LOG
    //window.CANONICAL_DATA = <?= json_encode($_SESSION['loaded_canonical_dataset'] ?? null, JSON_HEX_TAG) ?>;
    window.CANONICAL_DATA = <?= json_encode($_SESSION['loaded_canonical_dataset'] ?? null) ?>;
    console.log('DEBUG: window.CANONICAL_DATA:', window.CANONICAL_DATA);


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
                        type="submit"
                        name="apply_repetition"
                        formaction="enter_data.php"
                        formmethod="POST"
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

            <button id="saveBtn" class="btn btn-success" type="submit" name="action" value="save">Save Dataset</button>
            
            <?php if (!empty($_GET['dataset_id'])): ?>
                <button type="submit" 
                        class="btn btn-warning mt-3" 
                        formaction="../app/controllers/DatasetController.php?action=run_python&dataset_id=<?= htmlspecialchars($_GET['dataset_id']) ?>">
                    ▶ Run Optimization
                </button>
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
