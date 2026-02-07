<?php
declare(strict_types=1);

ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../app/config/db.php';
require_once __DIR__ . '/../app/helpers/InventoryAggregator.php';

use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\IOFactory;
use app\helpers\InventoryAggregator;

// --------------------------------------------------
// Input
// --------------------------------------------------
$opt_id = intval($_GET['opt_id'] ?? 0);
if ($opt_id <= 0) {
    die('opt_id is required');
}

// --------------------------------------------------
// DB connection
// --------------------------------------------------
$db  = new Database();
$pdo = $db->getConnection();

// --------------------------------------------------
// Load results
// --------------------------------------------------
$stmt = $pdo->prepare("
    SELECT summary_json, detail_json, inputs_json
    FROM results
    WHERE opt_id = :id
");
$stmt->execute([':id' => $opt_id]);
$result = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$result) {
    die('Optimization result not found');
}

$summary = json_decode($result['summary_json'], true);
$detail  = json_decode($result['detail_json'], true);
$inputs  = json_decode($result['inputs_json'], true);

if (!is_array($detail) || empty($detail)) {
    die('Invalid or empty detail_json');
}

// Prepare Categorized Inventory
$aggregator = new InventoryAggregator($detail);
$aggregatedData = $aggregator->aggregate();
$categorizedInventory = $aggregatedData['inventory'];
$allTotals = $aggregatedData['totals'];



// --------------------------------------------------
// Resolve global P_in (display only)
// --------------------------------------------------
$GLOBAL_P_IN = null;
if (isset($summary['input_level'])) {
    $GLOBAL_P_IN = (float)$summary['input_level'];
} elseif (isset($summary['P_in (entrada) (dBµV)'])) {
    $GLOBAL_P_IN = (float)$summary['P_in (entrada) (dBµV)'];
}

// --------------------------------------------------
// DOCX generation
// --------------------------------------------------
$phpWord = new PhpWord();
$section = $phpWord->addSection();

//Page Header (Traceability)

$header = $section->addHeader();
$header->addText(
    "TDT Network Optimization Report — Opt ID {$opt_id}",
    ['size' => 9]
);
$header->addText(
    "Generated: " . date('Y-m-d H:i'),
    ['size' => 9],
    ['alignment' => 'right']
);

$section = $phpWord->addSection();

// --------------------------------------------------
// Title
// --------------------------------------------------
$section->addText(
    'TDT DISTRIBUTION NETWORK',
    ['bold' => true, 'size' => 18],
    ['alignment' => 'center']
);
$section->addText(
    'ENGINEERING OPTIMIZATION REPORT',
    ['bold' => true, 'size' => 14],
    ['alignment' => 'center']
);

$section->addTextBreak(1);

$titleTable = $section->addTable(['borderSize' => 0]);

$titleTable->addRow();
$titleTable->addCell(4000)->addText('ID de Optimización');
$titleTable->addCell(4000)->addText((string)$opt_id);

$titleTable->addRow();
$titleTable->addCell()->addText('Nivel de Entrada (dBµV)');
$titleTable->addCell()->addText(
    $GLOBAL_P_IN !== null ? number_format($GLOBAL_P_IN, 2) : 'Normalizado por optimización'
);

$titleTable->addRow();
$titleTable->addCell()->addText('Nivel mínimo TU (dBµV)');
$titleTable->addCell()->addText(number_format((float)($summary["min_level"] ?? 0), 2));

$titleTable->addRow();
$titleTable->addCell()->addText('Nivel máximo TU (dBµV)');
$titleTable->addCell()->addText(number_format((float)($summary["max_level"] ?? 0), 2));

$titleTable->addRow();
$titleTable->addCell()->addText('Nivel promedio TU (dBµV)');
$titleTable->addCell()->addText(number_format((float)($summary["avg_level"] ?? 0), 2));

$titleTable->addRow();
$titleTable->addCell()->addText('Número de Tomas');
$titleTable->addCell()->addText((string)($summary["num_tomas"] ?? 0));

$section->addTextBreak(2);

// --------------------------------------------------
// Sección: Distribución Vertical
// --------------------------------------------------
$section->addText(
    'Sección: Distribución Vertical',
    ['bold' => true, 'size' => 12]
);
$section->addTextBreak(1);

