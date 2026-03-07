<?php
require_once __DIR__ . "/../app/config/db.php";
require_once __DIR__ . "/../vendor/autoload.php";
require_once __DIR__ . "/../app/auth/require_login.php";
require_once __DIR__ . "/../app/controllers/ResultsController.php";

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use app\helpers\InventoryAggregator;
use app\controllers\ResultsController;
use app\helpers\ResultParser;

$opt_id = $_GET['opt_id'] ?? null;
if (!$opt_id) {
    die('Missing opt_id');
}

$db = new Database();
$pdo = $db->getConnection();
ensureResultAccess((int)$opt_id, $pdo);

// --------------------------------------------------
// 1. Load result via Controller
// --------------------------------------------------
$controller = new ResultsController((int)$opt_id);
$response = $controller->execute();

if ($response['status'] !== 'success') {
    die('Error loading result: ' . ($response['message'] ?? 'Unknown error'));
}

/** @var \app\viewmodels\ResultViewModel $viewModel */
$viewModel = $response['viewModel'];

$dataset_name = $viewModel->dataset_name ?? 'Unnamed';
$safe_name = preg_replace('/[^a-zA-Z0-9_\-]/', '_', $dataset_name);

$detail  = $viewModel->details; 
$summary = $viewModel->summary;
$inputs  = $viewModel->inputs;

// Re-fetch raw row for legacy columns if needed
$raw_detail = json_decode($viewModel->results['detail_json'], true);

// Try to resolve global input level
$P_IN = (float)($inputs['potencia_entrada'] ?? 0);
if ($P_IN <= 0 && isset($raw_detail[0]['P_in (entrada) (dBµV)'])) {
    $P_IN = (float)$raw_detail[0]['P_in (entrada) (dBµV)'];
}

if ($P_IN <= 0) {
    die('Missing global P_in (entrada) in inputs or detail_json');
}

// --------------------------------------------------
// 2. Prepare Categorized Inventory
// --------------------------------------------------
$parser = ResultParser::fromDbRow($viewModel->results);
$canonical = $parser->canonical();

if (empty($canonical) || !isset($canonical['vertical_distribution']) || !isset($canonical['floors'])) {
    die('Canonical data not available or invalid for inventory export.');
}

$aggregator = new InventoryAggregator($canonical);
$aggregatedData = $aggregator->aggregate();
$categorizedInventory = $aggregatedData['inventory'];
$allTotals = $aggregatedData['totals'];

// --------------------------------------------------
// 3. Spreadsheet initialization
// --------------------------------------------------
$spreadsheet = new Spreadsheet();

// --- SHEET 1: Detalle_Tomas ---
$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle(__('xls_sheet_detail'));

$DETALLE_TOMAS_COLUMNS = [
    'Toma', 'Piso', 'Apto', 'Bloque', 'Piso Troncal', 'Piso Entrada Riser Bloque',
    'Direccion Propagacion', 'Longitud Antena→Troncal (m)', 'Pérdida Antena→Troncal (cable) (dB)',
    'Pérdida Antena↔Troncal (conectores) (dB)', 'Repartidor Troncal', 'Salidas Troncal',
    'Pérdida Repartidor Troncal (dB)', 'Feeder Troncal→Entrada Bloque (m)',
    'Pérdida Feeder (cable) (dB)', 'Pérdida Feeder (conectores) (dB)',
    'Pérdida Riser dentro del Bloque (dB)', 'Distancia riser dentro bloque (m)',
    'Riser Atenuacion Cable (dB)', 'Riser Conectores (uds)', 'Riser Atenuacion Conectores (dB)',
    'Riser Atenuación Taps (dB)', 'Derivador Piso', 'Pérdida Derivador Piso (dB)',
    'Pérdida Cable Deriv→Rep (dB)', 'Pérdida Conectores Apto (dB)', 'Repartidor Apt',
    'Pérdida Repartidor Apt (dB)', 'Pérdida Cable Rep→TU (dB)', 'Pérdida Conexión TU (dB)',
    'Pérdida Total (dB)', 'P_in (entrada) (dBµV)', 'Nivel TU Final (dBµV)',
    'Distancia total hasta la toma (m)'
];

$col = 'A';
foreach ($DETALLE_TOMAS_COLUMNS as $header) {
    $sheet->setCellValue($col . '1', $header);
    $col++;
}

$rowNum = 2;
foreach ($raw_detail as $tu) {
    if (!isset($tu['P_in (entrada) (dBµV)'])) {
        $tu['P_in (entrada) (dBµV)'] = $P_IN;
    }
    $col = 'A';
    foreach ($DETALLE_TOMAS_COLUMNS as $colName) {
        $value = $tu[$colName] ?? '';
        $sheet->setCellValueExplicit($col . $rowNum, $value, is_numeric($value) ? DataType::TYPE_NUMERIC : DataType::TYPE_STRING);
        $col++;
    }
    $rowNum++;
}

