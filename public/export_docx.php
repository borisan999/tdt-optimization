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
$titleTable->addCell(4000)->addText('Optimization ID');
$titleTable->addCell(4000)->addText((string)$opt_id);

$titleTable->addRow();
$titleTable->addCell()->addText('Input Level (dBµV)');
$titleTable->addCell()->addText(
    $GLOBAL_P_IN !== null ? number_format($GLOBAL_P_IN, 2) : 'N/A'
);

$section->addTextBreak(2);

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
$tuTable->addCell()->addText('Nivel TU Final (dBµV)', ['bold' => true]);
$tuTable->addCell()->addText('Estado', ['bold' => true]);

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
        $tu['Estado'] ?? ($tu['OK'] ?? 'N/A')
    );
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
