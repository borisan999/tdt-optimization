<?php
declare(strict_types=1);

/**
 * DOCX Export Functionality
 * -------------------------
 * Supports two modes:
 * - engineering (default): Detailed technical report in landscape with all inventory sections.
 * - report: Professional 3-page "Memoria Técnica" in portrait.
 */

ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../app/config/db.php';
require_once __DIR__ . '/../app/auth/require_login.php';
require_once __DIR__ . '/../app/helpers/InventoryAggregator.php';
require_once __DIR__ . '/../app/helpers/ResultParser.php';
require_once __DIR__ . '/../app/services/CanonicalMapperService.php';

use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\IOFactory;
use app\helpers\InventoryAggregator;
use app\helpers\ResultParser;

// --------------------------------------------------
// Input
// --------------------------------------------------
$opt_id = intval($_GET['opt_id'] ?? 0);
$mode   = $_GET['mode'] ?? 'engineering'; // 'engineering' or 'report'

if ($opt_id <= 0) {
    die('opt_id is required');
}

// --------------------------------------------------
// DB connection
// --------------------------------------------------
$db  = new Database();
$pdo = $db->getConnection();

ensureResultAccess($opt_id, $pdo);

// --------------------------------------------------
// Load results
// --------------------------------------------------
$stmt = $pdo->prepare("
    SELECT r.summary_json, r.detail_json, r.inputs_json, d.dataset_name
    FROM results r
    JOIN datasets d ON d.dataset_id = r.dataset_id
    WHERE r.opt_id = :id
");
$stmt->execute([':id' => $opt_id]);
$result = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$result) {
    die('Optimization result not found');
}

$dataset_name = $result['dataset_name'] ?? 'Unnamed';
$safe_name = preg_replace('/[^a-zA-Z0-9_\-]/', '_', $dataset_name);

// --------------------------------------------------
// Build Canonical Model
// --------------------------------------------------
$parser = ResultParser::fromDbRow($result);
if ($parser->hasErrors()) {
    die('Error parsing result: ' . implode(', ', $parser->errors()));
}

$canonical = $parser->canonical();
$summary  = $canonical['summary']  ?? [];
$inputs   = $canonical['inputs']   ?? [];
$detail   = $canonical['detail']   ?? [];
$warnings = $canonical['warnings'] ?? [];

if (!is_array($detail) || empty($detail)) {
    die('Invalid or empty canonical detail data');
}

// --------------------------------------------------
// Inventory Aggregation
// --------------------------------------------------
$aggregator = new InventoryAggregator($canonical);
$aggregatedData = $aggregator->aggregate();
$categorizedInventory = $aggregatedData['inventory'];
$allTotals = $aggregatedData['totals'];

// --------------------------------------------------
// DOCX generation
// --------------------------------------------------
$phpWord = new PhpWord();

// Define Common Title Styles
$phpWord->addTitleStyle(1, ['bold' => true, 'size' => 18, 'color' => '2E74B5'], ['alignment' => 'center']);
$phpWord->addTitleStyle(2, ['bold' => true, 'size' => 14, 'color' => '2E74B5'], ['spaceBefore' => 200]);