// --- SHEET 2: Vertical Distribution ---
$verticalSheet = $spreadsheet->createSheet();
$verticalSheet->setTitle(__('xls_sheet_vertical'));
$verticalSheet->fromArray([__('xls_col_scope'), __('xls_col_type'), __('xls_col_component'), __('xls_col_unit'), __('xls_col_quantity'), __('xls_col_obs')], null, 'A1');
$row = 2;
foreach ($categorizedInventory['Vertical Distribution'] as $item) {
    $verticalSheet->setCellValue('A' . $row, $item['Scope']);
    $verticalSheet->setCellValue('B' . $row, $item['Tipo']);
    $verticalSheet->setCellValue('C' . $row, $item['Componente']);
    $verticalSheet->setCellValue('D' . $row, $item['Unidad']);
    $verticalSheet->setCellValue('E' . $row, $item['Cantidad']);
    $verticalSheet->setCellValue('F' . $row, $item['Observación']);
    $row++;
}
$row++;
$totalRowStart = $row;
foreach ($allTotals['Vertical Distribution'] as $totalItem) {
    $verticalSheet->setCellValue('A' . $row, __('xls_total'));
    $verticalSheet->setCellValue('B' . $row, __('xls_dist_vertical'));
    $verticalSheet->setCellValue('C' . $row, $totalItem['Tipo']);
    $verticalSheet->setCellValue('D' . $row, $totalItem['Componente']);
    $verticalSheet->setCellValue('E' . $row, $totalItem['Unidad']);
    $verticalSheet->setCellValue('F' . $row, $totalItem['Cantidad']);
    $row++;
}
$verticalSheet->getStyle('A' . $totalRowStart . ':F' . ($row - 1))->getFont()->setBold(true);

// --- SHEET 3: Horizontal Distribution ---
$horizontalSheet = $spreadsheet->createSheet();
$horizontalSheet->setTitle(__('xls_sheet_horizontal'));
$horizontalSheet->fromArray([__('xls_col_piso'), __('xls_col_scope'), __('xls_col_type'), __('xls_col_component'), __('xls_col_unit'), __('xls_col_quantity'), __('xls_col_obs')], null, 'A1');
$row = 2;
ksort($categorizedInventory['Horizontal Distribution']);
foreach ($categorizedInventory['Horizontal Distribution'] as $piso => $items) {
    foreach ($items as $item) {
        $horizontalSheet->setCellValue('A' . $row, $piso);
        $horizontalSheet->setCellValue('B' . $row, $item['Scope']);
        $horizontalSheet->setCellValue('C' . $row, $item['Tipo']);
        $horizontalSheet->setCellValue('D' . $row, $item['Componente']);
        $horizontalSheet->setCellValue('E' . $row, $item['Unidad']);
        $horizontalSheet->setCellValue('F' . $row, $item['Cantidad']);
        $horizontalSheet->setCellValue('G' . $row, $item['Observación']);
        $row++;
    }
    $row++;
    $subtotalRowStart = $row;
    if (isset($allTotals['Horizontal Floor Subtotals'][$piso])) {
        foreach ($allTotals['Horizontal Floor Subtotals'][$piso] as $totalItem) {
            $horizontalSheet->setCellValue('A' . $row, __('xls_subtotal', ['piso' => $piso]));
            $horizontalSheet->setCellValue('B' . $row, $totalItem['Scope']);
            $horizontalSheet->setCellValue('C' . $row, $totalItem['Tipo']);
            $horizontalSheet->setCellValue('D' . $row, $totalItem['Componente']);
            $horizontalSheet->setCellValue('E' . $row, $totalItem['Unidad']);
            $horizontalSheet->setCellValue('F' . $row, $totalItem['Cantidad']);
            $row++;
        }
    }
    $horizontalSheet->getStyle('A' . $subtotalRowStart . ':G' . ($row - 1))->getFont()->setBold(true);
    $row++;
}
$row++;
$globalTotalRowStart = $row;
foreach ($allTotals['Horizontal Distribution'] as $totalItem) {
    $horizontalSheet->setCellValue('A' . $row, __('xls_total'));
    $horizontalSheet->setCellValue('B' . $row, __('xls_dist_horizontal'));
    $horizontalSheet->setCellValue('C' . $row, $totalItem['Tipo']);
    $horizontalSheet->setCellValue('D' . $row, $totalItem['Componente']);
    $horizontalSheet->setCellValue('E' . $row, $totalItem['Unidad']);
    $horizontalSheet->setCellValue('F' . $row, $totalItem['Cantidad']);
    $row++;
}
$horizontalSheet->getStyle('A' . $globalTotalRowStart . ':G' . ($row - 1))->getFont()->setBold(true);

