<?php
require_once __DIR__ . "/../app/config/db.php";
require_once __DIR__ . "/../app/lib/tcpdf/tcpdf.php";

$opt_id = $_GET["opt_id"] ?? null;
if (!$opt_id) {
    die("Missing opt_id.");
}

$pdo = Database::getInstance()->getConnection();

/* -----------------------------
   LOAD RESULTS
------------------------------ */

$stmt = $pdo->prepare("
    SELECT parameter, value, unit, meta_json
    FROM results
    WHERE opt_id = :id
");
$stmt->execute([":id" => $opt_id]);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (!$rows) {
    die("No results found.");
}

$raw = json_decode($rows[0]["meta_json"], true);

// explode JSON structure
$general = $raw["general_parameters"] ?? [];
$apartments = $raw["apartments"] ?? [];
$tus = $raw["tus"] ?? [];
$calculated = $raw["calculated"] ?? []; // amplifier chain, losses etc.

/* -----------------------------
   START PDF
------------------------------ */

$pdf = new TCPDF();
$pdf->SetCreator("TDT Optimization");
$pdf->SetAuthor("Your System");
$pdf->SetTitle("Optimization Report #$opt_id");
$pdf->SetMargins(15, 20, 15);
$pdf->AddPage();
$pdf->SetFont("helvetica", "", 11);

/* -----------------------------
   COVER PAGE
------------------------------ */

$html = "
<h1 style='text-align:center;'>📡 Optimization Report</h1>
<h3 style='text-align:center;'>Project ID: $opt_id</h3>
<hr>
<p><b>Date:</b> " . date("Y-m-d H:i") . "</p>
<p>This document summarizes the optimization results, parameters, and signal calculations for the TDT distribution system.</p>
";

$pdf->writeHTML($html, true, false, false, false, "");

/* -----------------------------
   GENERAL PARAMETERS
------------------------------ */

$pdf->AddPage();
$html = "<h2>1. General Parameters</h2><table border='1' cellpadding='5'>";

foreach ($general as $k => $v) {
    $html .= "<tr><td><b>$k</b></td><td>{$v['value']} {$v['unit']}</td></tr>";
}

$html .= "</table>";

$pdf->writeHTML($html, true, false, false, false, "");

/* -----------------------------
   APARTMENTS TABLE
------------------------------ */

$pdf->AddPage();
$html = "<h2>2. Apartment Inputs</h2>
<table border='1' cellpadding='4'>
<tr>
    <th>Piso</th>
    <th>Apartamento</th>
    <th>TUs</th>
    <th>Derivador (m)</th>
    <th>Repartidor (m)</th>
</tr>";

foreach ($apartments as $apt) {
    $html .= "<tr>
        <td>{$apt['piso']}</td>
        <td>{$apt['apartamento']}</td>
        <td>{$apt['tus_requeridos']}</td>
        <td>{$apt['largo_cable_derivador']}</td>
        <td>{$apt['largo_cable_repartidor']}</td>
    </tr>";
}

$html .= "</table>";
$pdf->writeHTML($html, true, false, false, false, "");

/* -----------------------------
   TU CABLE TABLE
------------------------------ */

$pdf->AddPage();
$html = "<h2>3. TU Cable Inputs</h2>
<table border='1' cellpadding='4'>
<tr>
    <th>Piso</th>
    <th>Apt</th>
    <th>TU Index</th>
    <th>Length (m)</th>
</tr>";

foreach ($tus as $t) {
    $html .= "<tr>
        <td>{$t['piso']}</td>
        <td>{$t['apartamento']}</td>
        <td>{$t['tu_index']}</td>
        <td>{$t['largo_cable_tu']}</td>
    </tr>";
}

$html .= "</table>";
$pdf->writeHTML($html, true, false, false, false, "");

/* -----------------------------
   OPTIMIZATION RESULTS
------------------------------ */

$pdf->AddPage();
$html = "<h2>4. Optimization Summary</h2>
<table border='1' cellpadding='5'>
<tr><th>Parameter</th><th>Value</th><th>Unit</th></tr>";

foreach ($rows as $r) {
    $p = htmlspecialchars($r["parameter"]);
    $v = htmlspecialchars($r["value"]);
    $u = htmlspecialchars($r["unit"]);

    $html .= "<tr>
        <td>$p</td>
        <td>$v</td>
        <td>$u</td>
    </tr>";
}

$html .= "</table>";

$pdf->writeHTML($html, true, false, false, false, "");

/* -----------------------------
   OPTIONAL: DEEP CALCULATED DATA
------------------------------ */

if (!empty($calculated)) {
    $pdf->AddPage();
    $pdf->writeHTML("<h2>5. Detailed Calculations</h2>", true, false, false, false, "");

    $pdf->writeHTML("<pre>" . json_encode($calculated, JSON_PRETTY_PRINT) . "</pre>");
}

/* -----------------------------
   OUTPUT
------------------------------ */

$pdf->Output("Optimization_Report_$opt_id.pdf", "I");
exit;
