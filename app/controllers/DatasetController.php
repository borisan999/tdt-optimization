<?php

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}


// prefer composer autoload and include helper for robust loading
$autoload = __DIR__ . '/../../vendor/autoload.php';
if (is_file($autoload)) require_once $autoload;

$incHelper = __DIR__ . '/../helpers/IncludeHelper.php';
if (is_file($incHelper)) require_once $incHelper;

// replace these requires to use helper pattern
require_one_of([__DIR__ . "/../config/db.php"]);
require_one_of([__DIR__ . "/../models/Dataset.php"]);
require_one_of([__DIR__ . "/../models/DatasetRow.php"]);
require_one_of([__DIR__ . "/../config/env.php"]);
require_one_of([__DIR__ . "/../models/GeneralParams.php"]);
require_one_of([__DIR__ . "/../models/Result.php"]);
require_one_of([__DIR__ . "/../models/ResultsDetail.php"]);



$_SESSION['upload_warnings'] = [];

use PhpOffice\PhpSpreadsheet\IOFactory;


class DatasetController
{
    public function handleRequest()
    {
        $action = $_GET['action'] ?? null;

        switch ($action) {

            case "upload_excel":
                $this->uploadExcel();
                break;

            case "manual_entry":
                $this->manualEntry();
                break;

            case "history":
                $this->loadHistory();
                break;

            case "run_python":
                $opt_id = $_GET["dataset_id"] ?? null;

                if (!$opt_id) {
                    throw new Exception("Missing dataset_id for run_python()");
                }

                $this->runPython($opt_id);
                break;

            default:
                $this->manualEntryForm();
                break;
        }
    }

