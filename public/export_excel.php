<?php
require_once __DIR__ . "/../app/config/db.php";
require_once __DIR__ . "/../vendor/autoload.php";
require_once __DIR__ . "/../app/helpers/InventoryAggregator.php";
require_once __DIR__ . "/../app/helpers/ResultParser.php"; // Add this
require_once __DIR__ . "/../app/services/CanonicalMapperService.php"; // Add this

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use app\helpers\InventoryAggregator;
use app\helpers\ResultParser; // Add this


$opt_id = $_GET['opt_id'] ?? null;
if (!$opt_id) {
    die('Missing opt_id');
}

// --------------------------------------------------
// 1. Load result row
// --------------------------------------------------
$db  = new Database();
$pdo = $db->getConnection();

$stmt = $pdo->prepare("SELECT summary_json, detail_json FROM results WHERE opt_id = :id");
$stmt->execute([':id' => $opt_id]);
$result = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$result) {
    die('Optimization result not found');
}

$summary = json_decode($result['summary_json'], true);
$parser = ResultParser::fromDbRow($result); // Pass the entire row
if ($parser->hasErrors()) {
    die('Error parsing result: ' . implode(', ', $parser->errors()));
}
$canonical = $parser->canonical();
$detail    = $parser->details(); // Keep legacy detail for non-inventory parts of the report for now
$inputs    = $parser->inputs();   // Keep legacy inputs for other parts


if (!is_array($detail)) { // Still check legacy detail if used elsewhere
    die('Invalid detail_json format');
}

// --------------------------------------------------
// Try to resolve global input level from canonical, fallback to legacy summary/detail
if (isset($canonical['global_parameters']['input_power_dbuv'])) {
    $P_IN = (float)$canonical['global_parameters']['input_power_dbuv'];
} elseif (isset($summary['input_level'])) { // Legacy fallback
    $P_IN = (float)$summary['input_level'];
} elseif (isset($summary['P_in (entrada) (dBµV)'])) { // Legacy fallback
    $P_IN = (float)$summary['P_in (entrada) (dBµV)'];
} elseif (isset($detail[0]['P_in (entrada) (dBµV)'])) { // Legacy fallback
    $P_IN = (float)$detail[0]['P_in (entrada) (dBµV)'];
} else {
    die('Missing global P_in (entrada) in canonical, summary_json, and detail_json');
}

// --------------------------------------------------
// 3. Canonical column definition (IMMUTABLE)
// --------------------------------------------------
$DETALLE_TOMAS_COLUMNS = [
    'Toma',
    'Piso',
    'Apto',
    'Bloque',
    'Piso Troncal',
    'Piso Entrada Riser Bloque',
    'Direccion Propagacion',
    'Longitud Antena→Troncal (m)',
    'Pérdida Antena→Troncal (cable) (dB)',
    'Pérdida Antena↔Troncal (conectores) (dB)',
    'Repartidor Troncal',
    'Salidas Troncal',
    'Pérdida Repartidor Troncal (dB)',
    'Feeder Troncal→Entrada Bloque (m)',
    'Pérdida Feeder (cable) (dB)',
    'Pérdida Feeder (conectores) (dB)',
    'Pérdida Riser dentro del Bloque (dB)',
    'Distancia riser dentro bloque (m)',
    'Riser Atenuacion Cable (dB)',
    'Riser Conectores (uds)',
    'Riser Atenuacion Conectores (dB)',
    'Riser Atenuación Taps (dB)',
    'Derivador Piso',
    'Pérdida Derivador Piso (dB)',
    'Pérdida Cable Deriv→Rep (dB)',
    'Pérdida Conectores Apto (dB)',
    'Repartidor Apt',
    'Pérdida Repartidor Apt (dB)',
    'Pérdida Cable Rep→TU (dB)',
    'Pérdida Conexión TU (dB)',
    'Pérdida Total (dB)',
    'P_in (entrada) (dBµV)',
    'Nivel TU Final (dBµV)',
    'Distancia total hasta la toma (m)'
];

