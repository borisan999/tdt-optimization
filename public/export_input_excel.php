<?php

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../app/config/db.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

try {
    // 1. Get opt_id and fetch results
    $opt_id = intval($_GET['opt_id'] ?? 0);
    if ($opt_id <= 0) {
        http_response_code(400);
        die('opt_id is required');
    }

    $DB = new Database();
    $pdo = $DB->getConnection();

    $sql = "SELECT inputs_json FROM results WHERE opt_id = :opt_id";
    $st = $pdo->prepare($sql);
    $st->execute(['opt_id' => $opt_id]);
    $row = $st->fetch(PDO::FETCH_ASSOC);

    if (!$row || empty($row['inputs_json'])) {
        http_response_code(404);
        die('Result not found or inputs_json is empty');
    }

    $inputs = json_decode($row['inputs_json'], true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        http_response_code(500);
        die('Invalid inputs_json data');
    }

    // 2. Create a new Spreadsheet object
    $spreadsheet = new Spreadsheet();

    // Helper to parse keys like "(1, 2, 3)"
    if (!function_exists('parseKey')) {
        function parseKey(string $key): array {
            $cleaned = preg_replace('/[()\s]/', '', $key);
            return array_map('intval', explode(',', $cleaned));
        }
    }

    // 3. Populate "parametros_generales" sheet
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->setTitle('parametros_generales');
    $sheet->setCellValue('A1', 'name');
    $sheet->setCellValue('B1', 'value');

    // Map internal python keys to the required template keys
    $key_map = [
        'Piso_Maximo'                        => 'Piso_Maximo',
        'apartamentos_por_piso'              => 'Apartamentos_Piso',
        'largo_cable_amplificador_ultimo_piso' => 'Largo_Cable_Amplificador_Ultimo_Piso',
        'potencia_entrada'                   => 'Potencia_Entrada_dBuV',
        'Nivel_minimo'                       => 'Nivel_Minimo_dBuV',
        'Nivel_maximo'                       => 'Nivel_Maximo_dBuV',
        'Potencia_Objetivo_TU'               => 'Potencia_Objetivo_TU_dBuV',
        'largo_cable_feeder_bloque'          => 'Largo_Feeder_Bloque_m',
        'atenuacion_cable_por_metro'         => 'Atenuacion_Cable_dBporM',
        'atenuacion_conector'                => 'Atenuacion_Conector_dB',
        'atenuacion_conexion_tu'             => 'Atenuacion_Conexion_TU_dB',
        'largo_cable_entre_pisos'            => 'Largo_Entre_Pisos_m',
        'conectores_por_union'               => 'Conectores_por_Union',
    ];

    $rowIndex = 2;
    foreach ($key_map as $json_key => $template_key) {
        if (isset($inputs[$json_key])) {
            $sheet->setCellValue('A' . $rowIndex, $template_key);
            $sheet->setCellValue('B' . $rowIndex, $inputs[$json_key]);
            $rowIndex++;
        }
    }

    // 4. Populate "apartamentos" sheet
    $apartamentosSheet = $spreadsheet->createSheet();
    $apartamentosSheet->setTitle('apartamentos');
    $apartamentosSheet->setCellValue('A1', 'piso');
    $apartamentosSheet->setCellValue('B1', 'apartamento');
    $apartamentosSheet->setCellValue('C1', 'tus_requeridos');
    $apartamentosSheet->setCellValue('D1', 'largo_cable_derivador');
    $apartamentosSheet->setCellValue('E1', 'largo_cable_repartidor');

    if (isset($inputs['tus_requeridos_por_apartamento']) && is_array($inputs['tus_requeridos_por_apartamento'])) {
        $rowIndex = 2;
        foreach ($inputs['tus_requeridos_por_apartamento'] as $key => $tusRequeridos) {
            list($piso, $apto) = parseKey($key);
            
            $largo_derivador = $inputs['largo_cable_derivador'][$key] ?? '';
            $largo_repartidor = $inputs['largo_cable_repartidor'][$key] ?? '';

            $apartamentosSheet->setCellValue('A' . $rowIndex, $piso);
            $apartamentosSheet->setCellValue('B' . $rowIndex, $apto);
            $apartamentosSheet->setCellValue('C' . $rowIndex, $tusRequeridos);
            $apartamentosSheet->setCellValue('D' . $rowIndex, $largo_derivador);
            $apartamentosSheet->setCellValue('E' . $rowIndex, $largo_repartidor);
            $rowIndex++;
        }
    }

    // 5. Populate "tu" sheet
    $tuSheet = $spreadsheet->createSheet();
    $tuSheet->setTitle('tu');
    $tuSheet->setCellValue('A1', 'piso');
    $tuSheet->setCellValue('B1', 'apartamento');
    $tuSheet->setCellValue('C1', 'tu_index');
    $tuSheet->setCellValue('D1', 'largo_cable_tu');

    if (isset($inputs['largo_cable_tu']) && is_array($inputs['largo_cable_tu'])) {
        $rowIndex = 2;
        foreach ($inputs['largo_cable_tu'] as $key => $largo) {
            list($piso, $apto, $tuIndex) = parseKey($key);

            $tuSheet->setCellValue('A' . $rowIndex, $piso);
            $tuSheet->setCellValue('B' . $rowIndex, $apto);
            $tuSheet->setCellValue('C' . $rowIndex, $tuIndex);
            $tuSheet->setCellValue('D' . $rowIndex, $largo);
            $rowIndex++;
        }
    }

    // 6. Set active sheet back to the first sheet
    $spreadsheet->setActiveSheetIndex(0);

    // 7. Stream the file to the browser
    $filename = "tdt_inputs_opt_{$opt_id}.xlsx";
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment;filename="' . $filename . '"');
    header('Cache-Control: max-age=0');

    $writer = new Xlsx($spreadsheet);
    $writer->save('php://output');

    exit;

} catch (\Throwable $e) {
    http_response_code(500);
    header('Content-Type: text/plain; charset=utf-8');
    echo "A critical error occurred while generating the Excel file:\n\n";
    echo "Message: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . "\n";
    echo "Line: " . $e->getLine() . "\n";
    echo "\nTrace:\n" . $e->getTraceAsString();
    exit;
}