    /**
     * -----------------------------------------
     * MANUAL ENTRY (Insert dataset + rows)
     * -----------------------------------------
     */
    private function manualEntry()
    {
        require_once __DIR__ . "/../models/ValidationEngine.php";
        require_once __DIR__ . "/../models/ValidationRules.php";

        $validator = new ValidationEngine(new ValidationRules());

        require_once __DIR__ . '/../models/GeneralParams.php';
        $gpModel = new GeneralParams();

        $_SESSION['upload_warnings'] = [];

        // Validate APARTMENT rows
        foreach ($_POST['piso'] as $i => $piso) {

            $fields = [
                'piso'                    => $_POST['piso'][$i],
                'apartamento'             => $_POST['apartamento'][$i],
                'tus_requeridos'          => $_POST['tus_requeridos'][$i],
                'largo_cable_derivador'   => $_POST['cable_derivador'][$i],
                'largo_cable_repartidor'  => $_POST['cable_repartidor'][$i]
            ];

            foreach ($fields as $fname => $value) {
                $msgs = $validator->validate($fname, $value);

                foreach ($msgs as $m) {
                    $msgTxt = "{$fname} (row {$i}): {$m['message']} [value: {$value}]";

                    if ($m['severity'] === 'error') {
                        $_SESSION['manual_errors'][] = $msgTxt;
                    } else {
                        $_SESSION['manual_warnings'][] = $msgTxt;
                    }
                }
            }
        }

        // Validate TU rows
        foreach ($_POST['tu_index'] as $i => $tuidx) {

            $fields = [
                'piso'           => $_POST['tu_piso'][$i],
                'apartamento'    => $_POST['tu_apartamento'][$i],
                'tu_index'       => $_POST['tu_index'][$i],
                'largo_cable_tu' => $_POST['largo_tu'][$i],
            ];

            foreach ($fields as $fname => $value) {
                $msgs = $validator->validate($fname, $value);

                foreach ($msgs as $m) {
                    $msgTxt = "TU {$fname} (row {$i}): {$m['message']} [value: {$value}]";

                    if ($m['severity'] === 'error') {
                        $_SESSION['manual_errors'][] = $msgTxt;
                    } else {
                        $_SESSION['manual_warnings'][] = $msgTxt;
                    }
                }
            }
        }

        // If errors, stop and return to UI
        if (!empty($_SESSION['manual_errors'])) {
            header("Location: ../../public/enter_data.php?manual_error=1");
            exit;
        }

        $uploaded_by = $_SESSION['user_id'] ?? 1;

        // Retrieve form arrays
        $pisos          = $_POST['piso'] ?? [];
        $apartamentos   = $_POST['apartamento'] ?? [];
        $tus            = $_POST['tus_requeridos'] ?? [];
        $c_derivador    = $_POST['cable_derivador'] ?? [];
        $c_repartidor   = $_POST['cable_repartidor'] ?? [];

        $tu_piso        = $_POST['tu_piso'] ?? [];
        $tu_apto        = $_POST['tu_apartamento'] ?? [];
        $tu_index       = $_POST['tu_index'] ?? [];
        $tu_length      = $_POST['largo_tu'] ?? [];

        if (count($pisos) === 0) {
            die("❌ No apartment records submitted.");
        }

        // Create new dataset
        $dataset = new Dataset();
        // If a dataset was loaded, UPDATE it instead of creating a new one
        if (isset($_SESSION['loaded_dataset_id'])) {
            $dataset_id = $_SESSION['loaded_dataset_id'];

            // Remove old rows before inserting new ones
            $rowModel = new DatasetRow();
            $rowModel->deleteRowsByDataset($dataset_id);

        } else {
            // Normal behavior: create a new dataset
            $dataset_id = $dataset->create($_SESSION['user_id'], "pending");
        }
        // ----------------------------------
        // Save General Parameters (manual)
        // ----------------------------------
        $generalParams = [];

        foreach ($_POST as $key => $value) {
            if (str_starts_with($key, 'param_')) {
                $paramName = substr($key, 6); // remove 'param_'
                $generalParams[$paramName] = $value;
            }
        }

        if (!empty($generalParams)) {
            $gpModel->saveForDataset($dataset_id, $generalParams);
        }

        $rowModel = new DatasetRow();

        /**
         * Insert APARTMENT rows
         */
        $record_index = 0;

        for ($i = 0; $i < count($pisos); $i++) {

            $rowModel->addRow($dataset_id, $record_index, "piso", $pisos[$i], "floor");
            $rowModel->addRow($dataset_id, $record_index, "apartamento", $apartamentos[$i], "apt");
            $rowModel->addRow($dataset_id, $record_index, "tus_requeridos", $tus[$i], "units");
            $rowModel->addRow($dataset_id, $record_index, "largo_cable_derivador", $c_derivador[$i], "m");
            $rowModel->addRow($dataset_id, $record_index, "largo_cable_repartidor", $c_repartidor[$i], "m");

            $record_index++;
        }

        /**
         * Insert TU rows
         */
        for ($i = 0; $i < count($tu_piso); $i++) {

            $rowModel->addRow($dataset_id, $record_index, "piso", $tu_piso[$i], "floor");
            $rowModel->addRow($dataset_id, $record_index, "apartamento", $tu_apto[$i], "apt");
            $rowModel->addRow($dataset_id, $record_index, "tu_index", $tu_index[$i], null);
            $rowModel->addRow($dataset_id, $record_index, "largo_cable_tu", $tu_length[$i], "m");

            $record_index++;
        }
        // Rehydrate UI state from submitted form
        //$_SESSION['loaded_params'] = array_filter($_POST, fn($k) => str_starts_with($k, 'param_'), ARRAY_FILTER_USE_KEY);
        $_SESSION['loaded_params'] = [];

        foreach ($_POST as $key => $value) {
            if (str_starts_with($key, 'param_')) {
                $paramName = substr($key, 6); // remove 'param_'
                $_SESSION['loaded_params'][$paramName] = $value;
            }
        }

        // Rebuild dataset-style buffer for UI
        $_SESSION['loaded_dataset'] = [];
        $_SESSION['loaded_dataset_id'] = $dataset_id;

        // Apartments
        for ($i = 0; $i < count($_POST['piso']); $i++) {
            $_SESSION['loaded_dataset'][] = ['record_index'=>$i,'field_name'=>'piso','field_value'=>$_POST['piso'][$i]];
            $_SESSION['loaded_dataset'][] = ['record_index'=>$i,'field_name'=>'apartamento','field_value'=>$_POST['apartamento'][$i]];
            $_SESSION['loaded_dataset'][] = ['record_index'=>$i,'field_name'=>'tus_requeridos','field_value'=>$_POST['tus_requeridos'][$i]];
            $_SESSION['loaded_dataset'][] = ['record_index'=>$i,'field_name'=>'largo_cable_derivador','field_value'=>$_POST['cable_derivador'][$i]];
            $_SESSION['loaded_dataset'][] = ['record_index'=>$i,'field_name'=>'largo_cable_repartidor','field_value'=>$_POST['cable_repartidor'][$i]];
        }

        // TUs
        $offset = count($_POST['piso']);
        for ($i = 0; $i < count($_POST['tu_piso']); $i++) {
            $idx = $offset + $i;
            $_SESSION['loaded_dataset'][] = ['record_index'=>$idx,'field_name'=>'piso','field_value'=>$_POST['tu_piso'][$i]];
            $_SESSION['loaded_dataset'][] = ['record_index'=>$idx,'field_name'=>'apartamento','field_value'=>$_POST['tu_apartamento'][$i]];
            $_SESSION['loaded_dataset'][] = ['record_index'=>$idx,'field_name'=>'tu_index','field_value'=>$_POST['tu_index'][$i]];
            $_SESSION['loaded_dataset'][] = ['record_index'=>$idx,'field_name'=>'largo_cable_tu','field_value'=>$_POST['largo_tu'][$i]];
        }

        if (empty($_POST['tu_index']) || count($_POST['tu_index']) === 0) {
            throw new RuntimeException(
                "Manual entry produced zero TUs — dataset is invalid for optimization"
            );
        }
        header("Location: ../../public/enter_data.php?saved=1&dataset_id={$dataset_id}");
        exit;
    }