// --------------------------------------------------
// Prepare Categorized Inventory
// --------------------------------------------------
// Check if canonical data is available and valid for inventory aggregation
if (empty($canonical) || !isset($canonical['vertical_distribution']) || !isset($canonical['floors'])) {
    die('Canonical data not available or invalid for inventory export.');
}
$aggregator = new InventoryAggregator($canonical); // Pass canonical data
$aggregatedData = $aggregator->aggregate();
$categorizedInventory = $aggregatedData['inventory'];
$allTotals = $aggregatedData['totals'];

// --------------------------------------------------
// 4. Spreadsheet initialization
// --------------------------------------------------
$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle('Detalle_Tomas');



// Headers
$col = 'A';
foreach ($DETALLE_TOMAS_COLUMNS as $header) {
    $sheet->setCellValue($col . '1', $header);
    $col++;
}

// --------------------------------------------------
// 5. Row writer with strict validation
// --------------------------------------------------
$rowNum = 2;

foreach ($detail as $tu) {

    // --- Mandatory engineering validation ---
    if (!isset($tu['P_in (entrada) (dBµV)'])) {
        $tu['P_in (entrada) (dBµV)'] = $P_IN;
    }

    $expectedFinal = round(
        (float)$tu['P_in (entrada) (dBµV)'] - (float)$tu['Pérdida Total (dB)'],
        2
    );

    if (abs($expectedFinal - (float)$tu['Nivel TU Final (dBµV)']) > 0.05) {
        die('Level mismatch on Toma ' . ($tu['Toma'] ?? 'UNKNOWN'));
    }



    // --- Write Detalle_Tomas row ---
    $col = 'A';
    foreach ($DETALLE_TOMAS_COLUMNS as $colName) {
        if (!array_key_exists($colName, $tu)) {
            die("Missing column '$colName' in detail_json");
        }

        $value = $tu[$colName];

        $sheet->setCellValueExplicit(
            $col . $rowNum,
            $value,
            is_numeric($value) ? DataType::TYPE_NUMERIC : DataType::TYPE_STRING
        );

        $col++;
    }

    $rowNum++;
}


// --------------------------------------------------
// 7. Categorized Inventory Sheets
// --------------------------------------------------

// --- Vertical Distribution ---
$verticalSheet = $spreadsheet->createSheet();
$verticalSheet->setTitle('Inventario Vertical');
// Headers: Scope, Tipo, Componente, Unidad, Cantidad, Observación
$verticalSheet->fromArray(['Alcance', 'Tipo', 'Componente', 'Unidad', 'Cantidad', 'Observación'], null, 'A1');
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
// Add total row for Vertical Distribution
$row++; // blank line
$totalRowStart = $row;
foreach ($allTotals['Vertical Distribution'] as $totalItem) {
    $verticalSheet->setCellValue('A' . $row, 'TOTAL');
    $verticalSheet->setCellValue('B' . $row, 'DISTRIBUCIÓN VERTICAL');
    $verticalSheet->setCellValue('C' . $row, $totalItem['Tipo']); // Tipo for consistency
    $verticalSheet->setCellValue('D' . $row, $totalItem['Componente']);
    $verticalSheet->setCellValue('E' . $row, $totalItem['Unidad']);
    $verticalSheet->setCellValue('F' . $row, $totalItem['Cantidad']);
    $row++;
}
$verticalSheet->getStyle('A' . $totalRowStart . ':F' . ($row - 1))->getFont()->setBold(true);
foreach (['A', 'B', 'C', 'D', 'E', 'F'] as $col) {
    $verticalSheet->getColumnDimension($col)->setAutoSize(true);
}


