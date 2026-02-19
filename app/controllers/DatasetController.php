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
use app\services\CanonicalResultBuilder;


class DatasetController
{
    public function handleRequest()
    {
        $action = $_GET['action'] ?? null;
        $loaded = isset($_GET['loaded']) && $loaded === '1';
        $dataset_id_param = $_GET['dataset_id'] ?? null;

        if ($loaded && $dataset_id_param !== null && $action === null) {
            // If the page is loaded with a dataset_id but no explicit action,
            // assume it's meant to load the history for that dataset.
            // This prevents manualEntryForm from being called directly without data.
            $this->loadHistoryFromGet($dataset_id_param); // New method to load from GET
            return; // Exit after loading history and before including the form again
        }

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

    // New method to load history from GET parameters
    private function loadHistoryFromGet($dataset_id)
    {
        // This is a simplified version of loadHistory that uses the dataset_id from GET
        // and sets session variables directly, then redirects back to enter_data.php (without params)
        // to prevent infinite redirects/reloads.

        $dataset_id = intval($dataset_id); // Ensure it's an integer

        $rowModel = new DatasetRow();
        $structuredDatasetFromDb = $rowModel->buildStructuredData($dataset_id);
       // $_SESSION['loaded_canonical_dataset'] = $structuredDatasetFromDb['canonical'];
        $_SESSION['loaded_canonical_dataset'] = [
            'inputs'     => $_SESSION['loaded_params'],
            'apartments' => $structuredDatasetFromDb['apartments'],
            'tus'        => $structuredDatasetFromDb['tus'],
        ];

      /*  $_SESSION['loaded_dataset_id'] = $dataset_id;
        $_SESSION['loaded_canonical_dataset'] = array_merge($structuredDatasetFromDb['apartments'], $structuredDatasetFromDb['tus']);

        */require_once __DIR__ . "/../models/GeneralParams.php";
        $gpModel = new GeneralParams();
        $params = $gpModel->getByDataset($dataset_id);
        $_SESSION['loaded_params'] = $params;

        // Redirect to enter_data.php without the 'loaded' and 'dataset_id' GET parameters
        // to avoid re-triggering this logic on subsequent form submissions/page reloads.
        header("Location: enter_data.php");
        exit;
    }

    /**
     * -----------------------------------------
     * MANUAL ENTRY (Insert dataset + rows)
     * -----------------------------------------
     */
    private function manualEntry()
    {
        if (empty($_POST['piso']) || !is_array($_POST['piso'])) {
            error_log("Manual entry called without apartment data");
            $_SESSION['flash_error'] = "No apartment records submitted.";
            header("Location: /tdt-optimization/public/enter_data.php");
            exit;
        }

        require_once __DIR__ . "/../models/ValidationEngine.php";
        require_once __DIR__ . "/../models/ValidationRules.php";
        require_once __DIR__ . '/../models/GeneralParams.php';
        $gpModel = new GeneralParams();

        $validator = new ValidationEngine(new ValidationRules());

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

        // Create a map of submitted TU data for easy lookup
        $submittedTuMap = [];
        $numSubmittedTus = count($tu_piso);
        for ($i = 0; $i < $numSubmittedTus; $i++) {
            $piso = $tu_piso[$i];
            $apartamento = $tu_apto[$i];
            $tuIndex = $tu_index[$i];
            $largoTu = $tu_length[$i];
            $submittedTuMap[$piso][$apartamento][$tuIndex] = $largoTu;
        }

        // --- Start of combined APARTMENT and TU insertion ---
        $record_index = 0; // Initialize record_index for apartment rows

        for ($i = 0; $i < count($pisos); $i++) {
            // Save apartment info
            $rowModel->addRow($dataset_id, $record_index, "piso", $pisos[$i], "floor");
            $rowModel->addRow($dataset_id, $record_index, "apartamento", $apartamentos[$i], "apt");
            $rowModel->addRow($dataset_id, $record_index, "tus_requeridos", $tus[$i], "units");
            $rowModel->addRow($dataset_id, $record_index, "largo_cable_derivador", $c_derivador[$i], "m");
            $rowModel->addRow($dataset_id, $record_index, "largo_cable_repartidor", $c_repartidor[$i], "m");

            // Insert TU rows for this apartment based on tus_requeridos
            $currentPiso = $pisos[$i];
            $currentApartamento = $apartamentos[$i];
            $numTusRequired = $tus[$i];

            for ($tu_idx_for_apt = 1; $tu_idx_for_apt <= $numTusRequired; $tu_idx_for_apt++) { // tu_idx should start from 1 based on TU Index in form
                $tu_record_index = ($record_index + 1) * 100 + $tu_idx_for_apt; // Unique index for TU within its apartment

                // Look up largo_cable_tu from the submitted map
                // If not found in map (e.g., TU was not explicitly submitted), default to 0
                $largo_cable_tu = $submittedTuMap[$currentPiso][$currentApartamento][$tu_idx_for_apt] ?? 0;

                $rowModel->addRow($dataset_id, $tu_record_index, "piso", $currentPiso, "floor");
                $rowModel->addRow($dataset_id, $tu_record_index, "apartamento", $currentApartamento, "apt");
                $rowModel->addRow($dataset_id, $tu_record_index, "tu_index", $tu_idx_for_apt, null); // Use $tu_idx_for_apt as tu_index
                $rowModel->addRow($dataset_id, $tu_record_index, "largo_cable_tu", $largo_cable_tu, "m");
            }

            $record_index++;
        }
        // --- End of combined APARTMENT and TU insertion ---

        // After saving, reload the dataset from DB to ensure session has the latest state
        $datasetId = $dataset_id; // Use the existing $dataset_id variable from the manual entry process

        $rowModel = new DatasetRow();

        /**
         * Rebuild canonical dataset from DB rows
         */
        // buildStructuredData now returns ['apartments' => [...], 'tus' => [...]]
        $structuredData = $rowModel->buildStructuredData($datasetId); 

        /**
         * Rehydrate editor state
         */
        $_SESSION['loaded_canonical_dataset'] = [
            'inputs'      => $generalParams,              // already known at save time
            'apartments'  => $structuredData['apartments'], // Directly use the apartments array
            'tus'         => $structuredData['tus']       // Directly use the tus array
        ];

        $_SESSION['loaded_dataset_id'] = $datasetId;
        $_SESSION['loaded_params']     = $generalParams;

        // Add temporary debug line as requested
        // This log will be written to the PHP error log.
        error_log('CANONICAL AFTER SAVE: ' . json_encode($_SESSION['loaded_canonical_dataset']));

        // Redirect back to editor with dataset_id and saved status
        header("Location: /tdt-optimization/public/enter_data.php?dataset_id={$datasetId}&saved=1");
        exit;

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
        $structuredData = $rowModel->buildStructuredData($dataset_id);

        require_once __DIR__ . "/../models/GeneralParams.php";
        $gpModel = new GeneralParams();
        $params = $gpModel->getByDataset($dataset_id);

        $_SESSION['loaded_dataset_id'] = $dataset_id;
        $_SESSION['loaded_params']     = $params;

        /**
         * Canonical editor hydration
         * Must match Excel upload structure exactly
         */
        $_SESSION['loaded_canonical_dataset'] = [
            'inputs'      => $params ?? [],
            'apartments'  => $structuredData['apartments'] ?? [],
            'tus'         => $structuredData['tus'] ?? []
        ];

        header("Location: ../../public/enter_data.php?loaded=1&dataset_id={$dataset_id}");
        exit;
    }


    public function manualEntryForm()
    {
        // If a dataset_id is already loaded in session, re-hydrate session data from the DB
        // This ensures the form re-displays current data after operations like floor repetition
        if (isset($_SESSION['loaded_dataset_id'])) {
            $dataset_id = $_SESSION['loaded_dataset_id'];

            $rowModel = new DatasetRow();
            $structuredDatasetFromDb = $rowModel->buildStructuredData($dataset_id);

            $_SESSION['loaded_dataset'] = [
                'apartments' => $structuredDatasetFromDb['apartments'],
                'tus' => $structuredDatasetFromDb['tus']
            ];
            // Canonical data is the merged version
            $_SESSION['loaded_canonical_dataset'] = array_merge($structuredDatasetFromDb['apartments'], $structuredDatasetFromDb['tus']);

            require_once __DIR__ . "/../models/GeneralParams.php";
            $gpModel = new GeneralParams();
            $params = $gpModel->getByDataset($dataset_id);
            $_SESSION['loaded_params'] = $params;
        }

        include __DIR__ . "/../../public/enter_data.php";
    }

    private function jsonResponse($data)
    {
        header('Content-Type: application/json');
        echo json_encode($data);
        exit;
    }

    private function deepKsort(&$array)
    {
        if (!is_array($array)) return;

        ksort($array);
        foreach ($array as &$value) {
            if (is_array($value)) {
                $this->deepKsort($value);
            }
        }
    }

    private function canonicalJson($data)
    {
        return json_encode(
            $data,
            JSON_UNESCAPED_UNICODE |
            JSON_UNESCAPED_SLASHES |
            JSON_PRESERVE_ZERO_FRACTION
        );
    }

    private function castNumeric($value)
    {
        if (is_numeric($value)) {
            if (strpos((string)$value, '.') !== false) {
                return (float)$value;
            }
            return (int)$value;
        }
        return $value;
    }

   /**
     * Upload Excel (production, with validation rules).
     */
    public function uploadExcel()
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            die("Invalid request method.");
        }

