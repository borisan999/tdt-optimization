<?php
require_once __DIR__ . "/../app/config/db.php";

$opt_id = $_GET["opt_id"] ?? null;
if (!$opt_id) {
    die("Missing opt_id.");
}
        $db = new Database();
        $pdo = $db->getConnection(); 

// Fetch all rows for this optimization
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

// meta_json is identical for all rows (we saved the full JSON each time)
$raw_json = $rows[0]["meta_json"];
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Optimization Results</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
</head>

<body class="bg-light">

<div class="container mt-4">

    <h2 class="mb-3">Optimization Results #<?= htmlspecialchars($opt_id) ?></h2>

    <div class="mb-3">
        <a href="results_export.php?opt_id=<?= $opt_id ?>&format=excel"
           class="btn btn-success btn-sm">⬇ Export Excel</a>

        <a href="export_excel.php?opt_id=<?= $opt_id ?>" 
        class="btn btn-success btn-sm">
            📊 Export Excel
        </a>


        <a href="results_export.php?opt_id=<?= $opt_id ?>&format=json"
           class="btn btn-secondary btn-sm">⬇ Raw JSON</a>

        <a href="results_report.php?opt_id=<?= $opt_id ?>" 
            class="btn btn-warning btn-sm">📄 View Full Report</a>

        <a href="enter_data.php" class="btn btn-dark btn-sm float-end">🔙 Back</a>
    </div>

    <div class="card mb-4">
        <div class="card-header bg-primary text-white">Summary Table</div>
        <div class="card-body">

            <table class="table table-bordered">
                <thead>
                    <tr>
                        <th>Parameter</th>
                        <th>Value</th>
                        <th>Unit</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($rows as $r): ?>
                    <tr>
                        <td><?= htmlspecialchars($r["parameter"]) ?></td>
                        <td><?= htmlspecialchars($r["value"]) ?></td>
                        <td><?= htmlspecialchars($r["unit"]) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>

        </div>
    </div>

    <!-- Raw JSON Viewer -->
    <div class="card mb-4">
        <div class="card-header bg-secondary text-white">Raw JSON Output (Python)</div>
        <div class="card-body">
            <pre style="background:#f0f0f0; padding:10px; border-radius:6px;">
<?= json_encode(json_decode($raw_json), JSON_PRETTY_PRINT); ?>
            </pre>
        </div>
    </div>

</div>

</body>
</html>
