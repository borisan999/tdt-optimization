<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . "/../config/db.php";
require_once __DIR__ . "/../models/Dataset.php";
require_once __DIR__ . "/../models/DatasetRow.php";
require_once __DIR__ . "/../config/env.php";

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

            default:
                $this->manualEntryForm();
                break;
        }
    }

    private function uploadExcel()
    {
        // Not implemented yet
        die("Excel upload not yet implemented");
    }

    /**
     * -----------------------------------------
     * MANUAL ENTRY (Insert dataset + rows)
     * -----------------------------------------
     */
    private function manualEntry()
    {
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
        unset($_SESSION['loaded_dataset']);
        unset($_SESSION['loaded_dataset_id']);
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

        header("Location: ../../public/enter_data.php?loaded=1");
        exit;
    }

    public function manualEntryForm()
    {
        include __DIR__ . "/../../public/enter_data.php";
    }
}

$controller = new DatasetController();
$controller->handleRequest();

