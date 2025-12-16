<?php
// public/get_results.php
// Returns full result data for a given optimization

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . "/../config/db.php";

header("Content-Type: application/json");

// -----------------------------
// Validate request
// -----------------------------
$opt_id = isset($_GET['opt_id']) ? intval($_GET['opt_id']) : 0;

if ($opt_id <= 0) {
    echo json_encode(["error" => "Invalid or missing opt_id"]);
    exit;
}

$db = new Database();
$pdo = $db->getConnection();

// -----------------------------
// Fetch optimization metadata
// -----------------------------
$stmtOpt = $pdo->prepare("
    SELECT opt_id,   status, created_at
    FROM optimizations
    WHERE opt_id = :id
    LIMIT 1
");
$stmtOpt->execute([":id" => $opt_id]);
$optimization = $stmtOpt->fetch(PDO::FETCH_ASSOC);

if (!$optimization) {
    echo json_encode(["error" => "Optimization not found"]);
    exit;
}

// -----------------------------
// Fetch all result rows for this optimization
// -----------------------------
$stmtResults = $pdo->prepare("
    SELECT 
        result_id,
        parameter,
        value,
        summary_json,
        detail_json,
        inputs_json,
        unit,
        deviation,
        meta_json,
        created_at
    FROM results
    WHERE opt_id = :id
    ORDER BY result_id ASC
");
$stmtResults->execute([":id" => $opt_id]);
$rows = $stmtResults->fetchAll(PDO::FETCH_ASSOC);

// Final structured dataset
$data = [
    "optimization" => $optimization,
    "summary" => null,
    "details" => null,
    "inputs" => null,
    "results" => []
];

// -----------------------------
// Normalize each row
// -----------------------------
foreach ($rows as $r) {

    // If any row contains summary_json, keep it
    if ($r["summary_json"] !== null && $data["summary"] === null) {
        $data["summary"] = json_decode($r["summary_json"], true);
    }

    // If any row contains detail_json, keep it
    if ($r["detail_json"] !== null && $data["details"] === null) {
        $data["details"] = json_decode($r["detail_json"], true);
    }

    // If any row contains inputs_json, keep it
    if ($r["inputs_json"] !== null && $data["inputs"] === null) {
        $data["inputs"] = json_decode($r["inputs_json"], true);
    }

    // Push parameter-level results
    $data["results"][] = [
        "result_id" => intval($r["result_id"]),
        "parameter" => $r["parameter"],
        "value" => is_numeric($r["value"]) ? floatval($r["value"]) : $r["value"],
        "unit" => $r["unit"],
        "deviation" => $r["deviation"] !== null ? floatval($r["deviation"]) : null,
        "meta" => $r["meta_json"] ? json_decode($r["meta_json"], true) : null,
        "created_at" => $r["created_at"]
    ];
}

// -----------------------------
// Output JSON
// -----------------------------
echo json_encode($data, JSON_PRETTY_PRINT);
