<?php
require_once __DIR__ . "/../app/config/db.php";

$opt_id = $_GET["opt_id"] ?? null;
$format = $_GET["format"] ?? "json";

if (!$opt_id) {
    die("Missing opt_id.");
}

$pdo = Database::getInstance()->getConnection();

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

    require_once "/usr/share/php/Symfony/Component/Process/autoload.php"; // Ubuntu fix
    $filename = "results_$opt_id.xlsx";

    // Generate CSV in memory
    $csv = "Parameter,Value,Unit\n";
    foreach ($rows as $r) {
        $csv .= "{$r['parameter']},{$r['value']},{$r['unit']}\n";
    }

    // Output same as Excel-friendly CSV
    header("Content-Type: text/csv");
    header("Content-Disposition: attachment; filename=$filename");
    echo $csv;
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
