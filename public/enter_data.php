<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL); 
//require_once "../app/controllers/DatasetController.php";

$loaded_params = $_SESSION['loaded_params'] ?? [];
$loaded_rows = $_SESSION['loaded_dataset'] ?? null;
$loaded_id   = $_SESSION['loaded_dataset_id'] ?? null;
?>
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
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Enter Data</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background: #f7f7f7; }
        .tab-btn { margin-right: 10px; }
        .section-box {
            background: white;
            border-radius: 8px;
            padding: 25px;
            margin-top: 20px;
            box-shadow: 0 2px 6px rgba(0,0,0,0.07);
        }
        .hidden { display: none; }
    </style>
</head>
<body>
<?php if (isset($_GET['loaded'])) ?>
<?php if (!empty($_SESSION['upload_warnings'])): ?>
<div style="
    background:#fff3cd;
    border:1px solid #ffeeba;
    padding:15px;
    margin-bottom:20px;
    border-radius:6px;
    color:#856404;
">
    <h4>⚠ Excel Upload Warnings</h4>
    <ul style="margin-left:20px;">
        <?php foreach ($_SESSION['upload_warnings'] as $w): ?>
            <li><?= htmlspecialchars($w) ?></li>
        <?php endforeach; ?>
    </ul>
</div>
<?php 
// clear warnings so they only show once
unset($_SESSION['upload_warnings']);
endif; ?>
<div class="container mt-4">

    <h2 class="mb-4">Enter Data</h2>

    <!-- TAB BUTTONS -->
    <div class="d-flex">
        <button class="btn btn-primary tab-btn" onclick="showTab('upload_tab')">Upload Excel</button>
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
            <h3>Manual Data Entry</h3>
            <h3>General Parameters</h3>

            <div class="row">
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

                    // Loaded from session if dataset was loaded or Excel uploaded
                ?>
                <?php foreach ($paramLabels as $name => $label): ?>
                    <div class="col-md-3 mb-3">
                        <label class="form-label"><?= $label ?></label>
                        <input type="number"
                            step="any"
                            class="form-control"
                            name="param_<?= $name ?>"
                            value="<?= $loaded_params[$name] ?? '' ?>">
                    </div>
                    
                <?php endforeach; ?>
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

            <button id="saveBtn" class="btn btn-success" onclick="submitManualForm()">Save Dataset</button>
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

</div>

