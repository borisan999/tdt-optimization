<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL); 
//require_once "../app/controllers/DatasetController.php";

$loaded_rows = $_SESSION['loaded_dataset'] ?? null;
$loaded_id   = $_SESSION['loaded_dataset_id'] ?? null;
?>

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

<div class="container mt-4">

    <h2 class="mb-4">Enter Data</h2>

    <!-- TAB BUTTONS -->
    <div class="d-flex">
        <button class="btn btn-primary tab-btn" onclick="showTab('upload_tab')">Upload Excel</button>
        <button class="btn btn-secondary tab-btn" onclick="showTab('manual_tab')">Manual Entry</button>
        <button class="btn btn-info tab-btn" onclick="showTab('history_tab')">Load From History</button>
    </div>

    <!-- SECTION: UPLOAD EXCEL -->
    <div id="upload_tab" class="section-box mt-3">
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

            <button class="btn btn-success" onclick="submitManualForm()">Save Dataset</button>
        </form>
    </div>
    
    <!-- SECTION: LOAD FROM HISTORY -->
    <div id="history_tab" class="section-box mt-3 hidden">
        <h4>Load Previous Dataset</h4>
       <!-- <form action="/tdt-optimization/app/controllers/DatasetController.php?action=history"
        method="POST">-->
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

            console.log("Loading dataset <?= $loaded_id ?>");

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
                        <td><input type="number" name="tu_piso[]" class="form-control" value="${row.piso}"></td>
                        <td><input type="number" name="tu_apartamento[]" class="form-control" value="${row.apartamento}"></td>
                        <td><input type="number" name="tu_index[]" class="form-control" value="${row.tu_index}"></td>
                        <td><input type="number" name="largo_tu[]" class="form-control" value="${row.largo_cable_tu}"></td>
                        <td><button type="button" class="btn btn-danger btn-sm" onclick="this.parentElement.parentElement.remove()">X</button></td>
                    `;
                    tuBody.appendChild(tr);
                } else {
                    // Apartment-level row
                    let tr = document.createElement("tr");
                    tr.innerHTML = `
                        <td><input type="number" name="piso[]" class="form-control" value="${row.piso}"></td>
                        <td><input type="number" name="apartamento[]" class="form-control" value="${row.apartamento}"></td>
                        <td><input type="number" name="tus_requeridos[]" class="form-control" value="${row.tus_requeridos}"></td>
                        <td><input type="number" name="cable_derivador[]" class="form-control" value="${row.largo_cable_derivador}"></td>
                        <td><input type="number" name="cable_repartidor[]" class="form-control" value="${row.largo_cable_repartidor}"></td>
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

