<?php
require_once __DIR__ . "/../app/config/db.php";
require_once __DIR__ . "/../vendor/autoload.php";
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

$opt_id = $_GET["opt_id"] ?? null;
$format = $_GET["format"] ?? "json";

if (!$opt_id) {
    die("Missing opt_id.");
}

$db = new Database();
$pdo = $db->getConnection(); 
$stmt = $pdo->prepare("SELECT * FROM results WHERE opt_id = :id");
$stmt->execute([":id" => $opt_id]);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (!$rows) {
    die("No results found.");
}

// Shared JSON (from first row)
$raw_json = $rows[0]["meta_json"];

// ------------------------
//  RAW JSON EXPORT
// ------------------------
if ($format === "json") {
    header("Content-Type: application/json");
    header("Content-Disposition: attachment; filename=results_$opt_id.json");
    echo $raw_json;
    exit;
}

// ------------------------
//  EXCEL EXPORT
// ------------------------
if ($format === "excel") {

    // -------------------------
    // EXPORTACIÓN EXCEL REAL
    // -------------------------


    // Crear workbook
    $spreadsheet = new Spreadsheet();

    /* -----------------------------------
       HOJA 1: Resultados (tabla results)
    ------------------------------------ */

    $sheet1 = $spreadsheet->getActiveSheet();
    $sheet1->setTitle("Resultados");

    $sheet1->setCellValue("A1", "Parameter");
    $sheet1->setCellValue("B1", "Value");
    $sheet1->setCellValue("C1", "Unit");

    $row = 2;
    foreach ($rows as $r) {
        $sheet1->setCellValue("A{$row}", $r["parameter"]);
        $sheet1->setCellValue("B{$row}", $r["value"]);
        $sheet1->setCellValue("C{$row}", $r["unit"]);
        $row++;
    }

    /* -----------------------------------
       OBTENER dataset_id DESDE results
    ------------------------------------ */
    $dataset_id = null;
    foreach ($rows as $r) {
        if ($r["parameter"] === "dataset_id") {
            $dataset_id = (int)$r["value"];
            break;
        }
    }

    if (!$dataset_id) {
        // Intentar desde meta_json
        $meta = json_decode($rows[0]["meta_json"], true);
        if (isset($meta["dataset_id"])) $dataset_id = $meta["dataset_id"];
    }

    if (!$dataset_id) {
        // Si aún falta dataset_id → cerrar pero exportar lo que tengamos
        $writer = new Xlsx($spreadsheet);
        header("Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet");
        header("Content-Disposition: attachment; filename=results_$opt_id.xlsx");
        $writer->save("php://output");
        exit;
    }

    /* -----------------------------------
       HOJA 2: Parámetros Generales
    ------------------------------------ */

    $sheet2 = $spreadsheet->createSheet();
    $sheet2->setTitle("Input – Parámetros");

    $stmt = $pdo->prepare("SELECT param_name, param_value FROM parametros_generales WHERE dataset_id = :ds");
    $stmt->execute([":ds" => $dataset_id]);
    $params = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $sheet2->setCellValue("A1", "param_name");
    $sheet2->setCellValue("B1", "param_value");

    $row = 2;
    foreach ($params as $p) {
        $sheet2->setCellValue("A{$row}", $p["param_name"]);
        $sheet2->setCellValue("B{$row}", $p["param_value"]);
        $row++;
    }

    /* -----------------------------------
       HOJA 3: Apartments (dataset_rows)
    ------------------------------------ */

    $sheet3 = $spreadsheet->createSheet();
    $sheet3->setTitle("Input – Apartments");

    $sheet3->fromArray(["piso","apartamento","tus_requeridos","largo_cable_derivador","largo_cable_repartidor"], NULL, "A1");

    $stmt = $pdo->prepare("SELECT * FROM dataset_rows WHERE dataset_id = :ds ORDER BY record_index,row_id");
    $stmt->execute([":ds" => $dataset_id]);
    $raw_rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $groups = [];
    foreach ($raw_rows as $r) {
        $groups[$r["record_index"]][$r["field_name"]] = $r["field_value"];
    }

    $row = 2;
    foreach ($groups as $g) {
        if (isset($g["tu_index"])) continue; // no es apartment
        $sheet3->fromArray([
            (int)($g["piso"] ?? 0),
            (int)($g["apartamento"] ?? 0),
            (int)($g["tus_requeridos"] ?? 0),
            (float)($g["largo_cable_derivador"] ?? 0),
            (float)($g["largo_cable_repartidor"] ?? 0)
        ], NULL, "A$row");
        $row++;
    }

    /* -----------------------------------
       HOJA 4: TU (dataset_rows)
    ------------------------------------ */

    $sheet4 = $spreadsheet->createSheet();
    $sheet4->setTitle("Input – TU");

    $sheet4->fromArray(["piso","apartamento","tu_index","largo_cable_tu"], NULL, "A1");

    $row = 2;
    foreach ($groups as $g) {
        if (!isset($g["tu_index"])) continue;
        $sheet4->fromArray([
            (int)($g["piso"] ?? 0),
            (int)($g["apartamento"] ?? 0),
            (int)($g["tu_index"] ?? 0),
            (float)($g["largo_cable_tu"] ?? 0)
        ], NULL, "A$row");
        $row++;
    }

    /* -----------------------------------
       HOJA 5: Derivadores
    ------------------------------------ */

    $sheet5 = $spreadsheet->createSheet();
    $sheet5->setTitle("Input – Derivadores");

    $stmt = $pdo->query("SELECT * FROM derivadores");
    $der = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if ($der) {
        $sheet5->fromArray(array_keys($der[0]), NULL, "A1");
        $sheet5->fromArray($der, NULL, "A2");
    } else {
        $sheet5->setCellValue("A1", "No hay derivadores en la tabla.");
    }

    /* -----------------------------------
       HOJA 6: Repartidores
    ------------------------------------ */

    $sheet6 = $spreadsheet->createSheet();
    $sheet6->setTitle("Input – Repartidores");

    $stmt = $pdo->query("SELECT * FROM repartidores");
    $rep = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if ($rep) {
        $sheet6->fromArray(array_keys($rep[0]), NULL, "A1");
        $sheet6->fromArray($rep, NULL, "A2");
    } else {
        $sheet6->setCellValue("A1", "No hay repartidores en la tabla.");
    }

    /* -----------------------------------
       HOJA 7: Cálculos detallados (meta_json)
    ------------------------------------ */

    $sheet7 = $spreadsheet->createSheet();
    $sheet7->setTitle("Cálculos detallados");

    $sheet7->setCellValue("A1", "meta_json");
    $sheet7->setCellValue("A2", json_encode(json_decode($raw_json, true), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    $sheet7->getColumnDimension("A")->setWidth(100);

    /* -----------------------------------
       SALIDA FINAL
    ------------------------------------ */

    header("Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet");
    header("Content-Disposition: attachment; filename=results_$opt_id.xlsx");

    $writer = new Xlsx($spreadsheet);
    $writer->save("php://output");
    exit;
}

// ------------------------
//  PDF EXPORT
// ------------------------
if ($format === "pdf") {

    require_once __DIR__ . "/../app/lib/tcpdf/tcpdf.php";

    $pdf = new TCPDF();
    $pdf->AddPage();
    $pdf->SetFont("helvetica", "", 10);

    $pdf->Write(0, "Optimization Results #$opt_id", "", 0, "L", true, 0, false, false, 0);
    $pdf->Ln(4);

    $html = "<table border='1' cellpadding='4'>
        <tr><th><b>Parameter</b></th><th><b>Value</b></th><th><b>Unit</b></th></tr>";

    foreach ($rows as $r) {
        $html .= "<tr>
            <td>{$r['parameter']}</td>
            <td>{$r['value']}</td>
            <td>{$r['unit']}</td>
        </tr>";
    }

    $html .= "</table>";

    $pdf->writeHTML($html, true, false, false, false, "");

    $pdf->Output("results_$opt_id.pdf", "D");
    exit;
}

die("Unknown export format.");
