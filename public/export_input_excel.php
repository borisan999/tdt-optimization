<?php
/**
 * public/export_input_excel.php
 * "Close Loop" Export: Produces an Excel file that exactly matches the structure 
 * required by ExcelProcessor.php for re-uploading.
 */

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../app/config/db.php';
require_once __DIR__ . '/../app/config/AppConfig.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use App\Config\AppConfig;

try {
    // 1. Get opt_id and fetch results
    $opt_id = intval($_GET['opt_id'] ?? 0);
    if ($opt_id <= 0) {
        http_response_code(400);
        die('opt_id is required');
    }

    $DB = new Database();
    $pdo = $DB->getConnection();

    // We fetch inputs_json which contains the canonical dataset
    $sql = "
        SELECT r.inputs_json, d.dataset_name 
        FROM results r 
        JOIN datasets d ON d.dataset_id = r.dataset_id
        WHERE r.opt_id = :opt_id
    ";
    $st = $pdo->prepare($sql);
    $st->execute(['opt_id' => $opt_id]);
    $row = $st->fetch(PDO::FETCH_ASSOC);

    if (!$row || empty($row['inputs_json'])) {
        http_response_code(404);
        die('Result not found or inputs_json is empty');
    }

    $inputs = json_decode($row['inputs_json'], true);
    $dataset_name = $row['dataset_name'] ?? 'Unnamed';
    $safe_name = preg_replace('/[^a-zA-Z0-9_\-]/', '_', $dataset_name);

    if (json_last_error() !== JSON_ERROR_NONE) {
        http_response_code(500);
        die('Invalid inputs_json data');
    }

    // 2. Create Spreadsheet
    $spreadsheet = new Spreadsheet();
    $spreadsheet->removeSheetByIndex(0); // Remove default sheet

    /* =====================================================
    1️⃣ Parametros_Generales
    ====================================================== */
    $sheet = $spreadsheet->createSheet();
    $sheet->setTitle(AppConfig::SHEET_PARAMS);
    $sheet->setCellValue('A1', 'Key');
    $sheet->setCellValue('B1', 'Valor');

    $param_mapping = [
        'Piso_Maximo'                        => 'Piso_Maximo',
        'apartamentos_por_piso'              => 'Apartamentos_Piso',
        'atenuacion_cable_por_metro'         => 'Atenuacion_Cable_dBporM',
        'atenuacion_cable_470mhz'            => 'Atenuacion_Cable_470MHz_dBporM',
        'atenuacion_cable_698mhz'            => 'Atenuacion_Cable_698MHz_dBporM',
        'atenuacion_conector'                => 'Atenuacion_Conector_dB',
        'largo_cable_entre_pisos'            => 'Largo_Entre_Pisos_m',
        'potencia_entrada'                   => 'Potencia_Entrada_dBuV',
        'Nivel_minimo'                       => 'Nivel_Minimo_dBuV',
        'Nivel_maximo'                       => 'Nivel_Maximo_dBuV',
        'Potencia_Objetivo_TU'               => 'Potencia_Objetivo_TU_dBuV',
        'conectores_por_union'               => 'Conectores_por_Union',
        'atenuacion_conexion_tu'             => 'Atenuacion_Conexion_TU_dB',
        'largo_cable_amplificador_ultimo_piso' => 'Largo_Cable_Amplificador_Ultimo_Piso',
        'largo_cable_feeder_bloque'          => 'Largo_Feeder_Bloque_m (Mínimo)',
    ];

    $row = 2;
    foreach ($param_mapping as $canonical_key => $excel_key) {
        if (isset($inputs[$canonical_key])) {
            $sheet->setCellValue('A' . $row, $excel_key);
            $sheet->setCellValue('B' . $row, $inputs[$canonical_key]);
            $row++;
        }
    }

    /* =====================================================
    2️⃣ largo_cable_derivador_repartido
    ====================================================== */
    $sheet = $spreadsheet->createSheet();
    $sheet->setTitle(AppConfig::SHEET_LC_DR);
    $sheet->setCellValue('A1', 'Piso');
    $sheet->setCellValue('B1', 'Apto');
    $sheet->setCellValue('C1', 'Longitud_m');

    if (isset($inputs['largo_cable_derivador_repartidor']) && is_array($inputs['largo_cable_derivador_repartidor'])) {
        $row = 2;
        foreach ($inputs['largo_cable_derivador_repartidor'] as $key => $val) {
            $parts = explode('|', $key);
            if (count($parts) === 2) {
                $sheet->setCellValue('A' . $row, $parts[0]);
                $sheet->setCellValue('B' . $row, $parts[1]);
                $sheet->setCellValue('C' . $row, $val);
                $row++;
            }
        }
    }

    /* =====================================================
    3️⃣ tus_requeridos_por_apartamento
    ====================================================== */
    $sheet = $spreadsheet->createSheet();
    $sheet->setTitle(AppConfig::SHEET_TUS_REQ);
    $sheet->setCellValue('A1', 'Piso');
    $sheet->setCellValue('B1', 'Apto');
    $sheet->setCellValue('C1', 'Cantidad_Tomas');

    if (isset($inputs['tus_requeridos_por_apartamento']) && is_array($inputs['tus_requeridos_por_apartamento'])) {
        $row = 2;
        foreach ($inputs['tus_requeridos_por_apartamento'] as $key => $val) {
            $parts = explode('|', $key);
            if (count($parts) === 2) {
                $sheet->setCellValue('A' . $row, $parts[0]);
                $sheet->setCellValue('B' . $row, $parts[1]);
                $sheet->setCellValue('C' . $row, $val);
                $row++;
            }
        }
    }

    /* =====================================================
    4️⃣ largo_cable_tu
    ====================================================== */
    $sheet = $spreadsheet->createSheet();
    $sheet->setTitle(AppConfig::SHEET_LC_TU);
    $sheet->setCellValue('A1', 'Piso');
    $sheet->setCellValue('B1', 'Apto');
    $sheet->setCellValue('C1', 'TU Index');
    $sheet->setCellValue('D1', 'Longitud_m');

    if (isset($inputs['largo_cable_tu']) && is_array($inputs['largo_cable_tu'])) {
        $row = 2;
        foreach ($inputs['largo_cable_tu'] as $key => $val) {
            $parts = explode('|', $key);
            if (count($parts) === 3) {
                $sheet->setCellValue('A' . $row, $parts[0]);
                $sheet->setCellValue('B' . $row, $parts[1]);
                $sheet->setCellValue('C' . $row, $parts[2]);
                $sheet->setCellValue('D' . $row, $val);
                $row++;
            }
        }
    }

    /* =====================================================
    5️⃣ derivadores_data
    ====================================================== */
    $sheet = $spreadsheet->createSheet();
    $sheet->setTitle(AppConfig::SHEET_DERIVADORES);
    $sheet->setCellValue('A1', 'Modelo');
    $sheet->setCellValue('B1', 'derivacion');
    $sheet->setCellValue('C1', 'paso');
    $sheet->setCellValue('D1', 'salidas');

    if (isset($inputs['derivadores_data']) && is_array($inputs['derivadores_data'])) {
        $row = 2;
        foreach ($inputs['derivadores_data'] as $model => $specs) {
            $sheet->setCellValue('A' . $row, $model);
            $sheet->setCellValue('B' . $row, $specs['derivacion'] ?? 0);
            $sheet->setCellValue('C' . $row, $specs['paso'] ?? 0);
            $sheet->setCellValue('D' . $row, $specs['salidas'] ?? 0);
            $row++;
        }
    }

    /* =====================================================
    6️⃣ repartidores_data
    ====================================================== */
    $sheet = $spreadsheet->createSheet();
    $sheet->setTitle(AppConfig::SHEET_REPARTIDORES);
    $sheet->setCellValue('A1', 'Modelo');
    $sheet->setCellValue('B1', 'perdida_insercion');
    $sheet->setCellValue('C1', 'salidas');

    if (isset($inputs['repartidores_data']) && is_array($inputs['repartidores_data'])) {
        $row = 2;
        foreach ($inputs['repartidores_data'] as $model => $specs) {
            $sheet->setCellValue('A' . $row, $model);
            $sheet->setCellValue('B' . $row, $specs['perdida_insercion'] ?? 0);
            $sheet->setCellValue('C' . $row, $specs['salidas'] ?? 0);
            $row++;
        }
    }

    // 3. Finalize and Download
    $spreadsheet->setActiveSheetIndex(0);
    $filename = "tdt_inputs_{$safe_name}_opt_{$opt_id}.xlsx";

    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment;filename="' . $filename . '"');
    header('Cache-Control: max-age=0');

    $writer = new Xlsx($spreadsheet);
    $writer->save('php://output');
    exit;

} catch (\Throwable $e) {
    http_response_code(500);
    header('Content-Type: text/plain; charset=utf-8');
    echo "Error generating Golden Excel: " . $e->getMessage();
    exit;
}