// --- SHEET 4: Apartment Interior ---
$apartmentSheet = $spreadsheet->createSheet();
$apartmentSheet->setTitle(__('xls_sheet_apartment'));
$apartmentSheet->fromArray([__('xls_col_piso'), __('xls_col_apto'), __('xls_col_scope'), __('xls_col_type'), __('xls_col_component'), __('xls_col_unit'), __('xls_col_quantity'), __('xls_col_obs')], null, 'A1');
$row = 2;
ksort($categorizedInventory['Apartment Interior']);
foreach ($categorizedInventory['Apartment Interior'] as $piso => $apts) {
    ksort($apts);
    foreach ($apts as $apto => $items) {
        foreach ($items as $item) {
            $apartmentSheet->setCellValue('A' . $row, $piso);
            $apartmentSheet->setCellValue('B' . $row, $apto);
            $apartmentSheet->setCellValue('C' . $row, $item['Scope']);
            $apartmentSheet->setCellValue('D' . $row, $item['Tipo']);
            $apartmentSheet->setCellValue('E' . $row, $item['Componente']);
            $apartmentSheet->setCellValue('F' . $row, $item['Unidad']);
            $apartmentSheet->setCellValue('G' . $row, $item['Cantidad']);
            $apartmentSheet->setCellValue('H' . $row, $item['Observación']);
            $row++;
        }
    }
}
$row++;
$globalTotalRowStart = $row;
foreach ($allTotals['Apartment Interior'] as $totalItem) {
    $apartmentSheet->setCellValue('A' . $row, __('xls_total'));
    $apartmentSheet->setCellValue('B' . $row, __('xls_dist_apartment'));
    $apartmentSheet->setCellValue('C' . $row, $totalItem['Tipo']);
    $apartmentSheet->setCellValue('D' . $row, $totalItem['Componente']);
    $apartmentSheet->setCellValue('E' . $row, $totalItem['Unidad']);
    $apartmentSheet->setCellValue('F' . $row, $totalItem['Cantidad']);
    $row++;
}
$apartmentSheet->getStyle('A' . $globalTotalRowStart . ':H' . ($row - 1))->getFont()->setBold(true);

// --- SHEET 5: Grand Total ---
$grandTotalSheet = $spreadsheet->createSheet();
$grandTotalSheet->setTitle(__('xls_sheet_grand_total'));
$grandTotalSheet->fromArray([__('xls_col_scope'), __('xls_col_type'), __('xls_col_component'), __('xls_col_unit'), __('xls_col_quantity')], null, 'A1');
$row = 2;
foreach ($allTotals['Grand Total'] as $totalItem) {
    $grandTotalSheet->setCellValue('A' . $row, $totalItem['Scope']);
    $grandTotalSheet->setCellValue('B' . $row, $totalItem['Tipo']);
    $grandTotalSheet->setCellValue('C' . $row, $totalItem['Componente']);
    $grandTotalSheet->setCellValue('D' . $row, $totalItem['Unidad']);
    $grandTotalSheet->setCellValue('E' . $row, $totalItem['Cantidad']);
    $row++;
}
$grandTotalSheet->getStyle('A2:E' . ($row - 1))->getFont()->setBold(true);

// --- SHEET 6: Resumen_Ingenieria ---
$summarySheet = $spreadsheet->createSheet();
$summarySheet->setTitle(__('xls_sheet_summary'));

$min = $summary["min_nivel_tu"] ?? $summary["min_level"] ?? 0;
$max = $summary["max_nivel_tu"] ?? $summary["max_level"] ?? 0;
$avg = $summary["avg_nivel_tu"] ?? $summary["avg_level"] ?? 0;
$num = $summary["total_tus"] ?? $summary["num_tomas"] ?? 0;
$statusStr = ($min >= 47 && $max <= 70) ? __('xls_pass') : __('xls_fail');

$summarySheet->setCellValue("A1", __('xls_summary_title'));
$summarySheet->setCellValue("A3", __('xls_num_tomas'));
$summarySheet->setCellValue("B3", $num);
$summarySheet->setCellValue("A4", __('xls_min_level'));
$summarySheet->setCellValue("B4", round($min, 2));
$summarySheet->setCellValue("A5", __('xls_max_level'));
$summarySheet->setCellValue("B5", round($max, 2));
$summarySheet->setCellValue("A6", __('xls_avg_level'));
$summarySheet->setCellValue("B6", round($avg, 2));
$summarySheet->setCellValue("A7", __('xls_input_level'));
$summarySheet->setCellValue("B7", $P_IN);
$summarySheet->setCellValue("A9", __('xls_overall_status'));
$summarySheet->setCellValue("B9", $statusStr);

$summarySheet->getStyle("A1")->getFont()->setBold(true)->setSize(14);
$summarySheet->getStyle("A3:A9")->getFont()->setBold(true);
$summarySheet->getStyle("A3:B9")->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
$summarySheet->getStyle("B9")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB($statusStr === __('xls_pass') ? "C6EFCE" : "FFC7CE");
$summarySheet->getStyle("B9")->getFont()->setBold(true);

foreach (["A", "B"] as $col) { $summarySheet->getColumnDimension($col)->setAutoSize(true); }

$spreadsheet->setActiveSheetIndex(0);

// --------------------------------------------------
// 4. Output
// --------------------------------------------------
$filename = "tdt_export_{$safe_name}_opt_{$opt_id}.xlsx";
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: max-age=0');

$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit;