$verticalTable = $section->addTable([
    'borderSize' => 6,
    'borderColor' => '000000',
    'cellMargin' => 80,
    'width' => 100 * 50, // 100% width
    'unit' => \PhpOffice\PhpWord\SimpleType\TblWidth::PERCENT
]);

$verticalTable->addRow();
$verticalTable->addCell(1500)->addText('Alcance', ['bold' => true]);
$verticalTable->addCell(1500)->addText('Tipo', ['bold' => true]);
$verticalTable->addCell(3000)->addText('Componente', ['bold' => true]);
$verticalTable->addCell(1000)->addText('Unidad', ['bold' => true]);
$verticalTable->addCell(1500)->addText('Cantidad', ['bold' => true]);
$verticalTable->addCell(3000)->addText('Observación', ['bold' => true]);

foreach ($categorizedInventory['Vertical Distribution'] as $item) {
    $verticalTable->addRow();
    $verticalTable->addCell()->addText($item['Scope']);
    $verticalTable->addCell()->addText($item['Tipo']);
    $verticalTable->addCell()->addText($item['Componente']);
    $verticalTable->addCell()->addText($item['Unidad']);
    $verticalTable->addCell()->addText($item['Cantidad']);
    $verticalTable->addCell()->addText($item['Observación']);
}

// Add total row for Vertical Distribution
$section->addTextBreak(1);
$section->addText('Resumen total por capa de distribución:', ['italic' => true]);
foreach ($allTotals['Vertical Distribution'] as $totalItem) {
    $verticalTable->addRow();
    $verticalTable->addCell(1500)->addText('TOTAL', ['bold' => true]);
    $verticalTable->addCell(1500)->addText($totalItem['Scope'], ['bold' => true]); // Use Scope for consistency
    $verticalTable->addCell(3000)->addText($totalItem['Tipo'], ['bold' => true]); // Tipo
    $verticalTable->addCell(1000)->addText($totalItem['Componente'], ['bold' => true]); // Componente
    $verticalTable->addCell(1500)->addText($totalItem['Unidad'], ['bold' => true]); // Unidad
    $verticalTable->addCell(3000)->addText($totalItem['Cantidad'], ['bold' => true]); // Cantidad
    $verticalTable->addCell(3000)->addText('', ['bold' => true]); // Observación empty
}
$section->addTextBreak(2);

// --------------------------------------------------
// Sección: Distribución Horizontal
// --------------------------------------------------
$section->addText(
    'Sección: Distribución Horizontal',
    ['bold' => true, 'size' => 12]
);
$section->addTextBreak(1);

$horizontalTable = $section->addTable([
    'borderSize' => 6,
    'borderColor' => '000000',
    'cellMargin' => 80,
    'width' => 100 * 50, // 100% width
    'unit' => \PhpOffice\PhpWord\SimpleType\TblWidth::PERCENT
]);

$horizontalTable->addRow();
$horizontalTable->addCell(1000)->addText('Piso', ['bold' => true]);
$horizontalTable->addCell(1500)->addText('Alcance', ['bold' => true]);
$horizontalTable->addCell(1500)->addText('Tipo', ['bold' => true]);
$horizontalTable->addCell(3000)->addText('Componente', ['bold' => true]);
$horizontalTable->addCell(1000)->addText('Unidad', ['bold' => true]);
$horizontalTable->addCell(1500)->addText('Cantidad', ['bold' => true]);
$horizontalTable->addCell(3000)->addText('Observación', ['bold' => true]);

