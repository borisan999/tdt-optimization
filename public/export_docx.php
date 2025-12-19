<?php
declare(strict_types=1);

ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../app/config/db.php';

use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\IOFactory;

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
    SELECT summary_json, detail_json
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

if (!is_array($detail) || empty($detail)) {
    die('Invalid or empty detail_json');
}

// --------------------------------------------------
// Load optimization (for dataset_id)
// --------------------------------------------------
$st = $pdo->prepare("SELECT dataset_id FROM optimizations WHERE opt_id = :id");
$st->execute(['id' => $opt_id]);
$optimization = $st->fetch(PDO::FETCH_ASSOC);

$dataset_id = $optimization['dataset_id'] ?? 0;

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
// Fetch parametros_generales
// --------------------------------------------------
$stmt = $pdo->prepare("
    SELECT param_name, param_value , unit
    FROM parametros_generales
    WHERE dataset_id = :id
    ORDER BY param_name
");
$stmt->execute([':id' => $dataset_id]);
$parametros = $stmt->fetchAll(PDO::FETCH_ASSOC);
$paramMap = [];

foreach ($parametros as $p) {
    $paramMap[$p['param_name']] = (float)$p['param_value'];
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
$titleTable->addCell(4000)->addText('Optimization ID');
$titleTable->addCell(4000)->addText((string)$opt_id);

$titleTable->addRow();
$titleTable->addCell()->addText('Input Level (dBµV)');
$titleTable->addCell()->addText(
    $GLOBAL_P_IN !== null ? number_format($GLOBAL_P_IN, 2) : 'N/A'
);

$section->addTextBreak(2);
// --------------------------------------------------
// Parámetros Generales
// --------------------------------------------------
$section->addText(
    '2. Parámetros Generales',
    ['bold' => true, 'size' => 12]
);

$table = $section->addTable([
    'borderSize' => 6,
    'borderColor' => '000000'
]);

$table->addRow();
$table->addCell()->addText('Parámetro', ['bold' => true]);
$table->addCell()->addText('Valor', ['bold' => true]);
$table->addCell()->addText('Unidad', ['bold' => true]);

foreach ($parametros as $p) {
    $table->addRow();
    $table->addCell()->addText($p['param_name']);
    $table->addCell()->addText((string)$p['param_value']);
    $table->addCell()->addText((string)$p['unit']);
}

$section->addTextBreak(1);

// --------------------------------------------------
// TU Results (engineering-critical subset)
// --------------------------------------------------
$section->addText(
    'Resultados por Toma (Subset Crítico)',
    ['bold' => true, 'size' => 12]
);

$tuTable = $section->addTable([
    'borderSize' => 6,
    'borderColor' => '000000'
]);

$tuTable->addRow();
$tuTable->addCell()->addText('Toma', ['bold' => true]);
$tuTable->addCell()->addText('Piso', ['bold' => true]);
$tuTable->addCell()->addText('Apto', ['bold' => true]);
$tuTable->addCell()->addText('Bloque', ['bold' => true]);
$tuTable->addCell()->addText('Pérdida Total (dB)', ['bold' => true]);
$tuTable->addCell()->addText('P_in (dBµV)', ['bold' => true]);
$tuTable->addCell()->addText('Nivel TU Final (dBµV)', ['bold' => true]);
$tuTable->addCell()->addText('Margen (dB)', ['bold' => true]);
$tuTable->addCell()->addText('Estado', ['bold' => true]);

foreach ($detail as $tu) {
    $tuTable->addRow();
    $tuTable->addCell()->addText($tu['Toma'] ?? 'N/A');
    $tuTable->addCell()->addText($tu['Piso'] ?? 'N/A');
    $tuTable->addCell()->addText($tu['Apto'] ?? 'N/A');
    $tuTable->addCell()->addText($tu['Bloque'] ?? 'N/A');

    $tuTable->addCell()->addText(
        isset($tu['Pérdida Total (dB)'])
            ? number_format((float)$tu['Pérdida Total (dB)'], 2)
            : 'N/A'
    );

    $tuTable->addCell()->addText(
        isset($tu['P_in (entrada) (dBµV)'])
            ? number_format((float)$tu['P_in (entrada) (dBµV)'], 2)
            : ($GLOBAL_P_IN !== null ? number_format($GLOBAL_P_IN, 2) : 'N/A')
    );

    $tuTable->addCell()->addText(
        isset($tu['Nivel TU Final (dBµV)'])
            ? number_format((float)$tu['Nivel TU Final (dBµV)'], 2)
            : 'N/A'
    );
    $finalLevel = isset($tu['Nivel TU Final (dBµV)'])
    ? (float)$tu['Nivel TU Final (dBµV)']
    : null;

    $potenciaObjetivo = $paramMap['Potencia_Objetivo_TU_dBuV'];
    $nivelMinimo      = $paramMap['Nivel_Minimo_dBuV'];
    $nivelMaximo      = $paramMap['Nivel_Maximo_dBuV'];

    $margin = $finalLevel - $potenciaObjetivo;
    
    $tuTable->addCell()->addText($margin);

    $status = (
        $finalLevel >= $nivelMinimo &&
        $finalLevel <= $nivelMaximo
    ) ? 'OK' : 'FAIL';
    $tuTable->addCell()->addText($status);
}

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
