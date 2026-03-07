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
    $section->addText(__('report_title'), ['bold' => true, 'size' => 26, 'color' => '2E74B5'], ['alignment' => 'center']);
    $section->addText(__('report_subtitle'), ['bold' => true, 'size' => 18], ['alignment' => 'center', 'spaceAfter' => 600]);
    
    $section->addTextBreak(2);
    $section->addText(__('report_project', ['name' => htmlspecialchars($dataset_name)]), ['bold' => true, 'size' => 14]);
    $section->addText(__('report_opt_id', ['id' => $opt_id]), ['size' => 12]);
    $section->addText(__('report_date', ['date' => date('d/m/Y')]), ['size' => 12]);
    $section->addTextBreak(3);

    $section->addTitle(__('report_section_1_title'), 2);
    $allPisos = array_unique(array_column($detail, 'piso'));
    $maxPiso = !empty($allPisos) ? max($allPisos) : 0;
    $totAptos = count(array_unique(array_map(fn($d) => $d['piso'] . '-' . $d['apto'], $detail)));
    $totTus = count($detail);
    
    $section->addText(
        __('report_section_1_desc', [
            'maxPiso' => $maxPiso,
            'totAptos' => $totAptos,
            'totTus' => $totTus
        ]),
        ['size' => 11], ['alignment' => 'both']
    );

    $section->addTitle(__('report_section_2_title'), 2);
    $section->addText(
        __('report_section_2_desc'),
        ['size' => 11], ['alignment' => 'both']
    );

    // --- PAGE 2: KPIs ---
    $section->addPageBreak();
    $section->addTitle(__('report_section_3_title'), 2);
    $section->addTextBreak(1);

    $kpiTable = $section->addTable(['borderSize' => 6, 'borderColor' => 'A9A9A9', 'cellMargin' => 80]);
    $kpiTable->addRow();
    $kpiTable->addCell(5000, ['bgColor' => 'F2F2F2'])->addText(__('report_kpi_metric'), ['bold' => true]);
    $kpiTable->addCell(4000, ['bgColor' => 'F2F2F2'])->addText(__('report_kpi_value'), ['bold' => true]);

    $numCumple = count(array_filter($detail, fn($d) => ($d['cumple'] ?? 0)));
    $pct = $totTus > 0 ? round(($numCumple / $totTus) * 100, 2) : 0;
    $kpiTable->addRow();
    $kpiTable->addCell()->addText(__('report_kpi_compliance'));
    $kpiTable->addCell()->addText("{$pct}%", ['bold' => true, 'color' => $pct >= 100 ? '00B050' : 'FF0000']);

    // Extract frequencies from input keys if they follow the standard naming
    $freqs = [];
    foreach ($inputs as $key => $val) {
        if (preg_match('/atenuacion_cable_(\d+)mhz/i', $key, $m)) {
            $freqs[] = $m[1];
        }
    }
    sort($freqs);
    $freqStr = !empty($freqs) ? implode(' / ', $freqs) : "470 / 698";

    $kpiTable->addRow();
    $kpiTable->addCell()->addText(__('report_kpi_freqs'));
    $kpiTable->addCell()->addText($freqStr, ['bold' => true]);

    $kpiTable->addRow();
    $kpiTable->addCell()->addText(__('report_kpi_min_level'));
    $kpiTable->addCell()->addText(number_format((float)($summary["min_nivel_tu"] ?? 0), 2));

    $kpiTable->addRow();
    $kpiTable->addCell()->addText(__('report_kpi_max_level'));
    $kpiTable->addCell()->addText(number_format((float)($summary["max_nivel_tu"] ?? 0), 2));

    $kpiTable->addRow();
    $kpiTable->addCell()->addText(__('report_kpi_avg_level'));
    $kpiTable->addCell()->addText(number_format((float)($summary["avg_nivel_tu"] ?? 0), 2));

    $section->addTextBreak(2);
    $section->addText(__('report_top_20_title'), ['bold' => true, 'size' => 12]);
    
    $tuTable = $section->addTable(['borderSize' => 6, 'borderColor' => 'A9A9A9', 'cellMargin' => 50, 'width' => 100*50, 'unit' => 'pct']);
    $tuTable->addRow();
    $headerStyle = ['bold' => true, 'size' => 9];
    $tuTable->addCell(2000, ['bgColor' => 'F2F2F2'])->addText(__('report_col_tu_id'), $headerStyle);
    $tuTable->addCell(1000, ['bgColor' => 'F2F2F2'])->addText(__('report_col_piso'), $headerStyle);
    $tuTable->addCell(1000, ['bgColor' => 'F2F2F2'])->addText(__('report_col_apto'), $headerStyle);
    $tuTable->addCell(2000, ['bgColor' => 'F2F2F2'])->addText(__('report_col_level'), $headerStyle);
    $tuTable->addCell(2000, ['bgColor' => 'F2F2F2'])->addText(__('report_col_status'), $headerStyle);

    $sampleDetail = array_slice($detail, 0, 20);
    foreach ($sampleDetail as $tu) {
        $tuTable->addRow();
        $tuTable->addCell()->addText($tu['tu_id'] ?? 'N/A', ['size' => 9]);
        $tuTable->addCell()->addText((string)($tu['piso'] ?? 'N/A'), ['size' => 9]);
        $tuTable->addCell()->addText((string)($tu['apto'] ?? 'N/A'), ['size' => 9]);
        $tuTable->addCell()->addText(number_format((float)($tu['nivel_tu'] ?? 0), 2), ['size' => 9]);
        $tuTable->addCell()->addText(($tu['cumple'] ?? false) ? __('report_status_ok') : __('report_status_out'), ['size' => 9, 'color' => ($tu['cumple'] ?? false) ? '00B050' : 'FF0000']);
    }

    // --- PAGE 3: INVENTORY ---
    $section->addPageBreak();
    $section->addTitle(__('report_section_4_title'), 2);
    $section->addTextBreak(1);

    $invTable = $section->addTable(['borderSize' => 6, 'borderColor' => 'A9A9A9', 'cellMargin' => 80, 'width' => 100*50, 'unit' => 'pct']);
    $invTable->addRow();
    $invTable->addCell(4000, ['bgColor' => 'F2F2F2'])->addText(__('report_col_layer'), ['bold' => true]);
    $invTable->addCell(2000, ['bgColor' => 'F2F2F2'])->addText(__('report_col_cable'), ['bold' => true], ['alignment' => 'center']);
    $invTable->addCell(2000, ['bgColor' => 'F2F2F2'])->addText(__('report_col_equipment'), ['bold' => true], ['alignment' => 'center']);
    $invTable->addCell(2000, ['bgColor' => 'F2F2F2'])->addText(__('report_col_connectors'), ['bold' => true], ['alignment' => 'center']);

    $scopeSummaries = $allTotals['Scope Summaries'] ?? [];
    $layers = [
        'Vertical' => __('report_layer_vertical'),
        'Horizontal' => __('report_layer_horizontal'),
        'Apartamento' => __('report_layer_apartment')
    ];
    foreach ($layers as $key => $lbl) {
        $d = $scopeSummaries[$key] ?? ['cable_m' => 0, 'equipment_uds' => 0, 'connectors_uds' => 0];
        $invTable->addRow();
        $invTable->addCell()->addText($lbl);
        $invTable->addCell()->addText(number_format($d['cable_m'], 1), [], ['alignment' => 'center']);
        $invTable->addCell()->addText(number_format($d['equipment_uds'], 0), [], ['alignment' => 'center']);
        $invTable->addCell()->addText(number_format($d['connectors_uds'], 0), [], ['alignment' => 'center']);
    }

    $section->addTextBreak(3);
    $section->addTitle(__('report_section_5_title'), 2);
    $section->addText(
        __('report_compliance_statement'),
        ['size' => 11, 'italic' => true], ['alignment' => 'both']
    );

} else {
    // ==========================================
    // MODE: ENGINEERING (Restored Detailed View)
    // ==========================================
    $section = $phpWord->addSection(['orientation' => 'landscape']);

    // Page Header
    $header = $section->addHeader();
    $header->addText(__('eng_header_info', ['id' => $opt_id]), ['size' => 9]);
    $header->addText(__('eng_generated_at', ['date' => date('Y-m-d H:i')]), ['size' => 9], ['alignment' => 'right']);

    // Page Footer
    $footer = $section->addFooter();
    if (!empty($warnings)) {
        $footer->addText('---', ['size' => 9]);
        $footer->addText(__('eng_notes_title'), ['bold' => true, 'size' => 9]);
        foreach ($warnings as $w) { $footer->addText("⚠ " . $w, ['size' => 9]); }
        $footer->addText(__('eng_notes_footer'), ['size' => 8, 'italic' => true]);
    }

    $section->addText(__('eng_report_title'), ['bold' => true, 'size' => 18], ['alignment' => 'center']);
    $section->addText(__('eng_report_subtitle'), ['bold' => true, 'size' => 14], ['alignment' => 'center']);
    $section->addTextBreak(1);

    // General Summary KPIs
    $titleTable = $section->addTable(['borderSize' => 0]);
    $titleTable->addRow();
    $titleTable->addCell(4000)->addText(__('eng_col_opt_id'));
    $titleTable->addCell(4000)->addText((string)$opt_id);

    $titleTable->addRow();
    $titleTable->addCell()->addText(__('eng_col_input_level'));
    $titleTable->addCell()->addText(($inputs['potencia_entrada'] ?? __('eng_input_normalized')));

    $titleTable->addRow();
    $titleTable->addCell()->addText(__('eng_col_min_level'));
    $titleTable->addCell()->addText(number_format((float)($summary["min_nivel_tu"] ?? 0), 2));

    $titleTable->addRow();
    $titleTable->addCell()->addText(__('eng_col_max_level'));
    $titleTable->addCell()->addText(number_format((float)($summary["max_nivel_tu"] ?? 0), 2));

    $titleTable->addRow();
    $titleTable->addCell()->addText(__('eng_col_num_tomas'));
    $titleTable->addCell()->addText((string)($summary["total_tus"] ?? count($detail)));

    $section->addTextBreak(2);

    // Resumen de Materiales por Capa
    $section->addText(__('eng_mat_summary_title'), ['bold' => true, 'size' => 12]);
    $summaryTable = $section->addTable(['borderSize' => 6, 'cellMargin' => 80, 'width' => 100 * 50, 'unit' => 'pct']);
    $summaryTable->addRow();
    $summaryTable->addCell(4000)->addText(__('report_col_layer'), ['bold' => true]);
    $summaryTable->addCell(2000)->addText(__('eng_col_cable_total'), ['bold' => true], ['alignment' => 'center']);
    $summaryTable->addCell(2000)->addText(__('eng_col_equip_uds'), ['bold' => true], ['alignment' => 'center']);
    $summaryTable->addCell(2000)->addText(__('eng_col_conn_uds'), ['bold' => true], ['alignment' => 'center']);

    $scopeSummaries = $allTotals['Scope Summaries'] ?? [];
    $engLayers = [
        'Vertical' => __('report_layer_vertical'),
        'Horizontal' => __('report_layer_horizontal'),
        'Apartamento' => __('eng_apt_int_title')
    ];
    foreach ($engLayers as $key => $lbl) {
        $d = $scopeSummaries[$key] ?? ['cable_m' => 0, 'equipment_uds' => 0, 'connectors_uds' => 0];
        $summaryTable->addRow();
        $summaryTable->addCell()->addText($lbl, ['bold' => true]);
        $summaryTable->addCell()->addText(number_format($d['cable_m'], 2) . ' m', [], ['alignment' => 'center']);
        $summaryTable->addCell()->addText(number_format($d['equipment_uds'], 0), [], ['alignment' => 'center']);
        $summaryTable->addCell()->addText(number_format($d['connectors_uds'], 0), [], ['alignment' => 'center']);
    }

    $section->addTextBreak(2);

    // Sección: Distribución Vertical
    $section->addText(__('eng_v_dist_title'), ['bold' => true, 'size' => 12]);
    $vTable = $section->addTable(['borderSize' => 6, 'cellMargin' => 80, 'width' => 100*50, 'unit' => 'pct']);
    $vTable->addRow();
    foreach([__('eng_col_scope'),__('eng_col_type'),__('eng_col_component'),__('eng_col_unit'),__('eng_col_qty'),__('eng_col_obs')] as $h) { $vTable->addCell()->addText($h, ['bold' => true]); }
    foreach ($categorizedInventory['Vertical Distribution'] as $item) {
        $vTable->addRow();
        foreach(['Scope','Tipo','Componente','Unidad','Cantidad','Observación'] as $k) { $vTable->addCell()->addText((string)($item[$k] ?? '')); }
    }

    $section->addTextBreak(2);

    // Sección: Distribución Horizontal
    $section->addText(__('eng_h_dist_title'), ['bold' => true, 'size' => 12]);
    ksort($categorizedInventory['Horizontal Distribution']);
    foreach ($categorizedInventory['Horizontal Distribution'] as $piso => $items) {
        $section->addText(__('eng_floor_label', ['piso' => $piso]), ['bold' => true, 'italic' => true]);
        $hTable = $section->addTable(['borderSize' => 6, 'cellMargin' => 80, 'width' => 100*50, 'unit' => 'pct']);
        $hTable->addRow();
        foreach([__('eng_col_type'),__('eng_col_component'),__('eng_col_unit'),__('eng_col_qty'),__('eng_col_obs')] as $h) { $hTable->addCell()->addText($h, ['bold' => true]); }
        foreach ($items as $item) {
            $hTable->addRow();
            foreach(['Tipo','Componente','Unidad','Cantidad','Observación'] as $k) { $hTable->addCell()->addText((string)($item[$k] ?? '')); }
        }
        
        // Restore Subtotal per Floor
        if (isset($allTotals['Horizontal Floor Subtotals'][$piso])) {
            $section->addText(__('eng_subtotal_floor', ['piso' => $piso]), ['bold' => true, 'italic' => true]);
            $hsTable = $section->addTable(['borderSize' => 6, 'cellMargin' => 80, 'width' => 100*50, 'unit' => 'pct']);
            foreach ($allTotals['Horizontal Floor Subtotals'][$piso] as $sub) {
                $hsTable->addRow();
                $hsTable->addCell(2000)->addText(__('eng_subtotal_label'), ['bold' => true]);
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
    $section->addText(__('eng_apt_int_title'), ['bold' => true, 'size' => 12]);
    $groupedApts = $categorizedInventory['Grouped Apartment Interior'] ?? [];
    
    foreach ($groupedApts as $group) {
        $section->addText($group['Location'], ['bold' => true, 'italic' => true]);
        $iTable = $section->addTable(['borderSize' => 6, 'cellMargin' => 80, 'width' => 100*50, 'unit' => 'pct']);
        $iTable->addRow();
        foreach([__('eng_col_type'),__('eng_col_component'),__('eng_col_unit'),__('eng_col_qty'),__('eng_col_obs')] as $h) { $iTable->addCell()->addText($h, ['bold' => true]); }
        foreach ($group['Components'] as $item) {
            $iTable->addRow();
            foreach(['Tipo','Componente','Unidad','Cantidad','Observación'] as $k) { $iTable->addCell()->addText((string)($item[$k] ?? '')); }
        }
        $section->addTextBreak(1);
    }

    $section->addPageBreak();

    // Sección: Inventario Total del Proyecto
    $section->addText(__('eng_proj_total_title'), ['bold' => true, 'size' => 12]);
    $gTable = $section->addTable(['borderSize' => 6, 'cellMargin' => 80, 'width' => 100*50, 'unit' => 'pct']);
    $gTable->addRow();
    foreach([__('eng_col_scope'),__('eng_col_type'),__('eng_col_component'),__('eng_col_unit'),__('eng_col_qty')] as $h) { $gTable->addCell()->addText($h, ['bold' => true]); }
    foreach ($allTotals['Grand Total'] as $item) {
        $gTable->addRow();
        foreach(['Scope','Tipo','Componente','Unidad','Cantidad'] as $k) { $gTable->addCell()->addText((string)($item[$k] ?? '')); }
    }

    $section->addTextBreak(2);

    // Sección: Resultados por Toma (Detalle Crítico)
    $section->addText(__('eng_tu_results_title'), ['bold' => true, 'size' => 12]);
    $tuTable = $section->addTable(['borderSize' => 6, 'cellMargin' => 80, 'width' => 100*50, 'unit' => 'pct']);
    $tuTable->addRow();
    foreach([__('report_col_tu_id'),__('report_col_piso'),__('report_col_apto'),__('report_col_level'),__('report_col_status')] as $h) { $tuTable->addCell()->addText($h, ['bold' => true]); }
    foreach ($detail as $tu) {
        $tuTable->addRow();
        $tuTable->addCell()->addText($tu['tu_id'] ?? 'N/A');
        $tuTable->addCell()->addText((string)($tu['piso'] ?? 'N/A'));
        $tuTable->addCell()->addText((string)($tu['apto'] ?? 'N/A'));
        $tuTable->addCell()->addText(number_format((float)($tu['nivel_tu'] ?? 0), 2));
        $tuTable->addCell()->addText(($tu['cumple'] ?? false) ? __('eng_status_in_range') : __('eng_status_out_of_range'));
    }
}

// --------------------------------------------------
// Output
// --------------------------------------------------
$prefix = ($mode === 'report' ? __('report_title') : __('eng_report_title'));
$prefix = str_replace(' ', '_', $prefix) . "_";
$filename = "{$prefix}{$safe_name}_{$opt_id}.docx";

header('Content-Type: application/vnd.openxmlformats-officedocument.wordprocessingml.document');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: max-age=0');

$writer = IOFactory::createWriter($phpWord, 'Word2007');
$writer->save('php://output');
exit;