ksort($categorizedInventory['Horizontal Distribution']);
foreach ($categorizedInventory['Horizontal Distribution'] as $piso => $items) {
    foreach ($items as $item) {
        $horizontalTable->addRow();
        $horizontalTable->addCell()->addText($piso);
        $horizontalTable->addCell()->addText($item['Scope']);
        $horizontalTable->addCell()->addText($item['Tipo']);
        $horizontalTable->addCell()->addText($item['Componente']);
        $horizontalTable->addCell()->addText($item['Unidad']);
        $horizontalTable->addCell()->addText($item['Cantidad']);
        $horizontalTable->addCell()->addText($item['Observación']);
    }
    // Add per-floor subtotal
    $section->addTextBreak(1);
    $section->addText('Subtotal por Piso ' . $piso . ':', ['italic' => true]);
    if (isset($allTotals['Horizontal Floor Subtotals'][$piso]) && is_array($allTotals['Horizontal Floor Subtotals'][$piso])) {
        foreach ($allTotals['Horizontal Floor Subtotals'][$piso] as $totalItem) {
            $horizontalTable->addRow();
            $horizontalTable->addCell(1000)->addText('SUBTOTAL PISO ' . $piso, ['bold' => true]);
            $horizontalTable->addCell(1500)->addText($totalItem['Scope'], ['bold' => true]);
            $horizontalTable->addCell(1500)->addText($totalItem['Tipo'], ['bold' => true]);
            $horizontalTable->addCell(3000)->addText($totalItem['Componente'], ['bold' => true]);
            $horizontalTable->addCell(1000)->addText($totalItem['Unidad'], ['bold' => true]);
            $horizontalTable->addCell(1500)->addText($totalItem['Cantidad'], ['bold' => true]);
            $horizontalTable->addCell(3000)->addText('', ['bold' => true]); // Empty observation
        }
    }
}
// Add global total row for Horizontal Distribution
$section->addTextBreak(1);
$section->addText('Resumen total por capa de distribución:', ['italic' => true]);
foreach ($allTotals['Horizontal Distribution'] as $totalItem) {
    $horizontalTable->addRow();
    $horizontalTable->addCell(1000)->addText('TOTAL', ['bold' => true]);
    $horizontalTable->addCell(1500)->addText($totalItem['Scope'], ['bold' => true]); // Scope
    $horizontalTable->addCell(1500)->addText($totalItem['Tipo'], ['bold' => true]); // Tipo
    $horizontalTable->addCell(3000)->addText($totalItem['Componente'], ['bold' => true]); // Componente
    $horizontalTable->addCell(1000)->addText($totalItem['Unidad'], ['bold' => true]); // Unidad
    $horizontalTable->addCell(1500)->addText($totalItem['Cantidad'], ['bold' => true]); // Cantidad
    $horizontalTable->addCell(3000)->addText('', ['bold' => true]); // Observación empty
}
$section->addTextBreak(2);

// --------------------------------------------------
// Sección: Interior de Apartamentos
// --------------------------------------------------
$section->addText(
    'Sección: Interior de Apartamentos',
    ['bold' => true, 'size' => 12]
);
$section->addTextBreak(1);

$apartmentTable = $section->addTable([
    'borderSize' => 6,
    'borderColor' => '000000',
    'cellMargin' => 80,
    'width' => 100 * 50, // 100% width
    'unit' => \PhpOffice\PhpWord\SimpleType\TblWidth::PERCENT
]);

$apartmentTable->addRow();
$apartmentTable->addCell(1000)->addText('Piso', ['bold' => true]);
$apartmentTable->addCell(1000)->addText('Apto', ['bold' => true]);
$apartmentTable->addCell(1500)->addText('Alcance', ['bold' => true]);
$apartmentTable->addCell(1500)->addText('Tipo', ['bold' => true]);
$apartmentTable->addCell(3000)->addText('Componente', ['bold' => true]);
$apartmentTable->addCell(1000)->addText('Unidad', ['bold' => true]);
$apartmentTable->addCell(1500)->addText('Cantidad', ['bold' => true]);
$apartmentTable->addCell(3000)->addText('Observación', ['bold' => true]);

ksort($categorizedInventory['Apartment Interior']);
foreach ($categorizedInventory['Apartment Interior'] as $piso => $apts) {
    ksort($apts);
    foreach ($apts as $apto => $items) {
        foreach ($items as $item) {
            $apartmentTable->addRow();
            $apartmentTable->addCell()->addText($piso);
            $apartmentTable->addCell()->addText($apto);
            $apartmentTable->addCell()->addText($item['Scope']);
            $apartmentTable->addCell()->addText($item['Tipo']);
            $apartmentTable->addCell()->addText($item['Componente']);
            $apartmentTable->addCell()->addText($item['Unidad']);
            $apartmentTable->addCell()->addText($item['Cantidad']);
            $apartmentTable->addCell()->addText($item['Observación']);
        }
    }
}
// Add global total row for Apartment Interior
$section->addTextBreak(1);
$section->addText('Resumen total por capa de distribución:', ['italic' => true]);
foreach ($allTotals['Apartment Interior'] as $totalItem) {
    $apartmentTable->addRow();
    $apartmentTable->addCell(1000)->addText('TOTAL', ['bold' => true]);
    $apartmentTable->addCell(1000)->addText($totalItem['Scope'], ['bold' => true]); // Scope
    $apartmentTable->addCell(1500)->addText($totalItem['Tipo'], ['bold' => true]);
    $apartmentTable->addCell(3000)->addText($totalItem['Componente'], ['bold' => true]);
    $apartmentTable->addCell(1000)->addText($totalItem['Unidad'], ['bold' => true]);
    $apartmentTable->addCell(1500)->addText($totalItem['Cantidad'], ['bold' => true]);
    $apartmentTable->addCell(3000)->addText('', ['bold' => true]); // Empty observation
}
$section->addTextBreak(2);