<script>
    // Same rules as database
    const validationRules = {
        piso:          { min: 0, max: 200 },
        apartamento:   { min: 0 },
        tus_requeridos:{ min: 0, max: 20 },
        largo_cable_derivador: { min: 0, max: 200 },
        largo_cable_repartidor: { min: 0, max: 200 },
        largo_cable_tu: { min: 0, max: 200 },
    };

    function validateField(input) {
        const field = input.dataset.field;
        const value = parseFloat(input.value);

        if (!validationRules[field]) {
            input.classList.remove("is-invalid");
            return true;
        }

        const rules = validationRules[field];

        if (rules.min !== undefined && value < rules.min) {
            input.classList.add("is-invalid");
            return false;
        }

        if (rules.max !== undefined && value > rules.max) {
            input.classList.add("is-invalid");
            return false;
        }

        input.classList.remove("is-invalid");
        return true;
    }

    function checkFormValidity() {
        const inputs = document.querySelectorAll(".validate-field");
        let valid = true;

        inputs.forEach(inp => {
            if (!validateField(inp)) valid = false;
        });

        document.getElementById("saveBtn").disabled = !valid;
    }

    // Trigger live validation
    document.addEventListener("input", function(e) {
        if (e.target.classList.contains("validate-field")) {
            validateField(e.target);
            checkFormValidity();
        }
    });

    document.addEventListener("DOMContentLoaded", checkFormValidity);


    function showTab(tabId) {
        const tabs = ["upload_tab", "manual_tab", "history_tab"];

        tabs.forEach(id => {
            const el = document.getElementById(id);
            if (el) el.classList.add("hidden");
        });

        const showEl = document.getElementById(tabId);
        if (showEl) showEl.classList.remove("hidden");
    }
    /**
     * -------------------------------
     * Dynamic Apartments Table
     * -------------------------------
     */
    function addApartmentRow() {
        const tbody = document.getElementById("apartmentsBody");

        const row = document.createElement("tr");
        row.innerHTML = `
            <td><input type="number" name="piso[]" class="form-control" required></td>
            <td><input type="number" name="apartamento[]" class="form-control" required></td>
            <td><input type="number" name="tus_requeridos[]" class="form-control" required></td>
            <td><input type="number" name="cable_derivador[]" class="form-control" required></td>
            <td><input type="number" name="cable_repartidor[]" class="form-control" required></td>
            <td><button type="button" class="btn btn-danger btn-sm" onclick="this.parentElement.parentElement.remove()">X</button></td>
        `;
        tbody.appendChild(row);
    }

    /**
     * -------------------------------
     * Dynamic TU Table
     * -------------------------------
     */
    function addTuRow() {
        const tbody = document.getElementById("tuBody");

        const row = document.createElement("tr");
        row.innerHTML = `
            <td><input type="number" name="tu_piso[]" class="form-control" required></td>
            <td><input type="number" name="tu_apartamento[]" class="form-control" required></td>
            <td><input type="number" name="tu_index[]" class="form-control" required></td>
            <td><input type="number" name="largo_tu[]" class="form-control" required></td>
            <td><button type="button" class="btn btn-danger btn-sm" onclick="this.parentElement.parentElement.remove()">X</button></td>
        `;
        tbody.appendChild(row);
    }

    /**
     * -------------------------------
     * Final Submit Function
     * -------------------------------
     */
    function submitManualForm() {
        document.getElementById("manualInputForm").submit();
    }

    document.addEventListener("DOMContentLoaded", function () {

        <?php if ($loaded_rows): ?>

            const aptBody = document.getElementById("apartmentsBody");
            const tuBody = document.getElementById("tuBody");

            let records = {};

            // Group by record_index
            <?php foreach ($loaded_rows as $r): ?>
                if (!records[<?= $r['record_index'] ?>]) {
                    records[<?= $r['record_index'] ?>] = {};
                }
                records[<?= $r['record_index'] ?>]["<?= $r['field_name'] ?>"] = "<?= $r['field_value'] ?>";
            <?php endforeach; ?>

            // Now generate table rows
            for (const index in records) {
                const row = records[index];

                if (row.tu_index !== undefined) {
                    // TU-level row
                    let tr = document.createElement("tr");
                    tr.innerHTML = `
                        
                        <td><input type="number" name="tu_piso[]" class="form-control validate-field" data-field="piso" value="${row.piso}"></td>
                        <td><input type="number" name="tu_apartamento[]" class="form-control validate-field" data-field="apartamento" value="${row.apartamento}"></td>
                        <td><input type="number" name="tu_index[]" class="form-control validate-field" data-field="tu_index" value="${row.tu_index}"></td>
                        <td><input type="number" name="largo_tu[]" class="form-control validate-field" data-field="largo_cable_tu" value="${row.largo_cable_tu}"></td>
                        <td><button type="button" class="btn btn-danger btn-sm" onclick="this.parentElement.parentElement.remove()">X</button></td>
                    `;
                    tuBody.appendChild(tr);
                } else {
                    // Apartment-level row
                    let tr = document.createElement("tr");
                    tr.innerHTML = `
                        <td><input type="number" name="piso[]" class="form-control validate-field" data-field="piso" required value="${row.piso}"></td>
                        <td><input type="number" name="apartamento[]" class="form-control validate-field" data-field="apartamento" required value="${row.apartamento}"></td>
                        <td><input type="number" name="tus_requeridos[]" class="form-control validate-field" data-field="tus_requeridos" required value="${row.tus_requeridos}"></td>
                        <td><input type="number" name="cable_derivador[]" class="form-control validate-field" data-field="largo_cable_derivador" required value="${row.largo_cable_derivador}"></td>
                        <td><input type="number" name="cable_repartidor[]" class="form-control validate-field" data-field="largo_cable_repartidor" required value="${row.largo_cable_repartidor}"></td>
                        <td><button type="button" class="btn btn-danger btn-sm" onclick="this.parentElement.parentElement.remove()">X</button></td>
                    `;
                    aptBody.appendChild(tr);
                }
            }

            // Automatically switch to manual tab
            showTab('manual_tab');

        <?php endif; ?>

    });
</script>

</body>
</html>