if ($mode === 'report') {
    // ==========================================
    // MODE: REPORT (Memoria Técnica - 3 Pages)
    // ==========================================
    $section = $phpWord->addSection([
        'orientation' => 'portrait',
        'marginTop' => 1440, 'marginBottom' => 1440, 'marginLeft' => 1440, 'marginRight' => 1440
    ]);

    // --- PAGE 1: COVER ---
    $section->addText('MEMORIA TÉCNICA DE DISEÑO', ['bold' => true, 'size' => 26, 'color' => '2E74B5'], ['alignment' => 'center']);
    $section->addText('RED DE DISTRIBUCIÓN TDT', ['bold' => true, 'size' => 18], ['alignment' => 'center', 'spaceAfter' => 600]);
    
    $section->addTextBreak(2);
    $section->addText("Proyecto: " . htmlspecialchars($dataset_name), ['bold' => true, 'size' => 14]);
    $section->addText("ID de Optimización: #{$opt_id}", ['size' => 12]);
    $section->addText("Fecha de Reporte: " . date('d/m/Y'), ['size' => 12]);
    $section->addTextBreak(3);

    $section->addTitle('1. Descripción del Inmueble', 2);
    $allPisos = array_unique(array_column($detail, 'piso'));
    $maxPiso = !empty($allPisos) ? max($allPisos) : 0;
    $totAptos = count(array_unique(array_map(fn($d) => $d['piso'] . '-' . $d['apto'], $detail)));
    $totTus = count($detail);
    
    $section->addText(
        "El inmueble analizado consta de un total de {$maxPiso} niveles habitables con {$totAptos} unidades residenciales/comerciales independientes. " .
        "La red proyectada contempla la alimentación de {$totTus} tomas de usuario (TU) finales, distribuidas estratégicamente según la topología del edificio.",
        ['size' => 11], ['alignment' => 'both']
    );

    $section->addTitle('2. Arquitectura de la Red', 2);
    $section->addText(
        "La arquitectura de distribución consiste en una etapa de captación y amplificación en cabecera, seguida de una red de distribución vertical (Riser) " .
        "implementada mediante derivadores de piso. Desde cada nodo de piso, se realiza una distribución horizontal hacia los apartamentos, donde se ubican " .
        "repartidores de abonado de bajas pérdidas para la entrega final de señal. El diseño ha sido optimizado matemáticamente para garantizar " .
        "niveles de potencia homogéneos y el cumplimiento estricto de la normativa vigente en todos los puntos de entrega.",
        ['size' => 11], ['alignment' => 'both']
    );

    // --- PAGE 2: KPIs ---
    $section->addPageBreak();
    $section->addTitle('3. Desempeño y Niveles de Señal', 2);
    $section->addTextBreak(1);

    $kpiTable = $section->addTable(['borderSize' => 6, 'borderColor' => 'A9A9A9', 'cellMargin' => 80]);
    $kpiTable->addRow();
    $kpiTable->addCell(5000, ['bgColor' => 'F2F2F2'])->addText('Métrica de Desempeño', ['bold' => true]);
    $kpiTable->addCell(4000, ['bgColor' => 'F2F2F2'])->addText('Valor Calculado', ['bold' => true]);

    $numCumple = count(array_filter($detail, fn($d) => ($d['cumple'] ?? 0)));
    $pct = $totTus > 0 ? round(($numCumple / $totTus) * 100, 2) : 0;
    $kpiTable->addRow();
    $kpiTable->addCell()->addText('Cumplimiento Normativo (%)');
    $kpiTable->addCell()->addText("{$pct}%", ['bold' => true, 'color' => $pct >= 100 ? '00B050' : 'FF0000']);

    $kpiTable->addRow();
    $kpiTable->addCell()->addText('Nivel Mínimo Detectado (dBµV)');
    $kpiTable->addCell()->addText(number_format((float)($summary["min_nivel_tu"] ?? 0), 2));

    $kpiTable->addRow();
    $kpiTable->addCell()->addText('Nivel Máximo Detectado (dBµV)');
    $kpiTable->addCell()->addText(number_format((float)($summary["max_nivel_tu"] ?? 0), 2));

    $kpiTable->addRow();
    $kpiTable->addCell()->addText('Nivel Promedio en Tomas (dBµV)');
    $kpiTable->addCell()->addText(number_format((float)($summary["avg_nivel_tu"] ?? 0), 2));

    $section->addTextBreak(2);
    $section->addText('Muestra de Resultados por Toma (Top 20)', ['bold' => true, 'size' => 12]);
    
    $tuTable = $section->addTable(['borderSize' => 6, 'borderColor' => 'A9A9A9', 'cellMargin' => 50, 'width' => 100*50, 'unit' => 'pct']);
    $tuTable->addRow();
    $headerStyle = ['bold' => true, 'size' => 9];
    $tuTable->addCell(2000, ['bgColor' => 'F2F2F2'])->addText('TU ID', $headerStyle);
    $tuTable->addCell(1000, ['bgColor' => 'F2F2F2'])->addText('Piso', $headerStyle);
    $tuTable->addCell(1000, ['bgColor' => 'F2F2F2'])->addText('Apto', $headerStyle);
    $tuTable->addCell(2000, ['bgColor' => 'F2F2F2'])->addText('Nivel (dBµV)', $headerStyle);
    $tuTable->addCell(2000, ['bgColor' => 'F2F2F2'])->addText('Estado', $headerStyle);

    $sampleDetail = array_slice($detail, 0, 20);
    foreach ($sampleDetail as $tu) {
        $tuTable->addRow();
        $tuTable->addCell()->addText($tu['tu_id'] ?? 'N/A', ['size' => 9]);
        $tuTable->addCell()->addText((string)($tu['piso'] ?? 'N/A'), ['size' => 9]);
        $tuTable->addCell()->addText((string)($tu['apto'] ?? 'N/A'), ['size' => 9]);
        $tuTable->addCell()->addText(number_format((float)($tu['nivel_tu'] ?? 0), 2), ['size' => 9]);
        $tuTable->addCell()->addText(($tu['cumple'] ?? false) ? 'OK' : 'FUERA', ['size' => 9, 'color' => ($tu['cumple'] ?? false) ? '00B050' : 'FF0000']);
    }

    // --- PAGE 3: INVENTORY ---
    $section->addPageBreak();
    $section->addTitle('4. Resumen de Inventario y Materiales', 2);
    $section->addTextBreak(1);

    $invTable = $section->addTable(['borderSize' => 6, 'borderColor' => 'A9A9A9', 'cellMargin' => 80, 'width' => 100*50, 'unit' => 'pct']);
    $invTable->addRow();
    $invTable->addCell(4000, ['bgColor' => 'F2F2F2'])->addText('Capa de Distribución', ['bold' => true]);
    $invTable->addCell(2000, ['bgColor' => 'F2F2F2'])->addText('Cable (m)', ['bold' => true], ['alignment' => 'center']);
    $invTable->addCell(2000, ['bgColor' => 'F2F2F2'])->addText('Equipos', ['bold' => true], ['alignment' => 'center']);
    $invTable->addCell(2000, ['bgColor' => 'F2F2F2'])->addText('Conectores', ['bold' => true], ['alignment' => 'center']);

    $scopeSummaries = $allTotals['Scope Summaries'] ?? [];
    $layers = ['Vertical' => 'Distribución Vertical', 'Horizontal' => 'Distribución Horizontal', 'Apartamento' => 'Interior Apartamentos'];
    foreach ($layers as $key => $lbl) {
        $d = $scopeSummaries[$key] ?? ['cable_m' => 0, 'equipment_uds' => 0, 'connectors_uds' => 0];
        $invTable->addRow();
        $invTable->addCell()->addText($lbl);
        $invTable->addCell()->addText(number_format($d['cable_m'], 1), [], ['alignment' => 'center']);
        $invTable->addCell()->addText(number_format($d['equipment_uds'], 0), [], ['alignment' => 'center']);
        $invTable->addCell()->addText(number_format($d['connectors_uds'], 0), [], ['alignment' => 'center']);
    }

    $section->addTextBreak(3);
    $section->addTitle('5. Declaración de Cumplimiento', 2);
    $section->addText(
        "Se certifica que el diseño técnico de la red de distribución TDT presentado en este documento ha sido validado " .
        "mediante modelos de optimización determinísticos. Los resultados confirman que la totalidad de los puntos de entrega " .
        "proyectados se encuentran dentro de los rangos de operación técnica requeridos para una recepción de señal de alta calidad.",
        ['size' => 11, 'italic' => true], ['alignment' => 'both']
    );

} else {
    // ==========================================
    // MODE: ENGINEERING (Restored Detailed View)
    // ==========================================
    $section = $phpWord->addSection(['orientation' => 'landscape']);

    // Page Header
    $header = $section->addHeader();
    $header->addText("TDT Network Optimization Report — Opt ID {$opt_id}", ['size' => 9]);
    $header->addText("Generated: " . date('Y-m-d H:i'), ['size' => 9], ['alignment' => 'right']);

    // Page Footer
    $footer = $section->addFooter();
    if (!empty($warnings)) {
        $footer->addText('---', ['size' => 9]);
        $footer->addText('Notas de Ingeniería y Supuestos del Modelo:', ['bold' => true, 'size' => 9]);
        foreach ($warnings as $w) { $footer->addText("⚠ " . $w, ['size' => 9]); }
        $footer->addText('Estas notas no invalidan los resultados de ingeniería, pero indican datos inferidos o asumidos.', ['size' => 8, 'italic' => true]);
    }

    $section->addText('TDT DISTRIBUTION NETWORK', ['bold' => true, 'size' => 18], ['alignment' => 'center']);
    $section->addText('ENGINEERING OPTIMIZATION REPORT', ['bold' => true, 'size' => 14], ['alignment' => 'center']);
    $section->addTextBreak(1);

    // General Summary KPIs
    $titleTable = $section->addTable(['borderSize' => 0]);
    $titleTable->addRow();
    $titleTable->addCell(4000)->addText('ID de Optimización');
    $titleTable->addCell(4000)->addText((string)$opt_id);

    $titleTable->addRow();
    $titleTable->addCell()->addText('Nivel de Entrada (dBµV)');
    $titleTable->addCell()->addText(($inputs['potencia_entrada'] ?? 'Normalizado por optimización'));

    $titleTable->addRow();
    $titleTable->addCell()->addText('Nivel mínimo TU (dBµV)');
    $titleTable->addCell()->addText(number_format((float)($summary["min_nivel_tu"] ?? 0), 2));

    $titleTable->addRow();
    $titleTable->addCell()->addText('Nivel máximo TU (dBµV)');
    $titleTable->addCell()->addText(number_format((float)($summary["max_nivel_tu"] ?? 0), 2));

    $titleTable->addRow();
    $titleTable->addCell()->addText('Número de Tomas');
    $titleTable->addCell()->addText((string)($summary["total_tus"] ?? count($detail)));

    $section->addTextBreak(2);

    // Resumen de Materiales por Capa
    $section->addText('Resumen de Materiales por Capa', ['bold' => true, 'size' => 12]);
    $summaryTable = $section->addTable(['borderSize' => 6, 'cellMargin' => 80, 'width' => 100 * 50, 'unit' => 'pct']);
    $summaryTable->addRow();
    $summaryTable->addCell(4000)->addText('Capa de Distribución', ['bold' => true]);
    $summaryTable->addCell(2000)->addText('Cable Total (m)', ['bold' => true], ['alignment' => 'center']);
    $summaryTable->addCell(2000)->addText('Equipos (uds)', ['bold' => true], ['alignment' => 'center']);
    $summaryTable->addCell(2000)->addText('Conectores (uds)', ['bold' => true], ['alignment' => 'center']);

    $scopeSummaries = $allTotals['Scope Summaries'] ?? [];
    foreach (['Vertical' => 'Distribución Vertical', 'Horizontal' => 'Distribución Horizontal', 'Apartamento' => 'Interior de Apartamento'] as $key => $lbl) {
        $d = $scopeSummaries[$key] ?? ['cable_m' => 0, 'equipment_uds' => 0, 'connectors_uds' => 0];
        $summaryTable->addRow();
        $summaryTable->addCell()->addText($lbl, ['bold' => true]);
        $summaryTable->addCell()->addText(number_format($d['cable_m'], 2) . ' m', [], ['alignment' => 'center']);
        $summaryTable->addCell()->addText(number_format($d['equipment_uds'], 0), [], ['alignment' => 'center']);
        $summaryTable->addCell()->addText(number_format($d['connectors_uds'], 0), [], ['alignment' => 'center']);
    }

    $section->addTextBreak(2);

    // Sección: Distribución Vertical
    $section->addText('Sección: Distribución Vertical', ['bold' => true, 'size' => 12]);
    $vTable = $section->addTable(['borderSize' => 6, 'cellMargin' => 80, 'width' => 100*50, 'unit' => 'pct']);
    $vTable->addRow();
    foreach(['Alcance','Tipo','Componente','Unidad','Cantidad','Observación'] as $h) { $vTable->addCell()->addText($h, ['bold' => true]); }
    foreach ($categorizedInventory['Vertical Distribution'] as $item) {
        $vTable->addRow();
        foreach(['Scope','Tipo','Componente','Unidad','Cantidad','Observación'] as $k) { $vTable->addCell()->addText((string)($item[$k] ?? '')); }
    }

    $section->addTextBreak(2);

    // Sección: Distribución Horizontal
    $section->addText('Sección: Distribución Horizontal', ['bold' => true, 'size' => 12]);
    ksort($categorizedInventory['Horizontal Distribution']);
    foreach ($categorizedInventory['Horizontal Distribution'] as $piso => $items) {
        $section->addText("Piso {$piso}:", ['bold' => true, 'italic' => true]);
        $hTable = $section->addTable(['borderSize' => 6, 'cellMargin' => 80, 'width' => 100*50, 'unit' => 'pct']);
        $hTable->addRow();
        foreach(['Tipo','Componente','Unidad','Cantidad','Observación'] as $h) { $hTable->addCell()->addText($h, ['bold' => true]); }
        foreach ($items as $item) {
            $hTable->addRow();
            foreach(['Tipo','Componente','Unidad','Cantidad','Observación'] as $k) { $hTable->addCell()->addText((string)($item[$k] ?? '')); }
        }
        
        // Restore Subtotal per Floor
        if (isset($allTotals['Horizontal Floor Subtotals'][$piso])) {
            $section->addText("Subtotal por Piso {$piso}:", ['bold' => true, 'italic' => true]);
            $hsTable = $section->addTable(['borderSize' => 6, 'cellMargin' => 80, 'width' => 100*50, 'unit' => 'pct']);
            foreach ($allTotals['Horizontal Floor Subtotals'][$piso] as $sub) {
                $hsTable->addRow();
                $hsTable->addCell(2000)->addText("SUBTOTAL", ['bold' => true]);
                $hsTable->addCell(2000)->addText($sub['Tipo'], ['bold' => true]);
                $hsTable->addCell(4000)->addText($sub['Componente'], ['bold' => true]);
                $hsTable->addCell(1000)->addText($sub['Unidad'], ['bold' => true]);
                $hsTable->addCell(1000)->addText((string)$sub['Cantidad'], ['bold' => true]);
            }
        }
        $section->addTextBreak(1);
    }

    $section->addTextBreak(2);

    // Sección: Interior de Apartamentos
    $section->addText('Sección: Interior de Apartamentos', ['bold' => true, 'size' => 12]);
    ksort($categorizedInventory['Apartment Interior']);
    foreach ($categorizedInventory['Apartment Interior'] as $piso => $apts) {
        ksort($apts);
        foreach ($apts as $apto => $items) {
            $section->addText("Piso {$piso}, Apto {$apto}:", ['bold' => true, 'italic' => true]);
            $iTable = $section->addTable(['borderSize' => 6, 'cellMargin' => 80, 'width' => 100*50, 'unit' => 'pct']);
            $iTable->addRow();
            foreach(['Tipo','Componente','Unidad','Cantidad','Observación'] as $h) { $iTable->addCell()->addText($h, ['bold' => true]); }
            foreach ($items as $item) {
                $iTable->addRow();
                foreach(['Tipo','Componente','Unidad','Cantidad','Observación'] as $k) { $iTable->addCell()->addText((string)($item[$k] ?? '')); }
            }
            $section->addTextBreak(1);
        }
    }

    $section->addPageBreak();

    // Sección: Inventario Total del Proyecto
    $section->addText('Sección: Inventario Total del Proyecto', ['bold' => true, 'size' => 12]);
    $gTable = $section->addTable(['borderSize' => 6, 'cellMargin' => 80, 'width' => 100*50, 'unit' => 'pct']);
    $gTable->addRow();
    foreach(['Alcance','Tipo','Componente','Unidad','Cantidad'] as $h) { $gTable->addCell()->addText($h, ['bold' => true]); }
    foreach ($allTotals['Grand Total'] as $item) {
        $gTable->addRow();
        foreach(['Scope','Tipo','Componente','Unidad','Cantidad'] as $k) { $gTable->addCell()->addText((string)($item[$k] ?? '')); }
    }

    $section->addTextBreak(2);

    // Sección: Resultados por Toma (Detalle Crítico)
    $section->addText('Sección: Resultados por Toma (Detalle Crítico)', ['bold' => true, 'size' => 12]);
    $tuTable = $section->addTable(['borderSize' => 6, 'cellMargin' => 80, 'width' => 100*50, 'unit' => 'pct']);
    $tuTable->addRow();
    foreach(['Toma','Piso','Apto','Nivel (dBµV)','Estado'] as $h) { $tuTable->addCell()->addText($h, ['bold' => true]); }
    foreach ($detail as $tu) {
        $tuTable->addRow();
        $tuTable->addCell()->addText($tu['tu_id'] ?? 'N/A');
        $tuTable->addCell()->addText((string)($tu['piso'] ?? 'N/A'));
        $tuTable->addCell()->addText((string)($tu['apto'] ?? 'N/A'));
        $tuTable->addCell()->addText(number_format((float)($tu['nivel_tu'] ?? 0), 2));
        $tuTable->addCell()->addText(($tu['cumple'] ?? false) ? 'Dentro de rango' : 'Fuera de rango');
    }
}

// --------------------------------------------------
// Output
// --------------------------------------------------
$filename = ($mode === 'report' ? "Memoria_Tecnica_" : "Engineering_Report_") . "{$safe_name}_{$opt_id}.docx";

header('Content-Type: application/vnd.openxmlformats-officedocument.wordprocessingml.document');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: max-age=0');

$writer = IOFactory::createWriter($phpWord, 'Word2007');
$writer->save('php://output');
exit;