// --------------------------------------------------
// Sección: Inventario Total del Proyecto
// --------------------------------------------------
$section->addText(
    'Sección: Inventario Total del Proyecto',
    ['bold' => true, 'size' => 12]
);
$section->addTextBreak(1);

$grandTotalTable = $section->addTable([
    'borderSize' => 6,
    'borderColor' => '000000',
    'cellMargin' => 80,
    'width' => 100 * 50, // 100% width
    'unit' => \PhpOffice\PhpWord\SimpleType\TblWidth::PERCENT
]);

$grandTotalTable->addRow();
$grandTotalTable->addCell(1500)->addText('Alcance', ['bold' => true]);
$grandTotalTable->addCell(1500)->addText('Tipo', ['bold' => true]);
$grandTotalTable->addCell(3000)->addText('Componente', ['bold' => true]);
$grandTotalTable->addCell(1000)->addText('Unidad', ['bold' => true]);
$grandTotalTable->addCell(1500)->addText('Cantidad', ['bold' => true]);

foreach ($allTotals['Grand Total'] as $totalItem) {
    $grandTotalTable->addRow();
    $grandTotalTable->addCell()->addText($totalItem['Scope']);
    $grandTotalTable->addCell()->addText($totalItem['Tipo']);
    $grandTotalTable->addCell()->addText($totalItem['Componente']);
    $grandTotalTable->addCell()->addText($totalItem['Unidad']);
    $grandTotalTable->addCell()->addText($totalItem['Cantidad']);
}
$section->addTextBreak(2);

// --------------------------------------------------
// Sección: Resultados por Toma (Detalle Crítico)
// --------------------------------------------------
$section->addText(
    'Sección: Resultados por Toma (Detalle Crítico)',
    ['bold' => true, 'size' => 12]
);
$section->addTextBreak(1);

$tuTable = $section->addTable([
    'borderSize' => 6,
    'borderColor' => '000000',
    'cellMargin' => 80,
    'width' => 100 * 50, // 100% width
    'unit' => \PhpOffice\PhpWord\SimpleType\TblWidth::PERCENT
]);

$tuTable->addRow();
$tuTable->addCell(1000)->addText('Toma', ['bold' => true]);
$tuTable->addCell(1000)->addText('Piso', ['bold' => true]);
$tuTable->addCell(1000)->addText('Apto', ['bold' => true]);
$tuTable->addCell(2500)->addText('Nivel TU Final (dBµV)', ['bold' => true]);
$tuTable->addCell(1500)->addText('Estado', ['bold' => true]);

foreach ($detail as $tu) {
    $tuTable->addRow();
    $tuTable->addCell()->addText($tu['Toma'] ?? 'N/A');
    $tuTable->addCell()->addText($tu['Piso'] ?? 'N/A');
    $tuTable->addCell()->addText($tu['Apto'] ?? 'N/A');
    $tuTable->addCell()->addText(
        isset($tu['Nivel TU Final (dBµV)'])
            ? number_format((float)$tu['Nivel TU Final (dBµV)'], 2)
            : 'N/A'
    );
    $tuTable->addCell()->addText(
        $tu['Estado'] ?? ($tu['OK'] ?? 'Dentro de rango')
    );
}
$section->addTextBreak(2);



// --------------------------------------------------
// Output
// --------------------------------------------------
$filename = "tdt_optimization_{$opt_id}.docx";

header('Content-Type: application/vnd.openxmlformats-officedocument.wordprocessingml.document');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: max-age=0');

$writer = IOFactory::createWriter($phpWord, 'Word2007');
$writer->save('php://output');
exit;
