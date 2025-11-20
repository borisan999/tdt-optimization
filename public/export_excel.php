<?php
require_once __DIR__ . "/../app/config/db.php";
require_once __DIR__ . "/../vendor/autoload.php";

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

$opt_id = $_GET["opt_id"] ?? null;
if (!$opt_id) die("Missing opt_id");

$pdo = Database::getInstance()->getConnection();

/* ---------------------------
   LOAD RESULTS META
---------------------------- */

$stmt = $pdo->prepare("SELECT parameter, value, unit, meta_json
                       FROM results
                       WHERE opt_id = :id");
$stmt->execute([":id" => $opt_id]);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (!$rows) die("Not found");

$meta = json_decode($rows[0]["meta_json"], true);

$general     = $meta["general_parameters"] ?? [];
$apartments  = $meta["apartments"] ?? [];
$tus         = $meta["tus"] ?? [];
$calculated  = $meta["calculated"] ?? [];

/* ---------------------------
   CREATE SPREADSHEET
---------------------------- */

$spreadsheet = new Spreadsheet();

/* -----------------------------------
   SHEET 1: GENERAL PARAMETERS
------------------------------------ */

$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle("General Parameters");

$sheet->setCellValue("A1", "Parameter");
$sheet->setCellValue("B1", "Value");
$sheet->setCellValue("C1", "Unit");

$row = 2;
foreach ($general as $param => $data) {
    $sheet->setCellValue("A$row", $param);
    $sheet->setCellValue("B$row", $data["value"]);
    $sheet->setCellValue("C$row", $data["unit"]);
    $row++;
}

/* -----------------------------------
   SHEET 2: APARTMENTS
------------------------------------ */

$sheet2 = $spreadsheet->createSheet();
$sheet2->setTitle("Apartments");

$headers = [
    "piso", "apartamento", "tus_requeridos",
    "largo_cable_derivador", "largo_cable_repartidor"
];

$col = "A";
foreach ($headers as $h) {
    $sheet2->setCellValue($col . "1", $h);
    $col++;
}

$row = 2;
foreach ($apartments as $apt) {
    $col = "A";
    foreach ($headers as $h) {
        $sheet2->setCellValue($col . $row, $apt[$h]);
        $col++;
    }
    $row++;
}

/* -----------------------------------
   SHEET 3: TU CABLES
------------------------------------ */

$sheet3 = $spreadsheet->createSheet();
$sheet3->setTitle("TU Cables");

$headers = ["piso", "apartamento", "tu_index", "largo_cable_tu"];

$col = "A";
foreach ($headers as $h) {
    $sheet3->setCellValue($col . "1", $h);
    $col++;
}

$row = 2;
foreach ($tus as $t) {
    $col = "A";
    foreach ($headers as $h) {
        $sheet3->setCellValue($col . $row, $t[$h]);
        $col++;
    }
    $row++;
}

/* -----------------------------------
   SHEET 4: OPTIMIZATION RESULTS
------------------------------------ */

$sheet4 = $spreadsheet->createSheet();
$sheet4->setTitle("Optimization Results");

$sheet4->setCellValue("A1", "Parameter");
$sheet4->setCellValue("B1", "Value");
$sheet4->setCellValue("C1", "Unit");

$row = 2;
foreach ($rows as $r) {
    $sheet4->setCellValue("A$row", $r["parameter"]);
    $sheet4->setCellValue("B$row", $r["value"]);
    $sheet4->setCellValue("C$row", $r["unit"]);
    $row++;
}

/* -----------------------------------
   SHEET 5: DETAILED CALCULATIONS
------------------------------------ */

$sheet5 = $spreadsheet->createSheet();
$sheet5->setTitle("Detailed Calc");

$sheet5->setCellValue("A1", "JSON");
$sheet5->setCellValue("A2", json_encode($calculated, JSON_PRETTY_PRINT));

$sheet5->getColumnDimension("A")->setWidth(120);

/* -----------------------------------
   OUTPUT FILE
------------------------------------ */

$filename = "Optimization_$opt_id.xlsx";

header("Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet");
header("Content-Disposition: attachment; filename=\"$filename\"");
header("Cache-Control: max-age=0");

$writer = new Xlsx($spreadsheet);
$writer->save("php://output");
exit;