        if (!isset($_FILES['excel_file']) || $_FILES['excel_file']['error'] !== UPLOAD_ERR_OK) {
            die("File upload failed.");
        }

        $ext = pathinfo($_FILES['excel_file']['name'], PATHINFO_EXTENSION);
        if (strtolower($ext) !== 'xlsx') {
            die("Only .xlsx files allowed.");
        }

        try {

            $file = $_FILES['excel_file']['tmp_name'];
            $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($file);

            $datasetModel = new Dataset();
            $uploaded_by = $_SESSION['user_id'] ?? 1;
            $dataset_id = $datasetModel->create($uploaded_by, "pending");

            $canonical = [];

            /* =====================================================
            1️⃣ PARAMETROS_GENERALES
            ====================================================== */
            $sheet = $spreadsheet->getSheetByName('Parametros_Generales');
            $rows = $sheet->toArray(null, true, true, false);

            $paramMap = [];

            foreach ($rows as $i => $row) {
                if ($i === 0) continue;
                if (!empty($row[0])) {
                    $paramMap[trim($row[0])] = $this->castNumeric($row[1]);
                }
            }

            // Map EXACT Python keys
            $canonical['Piso_Maximo'] = $this->getv($paramMap, 'Piso_Maximo', null, 'int');
            $canonical['apartamentos_por_piso'] = $this->getv($paramMap, 'Apartamentos_Piso', null, 'int');

            $canonical['atenuacion_cable_por_metro'] =
                $this->getv($paramMap, 'Atenuacion_Cable_dBporM', 0.2, 'float');

            $canonical['atenuacion_cable_470mhz'] =
                $this->getv($paramMap, 'Atenuacion_Cable_470MHz_dBporM', 0.127, 'float');

            $canonical['atenuacion_cable_698mhz'] =
                $this->getv($paramMap, 'Atenuacion_Cable_698MHz_dBporM', 0.1558, 'float');

            $canonical['atenuacion_conector'] =
                $this->getv($paramMap, 'Atenuacion_Conector_dB', 0.2, 'float');

            $canonical['largo_cable_entre_pisos'] =
                $this->getv($paramMap, 'Largo_Entre_Pisos_m', 3.0, 'float');

            $canonical['potencia_entrada'] =
                $this->getv($paramMap, 'Potencia_Entrada_dBuV', 110.0, 'float');

            $canonical['Nivel_minimo'] =
                $this->getv($paramMap, 'Nivel_Minimo_dBuV', 47.0, 'float');

            $canonical['Nivel_maximo'] =
                $this->getv($paramMap, 'Nivel_Maximo_dBuV', 70.0, 'float');

            $canonical['Potencia_Objetivo_TU'] =
                $this->getv($paramMap, 'Potencia_Objetivo_TU_dBuV', 60.0, 'float');

            $canonical['conectores_por_union'] =
                $this->getv($paramMap, 'Conectores_por_Union', 2, 'int');

            $canonical['atenuacion_conexion_tu'] =
                $this->getv($paramMap, 'Atenuacion_Conexion_TU_dB', 1.0, 'float');

            $canonical['largo_cable_amplificador_ultimo_piso'] =
                $this->getv($paramMap, 'Largo_Cable_Amplificador_Ultimo_Piso', 5, 'float');

            $canonical['largo_cable_feeder_bloque'] =
                $this->getv($paramMap, 'Largo_Feeder_Bloque_m (Mínimo)', 3.0, 'float');

            $canonical['p_troncal'] =
                (int) round($canonical['Piso_Maximo'] / 2, 0, PHP_ROUND_HALF_EVEN);


            /* =====================================================
            2️⃣ LARGO_CABLE_DERIVADOR_REPARTIDOR
            ====================================================== */
            $sheet = $spreadsheet->getSheetByName('largo_cable_derivador_repartido');
            $rows = $sheet->toArray(null, true, true, false);

            $lcd = [];
            foreach ($rows as $i => $row) {
                if ($i === 0) continue;
                if (!empty($row[0]) && !empty($row[1])) {
                    $key = trim($row[0]) . "|" . trim($row[1]);
                    $lcd[$key] = $this->castNumeric($row[2]);
                }
            }
            $canonical['largo_cable_derivador_repartidor'] = $lcd;

            /* =====================================================
            3️⃣ TUS REQUERIDOS
            ====================================================== */
            $sheet = $spreadsheet->getSheetByName('tus_requeridos_por_apartamento');
            $rows = $sheet->toArray(null, true, true, false);

            $tus = [];
            foreach ($rows as $i => $row) {
                if ($i === 0) continue;
                $key = trim($row[0]) . "|" . trim($row[1]);
                $tus[$key] = (int)$row[2];
            }
            $canonical['tus_requeridos_por_apartamento'] = $tus;

            /* =====================================================
            4️⃣ LARGO_CABLE_TU
            ====================================================== */
            $sheet = $spreadsheet->getSheetByName('largo_cable_tu');
            $rows = $sheet->toArray(null, true, true, false);

            $lctu = [];
            foreach ($rows as $i => $row) {
                if ($i === 0) continue;
                $key = trim($row[0]) . "|" . trim($row[1]) . "|" . trim($row[2]);
                $lctu[$key] = $this->castNumeric($row[3]);
            }
            $canonical['largo_cable_tu'] = $lctu;

            /* =====================================================
            5️⃣ DERIVADORES_DATA
            ====================================================== */
            $sheet = $spreadsheet->getSheetByName('derivadores_data');
            $rows = $sheet->toArray(null, true, true, false);

            $deriv = [];
            foreach ($rows as $i => $row) {
                if ($i === 0) continue;
                $code = trim($row[0]);
                $deriv[$code] = [
                    'derivacion' => (float)$row[1],
                    'paso' => (float)$row[2],
                    'salidas' => (int)$row[3],
                ];
            }
            $canonical['derivadores_data'] = $deriv;

            /* =====================================================
            6️⃣ REPARTIDORES_DATA
            ====================================================== */
            $sheet = $spreadsheet->getSheetByName('repartidores_data');
            $rows = $sheet->toArray(null, true, true, false);

            $rep = [];
            foreach ($rows as $i => $row) {
                if ($i === 0) continue;
                $code = trim($row[0]);
                $rep[$code] = [
                    'perdida_insercion' => $this->castNumeric($row[1]),
                    'salidas' => (int)$row[2],
                ];
            }
            $canonical['repartidores_data'] = $rep;

            /* =====================================================
            7️⃣ DETERMINISTIC SORT
            ====================================================== */
            $this->deepKsort($canonical);

            /* =====================================================
            8️⃣ CANONICAL JSON + HASH
            ====================================================== */
            $json = $this->canonicalJson($canonical);

            // FORENSIC DEBUGGING
            $debug_output = "";
            ob_start();
            var_dump($json);
            $debug_output .= "var_dump:\n" . ob_get_clean() . "\n";
            $debug_output .= "strlen: " . strlen($json) . "\n";
            $debug_output .= "bytes:\n" . print_r(unpack("C*", $json), true) . "\n";
            file_put_contents('/home/boris/.gemini/tmp/3eeaf677a99c707c6fd9e90f29636c8e2c33bb90b53b48e418e621ce7e949348/php_debug.txt', $debug_output);
            // END FORENSIC DEBUGGING

            $hash = hash('sha256', $json);

            $datasetModel->saveCanonicalInput($dataset_id, $json);

            return $this->jsonResponse([
                'success' => true,
                'dataset_id' => $dataset_id,
                'canonical_hash' => $hash
            ]);

        } catch (\Throwable $e) {
            return $this->jsonResponse([
                'success' => false,
                'error' => $e->getMessage()
            ]);
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
                $this->logEvent('warning', "{$field_name} (row {$rnum}): {$m['message']}", $_SESSION['user_id'] ?? null);
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
    private function getv($map, $key, $default = null, $cast = 'float')
    {
        if (array_key_exists($key, $map)) {
            $value = $map[$key];
        } else {
            $value = $default;
        }

        if ($cast === 'int') return (int)$value;
        if ($cast === 'float') return (float)$value;

        return $value;
    }

    function build_params_like_excel(array $dataset): array
    {
        $FLOAT_PRECISION = 6;

        $inputs = $dataset['inputs'] ?? [];
        $apartments = $dataset['apartments'] ?? [];
        $tus = $dataset['tus'] ?? [];

        // ----------------------------
        // Helper (Excel getv equivalent)
        // ----------------------------
        $getv = function($key, $default = 0) use ($inputs) {
            return isset($inputs[$key]) && $inputs[$key] !== ''
                ? $inputs[$key]
                : $default;
        };

        // ----------------------------
        // Scalars (exact Excel mirror)
        // ----------------------------

        $Piso_Maximo = (int)$getv("Piso_Maximo", 0);
        $apartamentos_por_piso = (int)$getv("Apartamentos_Piso", 0);

        $params = [
            "Piso_Maximo" => $Piso_Maximo,
            "apartamentos_por_piso" => $apartamentos_por_piso,

            "Nivel_minimo" => round((float)$getv("Nivel_Minimo_dBuV", 47), $FLOAT_PRECISION),
            "Nivel_maximo" => round((float)$getv("Nivel_Maximo_dBuV", 77), $FLOAT_PRECISION),
            "Potencia_Objetivo_TU" => round((float)$getv("Potencia_Objetivo_TU_dBuV", 60), $FLOAT_PRECISION),
            "potencia_entrada" => round((float)$getv("Potencia_Entrada_dBuV", 100), $FLOAT_PRECISION),

            "atenuacion_cable_por_metro" => round((float)$getv("Atenuacion_Cable_Por_Metro", 0.2), $FLOAT_PRECISION),
            "atenuacion_cable_470mhz" => round((float)$getv("Atenuacion_Cable_470MHz", 0), $FLOAT_PRECISION),
            "atenuacion_cable_698mhz" => round((float)$getv("Atenuacion_Cable_698MHz", 0), $FLOAT_PRECISION),
            "atenuacion_conector" => round((float)$getv("Atenuacion_Conector", 0.2), $FLOAT_PRECISION),
            "atenuacion_conexion_tu" => round((float)$getv("Atenuacion_Conexion_TU", 0.5), $FLOAT_PRECISION),

            "largo_cable_entre_pisos" => round((float)$getv("Largo_Cable_Entre_Pisos", 3), $FLOAT_PRECISION),
            "conectores_por_union" => (int)$getv("Conectores_Por_Union", 2),

            "largo_cable_amplificador_ultimo_piso" =>
                round((float)$getv("Largo_Cable_Amplificador_Ultimo_Piso", 0), $FLOAT_PRECISION),

            "largo_cable_feeder_bloque" =>
                round((float)$getv("Largo_Feeder_Bloque_m", 0), $FLOAT_PRECISION),
        ];

        // ----------------------------
        // Compute p_troncal EXACTLY like Excel
        // ----------------------------

        $params["p_troncal"] = (int)round($Piso_Maximo / 2);

        // ----------------------------
        // Tuple dictionaries
        // ----------------------------

        $tus_req = [];
        $largo_dr = [];
        $largo_tu = [];

        foreach ($apartments as $ap) {
            $p = (int)$ap['piso'];
            $a = (int)$ap['apartamento'];

            $tus_req["$p|$a"] =
                (int)$ap['tus_requeridos'];

            $largo_dr["$p|$a"] =
                round((float)$ap['largo_derivador'], $FLOAT_PRECISION);
        }

        foreach ($tus as $tu) {
            $p = (int)$tu['piso'];
            $a = (int)$tu['apartamento'];
            $idx = (int)$tu['tu_idx'];

            $largo_tu["$p|$a|$idx"] =
                round((float)$tu['longitud'], $FLOAT_PRECISION);
        }

        ksort($tus_req);
        ksort($largo_dr);
        ksort($largo_tu);

        $params["tus_requeridos_por_apartamento"] = $tus_req;
        $params["largo_cable_derivador_repartidor"] = $largo_dr;
        $params["largo_cable_tu"] = $largo_tu;

        // ----------------------------
        // Catalogs must also be injected
        // ----------------------------
        $params["derivadores_data"] =
            $_SESSION['derivadores_catalog'] ?? [];

        $params["repartidores_data"] =
            $_SESSION['repartidores_catalog'] ?? [];

        return $params;
    }


    public function runPython($dataset_id)
{
    $DB = new Database();
    $pdo = $DB->getConnection();

    /* ---------------------------------------------------------
       1) Decode POST canonical payload
    --------------------------------------------------------- */
    if (empty($_POST['canonical_payload'])) {
        throw new Exception("No canonical payload received.");
    }

    $canonical = json_decode($_POST['canonical_payload'], true);
    if (!is_array($canonical) || !isset($canonical['inputs'], $canonical['apartments'], $canonical['tus'])) {
        throw new Exception("Invalid canonical payload JSON.");
    }

    // [NEW] Inject tu_id into the canonical data structure immediately after decoding.
    // This ensures the data is complete before being used anywhere else (for canonical_json and dataset_rows).
    foreach ($canonical['tus'] as &$tu) { // Use reference to modify array in place
        if (!isset($tu['tu_id']) || empty($tu['tu_id'])) {
            if (isset($tu['piso'], $tu['apartamento'], $tu['tu_index'])) {
                $tu['tu_id'] = "P{$tu['piso']}A{$tu['apartamento']}TU{$tu['tu_index']}";
            } else {
                // Handle cases where essential keys are missing for ID generation
                throw new Exception("Cannot generate tu_id: A TU is missing 'piso', 'apartamento', or 'tu_index'.");
            }
        }
    }
    unset($tu); // Unset the reference after the loop

    /* ---------------------------------------------------------
       2) Save canonical JSON in datasets table
    --------------------------------------------------------- */
    /*$stmt = $pdo->prepare("
        UPDATE datasets SET canonical_json = :canonical WHERE dataset_id = :dataset_id
    ");
    $stmt->execute([
        'canonical' => json_encode($canonical, JSON_UNESCAPED_UNICODE),
        'dataset_id'        => $dataset_id
    ]);*/

    /* ---------------------------------------------------------
       3) Optional: populate legacy dataset_rows for backward compatibility
    --------------------------------------------------------- */
    $rowModel = new DatasetRow();
    $gpModel  = new GeneralParams();

    $pdo->beginTransaction();
    try {
        // Clear old rows
        $rowModel->deleteRowsByDataset($dataset_id);

        // Save general parameters
        $gpModel->saveForDataset($dataset_id, $canonical['inputs']);

        // Save apartment rows
        $record_index = 0;
        foreach ($canonical['apartments'] as $apt) {
            $rowModel->addRow($dataset_id, $record_index, "piso", $apt['piso'], "floor");
            $rowModel->addRow($dataset_id, $record_index, "apartamento", $apt['apartamento'], "apt");
            $rowModel->addRow($dataset_id, $record_index, "tus_requeridos", $apt['tus_requeridos'], "units");
            $rowModel->addRow($dataset_id, $record_index, "largo_cable_derivador", $apt['largo_cable_derivador'], "m");
            $rowModel->addRow($dataset_id, $record_index, "largo_cable_repartidor", $apt['largo_cable_repartidor'], "m");
            $record_index++;
        }

// Insert new TU rows from canonical payload
$tu_record_offset = 10000;

foreach ($canonical['tus'] as $i => $tu) {
    $tu_record_index = $tu_record_offset + $i;

    // Generate a canonical TU ID if not already present
    if (!isset($tu['tu_id']) || empty($tu['tu_id'])) {
        // Example format: "P{floor}A{apartment}TU{index}"
        $tu['tu_id'] = "P{$tu['piso']}A{$tu['apartamento']}TU{$tu['tu_index']}";
    }

    // Save all required canonical fields
    $rowModel->addRow($dataset_id, $tu_record_index, "tu_id", $tu['tu_id'], null);
    $rowModel->addRow($dataset_id, $tu_record_index, "piso", $tu['piso'], "floor");
    $rowModel->addRow($dataset_id, $tu_record_index, "apartamento", $tu['apartamento'], "apt");
    $rowModel->addRow($dataset_id, $tu_record_index, "tu_index", $tu['tu_index'], null);
    $rowModel->addRow($dataset_id, $tu_record_index, "largo_cable_tu", $tu['largo_cable_tu'], "m");
}

        $pdo->commit();
    } catch (\Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }

    /* ---------------------------------------------------------
       4) Call Python optimizer (reads canonical_json)
    --------------------------------------------------------- */
    $python_bin    = "/usr/bin/python3";
    $python_script = realpath(__DIR__ . "/../python/10/optimizer_db.py");

    putenv("OUTPUT_DIR=/var/tdt_outputs");

    $cmd = escapeshellcmd($python_bin) . " " . escapeshellarg($python_script) . " " . escapeshellarg((string)$dataset_id);
    $output = [];
    $return_var = 0;
    exec($cmd, $output, $return_var);

    if ($return_var !== 0) {
        throw new Exception("Python failed (rc={$return_var}): " . implode("\n", $output));
    }

    /* ---------------------------------------------------------
       5) Parse Python JSON output and check status
    --------------------------------------------------------- */
    $stdout = implode("\n", $output);
    $jsonStart = strpos($stdout, "{");
    if ($jsonStart === false) throw new Exception("Python did not return JSON.");

    $data = json_decode(substr($stdout, $jsonStart), true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception("Malformed JSON from Python: " . json_last_error_msg());
    }

    // --- Primary Status Gate ---
    // Handle non-OK outcomes first. This is the correct architectural pattern.
    $status = $data['status'] ?? null;

    if ($status === 'infeasible') {
        // This is a legitimate, expected outcome for some models.
        // We redirect to the results page with a special session flag
        // so the UI can render an 'infeasible' view.
        $_SESSION['optimization_result'] = [
            'status'     => 'infeasible',
            'opt_id'     => $data['opt_id'] ?? null,
            'dataset_id' => $data['dataset_id'] ?? null,
            'message'    => $data['message'] ?? 'Model infeasible'
        ];

        header("Location: /tdt-optimization/public/view_result.php?opt_id=" . ($data['opt_id'] ?? 0));
        exit;
    }

    if ($status === 'error') {
        throw new RuntimeException(
            "Optimization Error: " . ($data['message'] ?? 'The solver encountered an internal error.')
        );
    }

    // We only proceed if the status is explicitly 'success'.
    if ($status !== 'success') {
        throw new RuntimeException("Unknown or missing optimizer status. Expected 'success', 'infeasible', or 'error'.");
    }

    /* ---------------------------------------------------------
       6) Build STRICT canonical detail from Python output
    --------------------------------------------------------- */
    $rawDetail = $data['detail'] ?? [];
    $canonicalDetail = []; // Initialize canonicalDetail

    if (!empty($rawDetail)) {
        // Map Python flat rows (PascalCase) to a canonical structure (snake_case/lowercase)
        $builderInput = [];
        foreach ($rawDetail as $row) {
            $tu_id = $row['tu_id'] ?? $row['Toma'] ?? null;
            if (!$tu_id) {
                continue; // Or throw an exception if every row MUST have an ID
            }

            $losses = [];
            foreach ($row as $key => $value) {
                if (str_contains($key, '(dB)') && !str_contains($key, 'Nivel TU')) {
                    $segment = str_replace(['(dB)', 'Pérdida ', '→', '↔'], '', $key);
                    $segment = trim(preg_replace('/[^A-Za-z0-9\s-]/', '', $segment));
                    $losses[] = [
                        'segment' => strtolower(str_replace(' ', '_', $segment)),
                        'value'   => (float)$value
                    ];
                }
            }
            if (empty($losses)) {
                $losses[] = ['segment' => 'unknown', 'value' => 0];
            }

            $builderInput[] = [
                'tu_id'     => $tu_id,
                'piso'      => (int)($row['Piso'] ?? 0),
                'apto'      => (int)($row['Apto'] ?? 0),
                'bloque'    => (int)($row['Bloque'] ?? 0),
                'nivel_tu'  => (float)($row['Nivel TU Final (dBµV)'] ?? 0),
                'losses'    => $losses
            ];
        }
        
        // Get min/max levels from Python inputs
        $nivelMin = (float)($data['inputs']['Nivel_minimo'] ?? 48);
        $nivelMax = (float)($data['inputs']['Nivel_maximo'] ?? 69);
        if ($nivelMin >= $nivelMax) {
            throw new Exception("Invalid signal range from Python inputs.");
        }

        // Manually construct the final canonical detail, bypassing the faulty builder
        foreach($builderInput as $tu) {
            $nivel_tu = $tu['nivel_tu'];
            $tu['nivel_min'] = $nivelMin;
            $tu['nivel_max'] = $nivelMax;
            $tu['cumple'] = ($nivel_tu >= $nivelMin && $nivel_tu <= $nivelMax);
            $canonicalDetail[] = $tu;
        }
    }

    /* ---------------------------------------------------------
       7) Insert/Update results table
    --------------------------------------------------------- */
    $opt_id = intval($data['opt_id'] ?? 0);
    if (!$opt_id) {
        throw new RuntimeException("Python did not return opt_id.");
    }

    if (empty($data['summary']) || !is_array($data['summary'])) {
        throw new RuntimeException("Invalid or empty summary from optimizer.");
    }

    if (empty($data['inputs']) || !is_array($data['inputs'])) {
        throw new RuntimeException("Invalid or empty inputs from optimizer.");
    }

    if (empty($canonicalDetail) || !is_array($canonicalDetail)) {
        throw new RuntimeException("Optimizer returned zero TU results.");
    }

    if (count($canonicalDetail) === 0) {
        throw new RuntimeException("No TU results produced.");
    }

    if (
        isset($data['summary']['tu_total']) &&
        $data['summary']['tu_total'] !== count($canonicalDetail)
    ) {
        throw new RuntimeException("Mismatch between summary TU count and detail rows.");
    }

    $summaryJson = json_encode($data['summary'], JSON_UNESCAPED_UNICODE);
    $detailJson  = json_encode($canonicalDetail, JSON_UNESCAPED_UNICODE); // Use the builder's output
    $inputsJson  = json_encode($data['inputs'], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);

    error_log("Canonical TUs count: " . count($canonicalDetail));

    $insert = $pdo->prepare("
        INSERT INTO results (opt_id, summary_json, detail_json, inputs_json)
        VALUES (:opt_id, :summary, :detail, :inputs)
        ON DUPLICATE KEY UPDATE
            summary_json = :summary,
            detail_json  = :detail,
            inputs_json  = :inputs
    ");
    $insert->execute([
        ':opt_id' => $opt_id,
        ':summary'=> $summaryJson,
        ':detail' => $detailJson,
        ':inputs' => $inputsJson,
    ]);

    /* ---------------------------------------------------------
       7) Redirect to results UI
    --------------------------------------------------------- */
    header("Location: ../../public/results.php?opt_id=" . $opt_id);
    exit();
}


    

}

$controller = new DatasetController();
$controller->handleRequest();



?>