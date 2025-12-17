<?php
require_once __DIR__ . "/../app/config/db.php";
require_once __DIR__ . "/../vendor/autoload.php";

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Cell\DataType;


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
$detail  = json_decode($result['detail_json'], true);

if (!is_array($detail)) {
    die('Invalid detail_json format');
}

// --------------------------------------------------
// Try to resolve global input level from multiple canonical locations
if (isset($summary['input_level'])) {
    $P_IN = (float)$summary['input_level'];
} elseif (isset($summary['P_in (entrada) (dBµV)'])) {
    $P_IN = (float)$summary['P_in (entrada) (dBµV)'];
} elseif (isset($detail[0]['P_in (entrada) (dBµV)'])) {
    // Fallback: derive from first TU (must be identical for all TUs)
    $P_IN = (float)$detail[0]['P_in (entrada) (dBµV)'];
} else {
    die('Missing global P_in (entrada) in summary_json and detail_json');
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
// 4. Spreadsheet initialization
// --------------------------------------------------
$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle('Detalle_Tomas');

// --------------------------------------------------
// 4b. Prepare Inventario aggregations (EPIC 3.2)
// --------------------------------------------------
$inventario = [
    'Cable' => [ 'Cable Coaxial' => 0.0 ],
    'Conectores' => [ 'Conector F' => 0 ],
    'Tomas' => [ 'Toma de Usuario (TU)' => 0 ],
    'Equipos' => []
];

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

    // --- Inventario aggregation ---
    $inventario['Cable']['Cable Coaxial'] += (float)$tu['Distancia total hasta la toma (m)'];
    $inventario['Conectores']['Conector F'] +=
        ($tu['Riser Conectores (uds)'] ?? 0) + 2; // entrada + TU
    $inventario['Tomas']['Toma de Usuario (TU)']++;

    if (!empty($tu['Repartidor Troncal'])) {
        $inventario['Equipos'][$tu['Repartidor Troncal']] =
            ($inventario['Equipos'][$tu['Repartidor Troncal']] ?? 0) + 1;
    }
    if (!empty($tu['Derivador Piso'])) {
        $inventario['Equipos'][$tu['Derivador Piso']] =
            ($inventario['Equipos'][$tu['Derivador Piso']] ?? 0) + 1;
    }
    if (!empty($tu['Repartidor Apt']) && $tu['Repartidor Apt'] !== 'N/A') {
        $inventario['Equipos'][$tu['Repartidor Apt']] =
            ($inventario['Equipos'][$tu['Repartidor Apt']] ?? 0) + 1;
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
// 7. Inventario Sheet (EPIC 3.2)
// --------------------------------------------------
$invSheet = $spreadsheet->createSheet();
$invSheet->setTitle('Inventario');

$invSheet->fromArray(['Tipo', 'Componente', 'Cantidad'], null, 'A1');
$row = 2;

foreach ($inventario as $tipo => $items) {
    foreach ($items as $comp => $qty) {
        $invSheet->setCellValue('A' . $row, $tipo);
        $invSheet->setCellValue('B' . $row, $comp);
        $invSheet->setCellValue('C' . $row, is_float($qty) ? round($qty, 2) . ' m' : $qty . ' uds.');
        $row++;
    }
}
// --------------------------------------------------
// 8. Resumen_Ingenieria Sheet (EPIC 3.3)
// --------------------------------------------------
$summarySheet = $spreadsheet->createSheet();
$summarySheet->setTitle("Resumen_Ingenieria");

$summary = json_decode($result["summary_json"], true);
$detail  = json_decode($result["detail_json"], true);

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