// --- Horizontal Distribution ---
$horizontalSheet = $spreadsheet->createSheet();
$horizontalSheet->setTitle('Inventario Horizontal');
// Headers: Piso, Scope, Tipo, Componente, Unidad, Cantidad, Observación
$horizontalSheet->fromArray(['Piso', 'Alcance', 'Tipo', 'Componente', 'Unidad', 'Cantidad', 'Observación'], null, 'A1');
$row = 2;
// Sort floors for consistent output
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
    // Add per-floor subtotal
    $row++; // blank line
    $subtotalRowStart = $row;
    if (isset($allTotals['Horizontal Floor Subtotals'][$piso]) && is_array($allTotals['Horizontal Floor Subtotals'][$piso])) {
        foreach ($allTotals['Horizontal Floor Subtotals'][$piso] as $totalItem) {
            $horizontalSheet->setCellValue('A' . $row, 'SUBTOTAL PISO ' . $piso);
            $horizontalSheet->setCellValue('B' . $row, $totalItem['Scope']); // Scope for consistency
            $horizontalSheet->setCellValue('C' . $row, $totalItem['Tipo']); // Tipo for consistency
            $horizontalSheet->setCellValue('D' . $row, $totalItem['Componente']);
            $horizontalSheet->setCellValue('E' . $row, $totalItem['Unidad']);
            $horizontalSheet->setCellValue('F' . $row, $totalItem['Cantidad']);
            $row++;
        }
    }
    $horizontalSheet->getStyle('A' . $subtotalRowStart . ':G' . ($row - 1))->getFont()->setBold(true);
    $row++; // blank line after subtotal
}
// Add global total row for Horizontal Distribution
$row++; // blank line
$globalTotalRowStart = $row;
foreach ($allTotals['Horizontal Distribution'] as $totalItem) {
    $horizontalSheet->setCellValue('A' . $row, 'TOTAL');
    $horizontalSheet->setCellValue('B' . $row, 'DISTRIBUCIÓN HORIZONTAL');
    $horizontalSheet->setCellValue('C' . $row, $totalItem['Tipo']); // Tipo for consistency
    $horizontalSheet->setCellValue('D' . $row, $totalItem['Componente']);
    $horizontalSheet->setCellValue('E' . $row, $totalItem['Unidad']);
    $horizontalSheet->setCellValue('F' . $row, $totalItem['Cantidad']);
    $row++;
}
$horizontalSheet->getStyle('A' . $globalTotalRowStart . ':G' . ($row - 1))->getFont()->setBold(true);

foreach (['A', 'B', 'C', 'D', 'E', 'F', 'G'] as $col) {
    $horizontalSheet->getColumnDimension($col)->setAutoSize(true);
}


// --- Apartment Interior ---
$apartmentSheet = $spreadsheet->createSheet();
$apartmentSheet->setTitle('Inventario Apartamento');
// Headers: Piso, Apto, Scope, Tipo, Componente, Unidad, Cantidad, Observación
$apartmentSheet->fromArray(['Piso', 'Apto', 'Alcance', 'Tipo', 'Componente', 'Unidad', 'Cantidad', 'Observación'], null, 'A1');
$row = 2;
// Sort floors and then apartments for consistent output
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
// Add global total row for Apartment Interior
$row++; // blank line
$globalTotalRowStart = $row;
foreach ($allTotals['Apartment Interior'] as $totalItem) {
    $apartmentSheet->setCellValue('A' . $row, 'TOTAL');
    $apartmentSheet->setCellValue('B' . $row, 'INTERIOR APARTAMENTO');
    $apartmentSheet->setCellValue('C' . $row, $totalItem['Tipo']); // Tipo for consistency
    $apartmentSheet->setCellValue('D' . $row, $totalItem['Componente']);
    $apartmentSheet->setCellValue('E' . $row, $totalItem['Unidad']);
    $apartmentSheet->setCellValue('F' . $row, $totalItem['Cantidad']);
    $row++;
}
$apartmentSheet->getStyle('A' . $globalTotalRowStart . ':H' . ($row - 1))->getFont()->setBold(true);

foreach (['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H'] as $col) {
    $apartmentSheet->getColumnDimension($col)->setAutoSize(true);
}