    /**
     * -----------------------------------------
     * LOAD HISTORY (Retrieve rows + fill session)
     * -----------------------------------------
     */
    private function loadHistory()
    {
        if (!isset($_POST['dataset_id'])) {
            die("No dataset selected.");
        }

        $dataset_id = intval($_POST['dataset_id']);

        $rowModel = new DatasetRow();
        $rows = $rowModel->getRowsByDataset($dataset_id);

        // Save in session
        $_SESSION['loaded_dataset'] = $rows;
        $_SESSION['loaded_dataset_id'] = $dataset_id;
        require_once __DIR__ . "/../models/GeneralParams.php";
        $gpModel = new GeneralParams();

        $params = $gpModel->getByDataset($dataset_id);
        $_SESSION['loaded_params'] = $params;

        header("Location: ../../public/enter_data.php?loaded=1");
        exit;
    }

    public function manualEntryForm()
    {
        include __DIR__ . "/../../public/enter_data.php";
    }

   /**
     * Upload Excel (three-sheet template):
     *  - sheet "parametros_generales" -> key/value (A=param_name, B=param_value)
     *  - sheet "apartamentos"         -> columns: piso, apartamento, tus_requeridos, largo_cable_derivador, largo_cable_repartidor
     *  - sheet "tu"                   -> columns: piso, apartamento, tu_index, largo_cable_tu
     *
     * On success:
     *  - create dataset row in datasets
     *  - save parametros_generales rows (parametros_generales table)
     *  - save dataset_rows for apartments and tu rows (DatasetRow->addRow)
     *  - store loaded data in session: loaded_params, loaded_dataset (like history load)
     *  - redirect to enter_data.php?loaded=1&dataset_id=...
     */
    /**
     * Upload Excel (production, with validation rules).
     */
    private function uploadExcel()
    {
        // composer autoload for PhpSpreadsheet
        require_once __DIR__ . "/../../vendor/autoload.php";
        // ensure validation model available
        require_once __DIR__ . "/../models/ValidationEngine.php";
        require_once __DIR__ . "/../models/ValidationRules.php";

        $reader = new \PhpOffice\PhpSpreadsheet\Reader\Xlsx();

        if (!isset($_FILES['excel_file'])) {
            $this->logEvent('error', 'No file uploaded', $_SESSION['user_id'] ?? null);
            http_response_code(400); die("No file uploaded.");
        }

        $tmp = $_FILES['excel_file']['tmp_name'];
        if (!is_uploaded_file($tmp)) {
            $this->logEvent('error', 'Upload file missing in tmp', $_SESSION['user_id'] ?? null);
            http_response_code(400); die("Upload failed.");
        }

        try {
            $spreadsheet = $reader->load($tmp);
        } catch (\Throwable $e) {
            $this->logEvent('error', 'PhpSpreadsheet load error: ' . $e->getMessage(), $_SESSION['user_id'] ?? null);
            http_response_code(500); die("Failed to read Excel file: " . $e->getMessage());
        }

        // required sheet names
        $requiredSheets = ['parametros_generales', 'apartamentos', 'tu'];
        foreach ($requiredSheets as $s) {
            if (!$spreadsheet->sheetNameExists($s)) {
                $this->logEvent('error', "Missing sheet: $s", $_SESSION['user_id'] ?? null);
                http_response_code(400); die("Excel file must include sheet: $s");
            }
        }

        $pdo = (new Database())->getConnection();

        try {
            $pdo->beginTransaction();

            // create dataset record
            $datasetModel = new Dataset();
            $dataset_id = $datasetModel->create($_SESSION['user_id'] ?? null, "pending");

            $gpModel = new GeneralParams();
            $rowModel = new DatasetRow();
            $validator = new ValidationRules();

            // init session buffers for UI auto-fill
            $_SESSION['loaded_params'] = [];
            $_SESSION['loaded_dataset'] = [];
            $_SESSION['loaded_dataset_id'] = $dataset_id;

            /*
            * 1) Parse parametros_generales
            */
            $sheet = $spreadsheet->getSheetByName('parametros_generales');
            $rows = $sheet->toArray(null, true, true, true);
            $params = [];
            foreach ($rows as $rnum => $r) {
                $name = trim((string)($r['A'] ?? ''));
                $val  = trim((string)($r['B'] ?? ''));

                if ($name === '' && $val === '') continue;
                $lower = strtolower($name);
                if (in_array($lower, ['param_name','parameter','param'])) continue;

                // validate general parameter if rules exist
                $msgs = $validator->validate($name, $val);
                foreach ($msgs as $m) {
                    // log warnings, abort on error
                    if ($m['severity'] === 'error') {
                        throw new \Exception("General param '{$name}': {$m['message']}");
                    } else {
                        $this->logEvent('warning', "General param '{$name}': {$m['message']}", $_SESSION['user_id'] ?? null);
                    }
                }

                $params[$name] = $val;
            }

            if (!empty($params)) {
                $gpModel->saveForDataset($dataset_id, $params);
                $_SESSION['loaded_params'] = $params;
            }

            /*
            * 2) Parse apartamentos
            * columns: A=piso, B=apartamento, C=tus_requeridos, D=largo_cable_derivador, E=largo_cable_repartidor
            */
            $sheet = $spreadsheet->getSheetByName('apartamentos');
            $rows = $sheet->toArray(null, true, true, true);
            $recordIndex = 1;

            foreach ($rows as $rnum => $r) {
                $piso = trim((string)($r['A'] ?? ''));
                $apt  = trim((string)($r['B'] ?? ''));
                $tus  = trim((string)($r['C'] ?? ''));
                $der  = trim((string)($r['D'] ?? ''));
                $rep  = trim((string)($r['E'] ?? ''));

                // skip empty row
                if ($piso === '' && $apt === '' && $tus === '' && $der === '' && $rep === '') continue;

                // header skip
                if (strtolower($piso) === 'piso') continue;

                // basic required field check
                if ($apt === '') {
                    throw new \Exception("Missing apartment value on apartamentos sheet, row {$rnum}");
                }

                // apply validation for each relevant field
                $this->applyValidation($validator, 'piso', $piso, $rnum);
                $this->applyValidation($validator, 'apartamento', $apt, $rnum);
                $this->applyValidation($validator, 'tus_requeridos', $tus, $rnum);
                $this->applyValidation($validator, 'largo_cable_derivador', $der, $rnum);
                $this->applyValidation($validator, 'largo_cable_repartidor', $rep, $rnum);

                // save rows
                $rowModel->addRow($dataset_id, $recordIndex, 'piso', $piso, 'floor');
                $rowModel->addRow($dataset_id, $recordIndex, 'apartamento', $apt, 'apt');
                $rowModel->addRow($dataset_id, $recordIndex, 'tus_requeridos', $tus, 'units');
                $rowModel->addRow($dataset_id, $recordIndex, 'largo_cable_derivador', $der, 'm');
                $rowModel->addRow($dataset_id, $recordIndex, 'largo_cable_repartidor', $rep, 'm');

                // session buffer
                $_SESSION['loaded_dataset'][] = ['record_index'=>$recordIndex,'field_name'=>'piso','field_value'=>$piso];
                $_SESSION['loaded_dataset'][] = ['record_index'=>$recordIndex,'field_name'=>'apartamento','field_value'=>$apt];
                $_SESSION['loaded_dataset'][] = ['record_index'=>$recordIndex,'field_name'=>'tus_requeridos','field_value'=>$tus];
                $_SESSION['loaded_dataset'][] = ['record_index'=>$recordIndex,'field_name'=>'largo_cable_derivador','field_value'=>$der];
                $_SESSION['loaded_dataset'][] = ['record_index'=>$recordIndex,'field_name'=>'largo_cable_repartidor','field_value'=>$rep];

                $recordIndex++;
            }

            /*
            * 3) Parse tu sheet
            * columns: A=piso, B=apartamento, C=tu_index, D=largo_cable_tu
            */
            $sheet = $spreadsheet->getSheetByName('tu');
            $rows = $sheet->toArray(null, true, true, true);

            foreach ($rows as $rnum => $r) {
                $piso = trim((string)($r['A'] ?? ''));
                $apt  = trim((string)($r['B'] ?? ''));
                $idx  = trim((string)($r['C'] ?? ''));
                $len  = trim((string)($r['D'] ?? ''));

                if ($piso === '' && $apt === '' && $idx === '' && $len === '') continue;
                if (strtolower($piso) === 'piso') continue;

                if ($apt === '') throw new \Exception("Missing apartment in TU sheet row {$rnum}");

                // validations
                $this->applyValidation($validator, 'piso', $piso, $rnum);
                $this->applyValidation($validator, 'apartamento', $apt, $rnum);
                $this->applyValidation($validator, 'tu_index', $idx, $rnum);
                $this->applyValidation($validator, 'largo_cable_tu', $len, $rnum);

                // save
                $rowModel->addRow($dataset_id, $recordIndex, 'piso', $piso, 'floor');
                $rowModel->addRow($dataset_id, $recordIndex, 'apartamento', $apt, 'apt');
                $rowModel->addRow($dataset_id, $recordIndex, 'tu_index', $idx, null);
                $rowModel->addRow($dataset_id, $recordIndex, 'largo_cable_tu', $len, 'm');

                $_SESSION['loaded_dataset'][] = ['record_index'=>$recordIndex,'field_name'=>'piso','field_value'=>$piso];
                $_SESSION['loaded_dataset'][] = ['record_index'=>$recordIndex,'field_name'=>'apartamento','field_value'=>$apt];
                $_SESSION['loaded_dataset'][] = ['record_index'=>$recordIndex,'field_name'=>'tu_index','field_value'=>$idx];
                $_SESSION['loaded_dataset'][] = ['record_index'=>$recordIndex,'field_name'=>'largo_cable_tu','field_value'=>$len];

                $recordIndex++;
            }

            // commit
            $pdo->commit();

            $this->logEvent('info', "Excel upload processed dataset_id={$dataset_id}", $_SESSION['user_id'] ?? null);

            // redirect to manual form that will auto-fill from session
            header("Location: ../../public/enter_data.php?loaded=1&dataset_id={$dataset_id}");
            exit();

        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $this->logEvent('error', "Upload failed: " . $e->getMessage(), $_SESSION['user_id'] ?? null);
            http_response_code(400);
            die("Upload failed: " . htmlspecialchars($e->getMessage()));
        }
    }

    /**
     * Apply validation rules for a single field value using ValidationRules model.
     * - on 'error' severity: throws Exception (will rollback upload)
     * - on 'warning' severity: logs warning but continues
     */
    private function applyValidation($validator, $field_name, $value, $rnum = null)
    {
        $msgs = $validator->validate($field_name, $value);

        foreach ($msgs as $m) {
            $msgText = "{$field_name} (row {$rnum}): {$m['message']} [value: {$value}]";

            if ($m['severity'] === 'error') {
                throw new \Exception("ERROR: " . $msgText);
            } else {
                // Store warnings to show in UI
                $_SESSION['upload_warnings'][] = $msgText;

                // Also log warning
                $this->logEvent('warning', $msgText, $_SESSION['user_id'] ?? null);
            }
        }
    }

    /**
     * Simple logger that writes to `logs` table (if present).
     */
    private function logEvent($type, $msg, $userId = null)
    {
    try {
        $db = new Database();
        $pdo = $db->getConnection();

        if (!$pdo) {
            die("DEBUG: PDO is NULL inside logEvent()");
        }

        $sql = "INSERT INTO logs (event_type, description, user_id) VALUES (:t, :d, :u)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            't' => $type,
            'd' => $msg,
            'u' => $userId
        ]);
    } catch (\Throwable $ignore) {
            // don't break the flow if logging fails
        }
    }

    public function runPython($dataset_id)
{
    $DB = new Database();
    $pdo = $DB->getConnection();

    /* ---------------------------------------------------------
       1) Python executable + script path
    --------------------------------------------------------- */
    $python_bin = "/usr/bin/python3";
    $python_script = realpath(__DIR__ . "/../python/10/optimizer_db.py");

    if (!$python_script || !is_file($python_script)) {
        $this->logEvent('error', "Python script not found: {$python_script}", $_SESSION['user_id'] ?? null);
        throw new Exception("Python script not found.");
    }

    /* ---------------------------------------------------------
       2) Ensure output dir (Python side) exists
    --------------------------------------------------------- */
    putenv("OUTPUT_DIR=/var/tdt_outputs");

    /* ---------------------------------------------------------
       3) Build safe exec command
    --------------------------------------------------------- */
    $cmd = escapeshellcmd($python_bin) . " " .
           escapeshellarg($python_script) . " " .
           escapeshellarg((string)$dataset_id) . " 2>&1";

    $output = [];
    $return_var = 0;
    exec($cmd, $output, $return_var);

    /* ---------------------------------------------------------
       4) Execution error?
    --------------------------------------------------------- */
    if ($return_var !== 0) {
        $this->logEvent('error',
            "Python failed (rc={$return_var}) Output: " . implode("\n", array_slice($output, -20)),
            $_SESSION['user_id'] ?? null
        );
        throw new Exception("Python script failed with return code {$return_var}");
    }

    /* ---------------------------------------------------------
       5) Extract JSON from final stdout
    --------------------------------------------------------- */
    $stdout = implode("\n", $output);

    $jsonStart = strpos($stdout, "{");
    if ($jsonStart === false) {
        $this->logEvent('error',
            "No JSON from python. Raw output: " . substr($stdout, 0, 4000),
            $_SESSION['user_id'] ?? null
        );
        throw new Exception("Python did not return JSON.");
    }

    $jsonStr = substr($stdout, $jsonStart);
    $data = json_decode($jsonStr, true);

    if (json_last_error() !== JSON_ERROR_NONE) {
        $this->logEvent('error',
            "JSON decode error: " . json_last_error_msg() . " Payload: " . substr($jsonStr, 0, 4000),
            $_SESSION['user_id'] ?? null
        );
        throw new Exception("Malformed JSON returned by Python: " . json_last_error_msg());
    }

    /* ---------------------------------------------------------
       6) Validate python result
    --------------------------------------------------------- */
    $py_status = strtolower(trim($data['status'] ?? ''));

    // Mapping Python -> DB enum
    $map = [
        'success'    => 'completed',   // Successful optimization
        'optimal'    => 'completed',   // MILP found optimal solution
        'infeasible' => 'infeasible',  // MILP infeasible
        'failed'     => 'failed'       // Explicit fails
    ];

    // Fallback to failed if unknown
    $status = $map[$py_status] ?? 'failed';
    

    if (empty($data['opt_id'])) {
        throw new Exception("Python did not return opt_id.");
    }

    $opt_id = intval($data['opt_id']);

    /* ---------------------------------------------------------
       7) Ensure optimization row exists or update state
    --------------------------------------------------------- */
    $st = $pdo->prepare("SELECT 1 FROM optimizations WHERE opt_id = :id");
    $st->execute(['id' => $opt_id]);

    if ($st->rowCount() === 0) {
        $insert = $pdo->prepare("
            INSERT INTO optimizations (opt_id, dataset_id, status, created_at)
            VALUES (:opt_id, :dataset_id, :status, NOW())
        ");
        $insert->execute([
            'opt_id' => $opt_id,
            'dataset_id' => $dataset_id,
            'status' => $status
        ]);
    } else {
        $update = $pdo->prepare("UPDATE optimizations SET status = :status WHERE opt_id = :opt_id");
        $update->execute([
            'status' => $status,
            'opt_id' => $opt_id
        ]);
    }

    /* ---------------------------------------------------------
       8)  Normalize Python payload to canonical EPIC-2 keys
    --------------------------------------------------------- */
    
    if (isset($data['detalle']) && !isset($data['detail'])) {
        $data['detail'] = $data['detalle'];
    }
    if (
        !isset($data['detail']) ||
        !is_array($data['detail']) ||
        count($data['detail']) === 0
    ) {
        $data['summary']['warning'] = 'No valid TUs generated for given parameters';
    }

    $detailRows = $data['detail'] ?? [];

    $summaryJson = json_encode($data['summary'], JSON_UNESCAPED_UNICODE);
    $detailJson  = json_encode($detailRows, JSON_UNESCAPED_UNICODE);

    /* ---------------------------------------------------------
       9) Store per-TU results
    --------------------------------------------------------- */
    
    $pdo->prepare("
        INSERT INTO results (opt_id, summary_json, detail_json)
        VALUES (:opt_id, :summary, :detail)
        ON DUPLICATE KEY UPDATE
            summary_json = VALUES(summary_json),
            detail_json  = VALUES(detail_json)
    ")->execute([
        'opt_id'  => $opt_id,
        'summary' => $summaryJson,
        'detail'  => $detailJson,
    ]);


    /* ---------------------------------------------------------
       10) Redirect to results UI
    --------------------------------------------------------- */
    header("Location: ../../public/results.php?opt_id=" . intval($opt_id));
    exit();
}



    

}

$controller = new DatasetController();
$controller->handleRequest();



?>