// --- Grand Total (Proyecto) ---
$grandTotalSheet = $spreadsheet->createSheet();
$grandTotalSheet->setTitle('Inventario Total Proyecto');
// Headers: Alcance, Tipo, Componente, Unidad, Cantidad
$grandTotalSheet->fromArray(['Alcance', 'Tipo', 'Componente', 'Unidad', 'Cantidad'], null, 'A1');
$row = 2;
$totalRowStart = $row;
foreach ($allTotals['Grand Total'] as $totalItem) {
    $grandTotalSheet->setCellValue('A' . $row, $totalItem['Scope']);
    $grandTotalSheet->setCellValue('B' . $row, $totalItem['Tipo']);
    $grandTotalSheet->setCellValue('C' . $row, $totalItem['Componente']);
    $grandTotalSheet->setCellValue('D' . $row, $totalItem['Unidad']);
    $grandTotalSheet->setCellValue('E' . $row, $totalItem['Cantidad']);
    $row++;
}
$grandTotalSheet->getStyle('A' . $totalRowStart . ':E' . ($row - 1))->getFont()->setBold(true);
foreach (['A', 'B', 'C', 'D', 'E'] as $col) {
    $grandTotalSheet->getColumnDimension($col)->setAutoSize(true);
}

// --------------------------------------------------
// 8. Resumen_Ingenieria Sheet (EPIC 3.3)
// --------------------------------------------------
$summarySheet = $spreadsheet->createSheet();
$summarySheet->setTitle("Resumen_Ingenieria");



// P_in is global — take from first TU
$pin = isset($detail[0]["P_in (entrada) (dBµV)"])
    ? floatval($detail[0]["P_in (entrada) (dBµV)"])
    : null;

$min = $summary["min_level"];
$max = $summary["max_level"];
$avg = $summary["avg_level"];
$num = $summary["num_tomas"];

$status = ($min >= 47 && $max <= 70) ? "PASS" : "FAIL";

$sheet = $summarySheet;

// Content
$sheet->setCellValue("A1", "Resumen de Ingeniería");

$sheet->setCellValue("A3", "Número de Tomas");
$sheet->setCellValue("B3", $num);

$sheet->setCellValue("A4", "Nivel mínimo TU (dBµV)");
$sheet->setCellValue("B4", round($min, 2));

$sheet->setCellValue("A5", "Nivel máximo TU (dBµV)");
$sheet->setCellValue("B5", round($max, 2));

$sheet->setCellValue("A6", "Nivel promedio TU (dBµV)");
$sheet->setCellValue("B6", round($avg, 2));

$sheet->setCellValue("A7", "Nivel de Entrada P_in (dBµV)");
$sheet->setCellValue("B7", $pin);

$sheet->setCellValue("A9", "Estado General");
$sheet->setCellValue("B9", $status);

// Styling
$sheet->getStyle("A1")->getFont()->setBold(true)->setSize(14);
$sheet->getStyle("A3:A9")->getFont()->setBold(true);

$sheet->getStyle("A3:B9")->getBorders()->getAllBorders()
    ->setBorderStyle(Border::BORDER_THIN);

if ($status === "PASS") {
    $sheet->getStyle("B9")->getFill()
        ->setFillType(Fill::FILL_SOLID)
        ->getStartColor()->setRGB("C6EFCE");
} else {
    $sheet->getStyle("B9")->getFill()
        ->setFillType(Fill::FILL_SOLID)
        ->getStartColor()->setRGB("FFC7CE");
}

$sheet->getStyle("B9")->getFont()->setBold(true);

foreach (["A", "B"] as $col) {
    $sheet->getColumnDimension($col)->setAutoSize(true);
}
$spreadsheet->setActiveSheetIndex(0); // Detalle_Tomas

// --------------------------------------------------
// 6. Output
// --------------------------------------------------
$filename = "export_opt_{$opt_id}_detalle_tomas.xlsx";

header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: max-age=0');

$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit;
