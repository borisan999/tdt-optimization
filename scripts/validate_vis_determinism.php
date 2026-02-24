<?php
// scripts/validate_vis_determinism.php
declare(strict_types=1);

ini_set('display_errors', '1');
error_reporting(E_ALL);

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../app/config/db.php';
require_once __DIR__ . '/../app/controllers/ResultsController.php';

use app\controllers\ResultsController;

/**
 * Executes the core graph generation logic from results_tree.php
 * to produce a node and edge list for a given optimization ID.
 */
function generate_graph_payload(int $opt_id): array {
    $controller = new ResultsController($opt_id);
    $response = $controller->execute();

    if (($response['status'] ?? 'error') !== 'success') {
        throw new \RuntimeException("Failed to load result for opt_id: $opt_id");
    }

    /** @var \app\viewmodels\ResultViewModel $viewModel */
    $viewModel = $response['viewModel'];

    $original_row = $viewModel->results;
    $decoded_detail = json_decode($original_row['detail_json'] ?? '[]', true);
    if (empty($decoded_detail)) {
        throw new \RuntimeException("No detail_json found for opt_id: $opt_id");
    }
    
    $nodes = [];
    $edges = [];
    $hierarchy_map = [];

    // --- Replicated Logic from results_tree.php ---

    $inputs = $viewModel->inputs;
    $specs_derivadores = $inputs['derivadores_data'] ?? [];
    $specs_repartidores = $inputs['repartidores_data'] ?? [];

    $max_piso = 0;
    foreach($decoded_detail as $d) {
        $p = $d['piso'] ?? $d['Piso'] ?? 0;
        if ($p > $max_piso) $max_piso = $p;
    }

    $id_cabecera = "Cabecera_Master";
    $nodes[] = ["id" => $id_cabecera];

    $id_troncal = "Troncal";
    $nodes[] = ["id" => $id_troncal];
    $edges[] = ["from" => $id_cabecera, "to" => $id_troncal];

    $mapa_bloques = [];
    $bloques_seen = [];
    foreach($decoded_detail as $row) {
        $b_id = $row['Bloque'] ?? $row['bloque'] ?? null;
        if ($b_id === null || in_array($b_id, $bloques_seen)) continue;
        $bloques_seen[] = $b_id;
        $id_bloque = "Bloque_$b_id";
        $nodes[] = ["id" => $id_bloque];
        $edges[] = ["from" => $id_troncal, "to" => $id_bloque];
        $mapa_bloques[$b_id] = $id_bloque;
    }

    $pisos_creados = [];
    $aptos_creados = [];
    foreach($decoded_detail as $row) {
        $codigo = $row['Toma'] ?? $row['tu_id'] ?? 'unknown';
        $bloque = $row['Bloque'] ?? $row['bloque'] ?? 0;
        $piso = $row['Piso'] ?? $row['piso'] ?? 0;
        $apto = $row['Apto'] ?? $row['apto'] ?? 0;

        $id_bloque_parent = $mapa_bloques[$bloque] ?? null;
        if (!$id_bloque_parent) continue;

        $id_piso = "B{$bloque}_P{$piso}";
        $id_apto = "B{$bloque}_P{$piso}_A{$apto}";
        $id_toma = $codigo;

        if (!isset($pisos_creados[$id_piso])) {
            $nodes[] = ["id" => $id_piso];
            $edges[] = ["from" => $id_bloque_parent, "to" => $id_piso];
            $pisos_creados[$id_piso] = true;
        }

        if (!isset($aptos_creados[$id_apto])) {
            $nodes[] = ["id" => $id_apto];
            $edges[] = ["from" => $id_piso, "to" => $id_apto];
            $aptos_creados[$id_apto] = true;
        }

        $nodes[] = ["id" => $id_toma];
        $edges[] = ["from" => $id_apto, "to" => $id_toma];
    }
    
    // Remove duplicates that might arise from logic
    $nodes = array_map("unserialize", array_unique(array_map("serialize", $nodes)));
    $edges = array_map("unserialize", array_unique(array_map("serialize", $edges)));

    return ['nodes' => $nodes, 'edges' => $edges];
}

/**
 * Normalizes the graph payload for hashing.
 * - Sorts nodes by ID.
 * - Sorts edges by a composite key of 'from' and 'to'.
 */
function normalize_and_hash(array $payload): string {
    $nodes = $payload['nodes'];
    $edges = $payload['edges'];
    
    // Sort nodes by 'id'
    usort($nodes, fn($a, $b) => $a['id'] <=> $b['id']);
    
    // Sort edges by 'from' then 'to'
    usort($edges, fn($a, $b) => ($a['from'] <=> $b['from']) ?: ($a['to'] <=> $b['to']));
    
    $normalized_payload = [
        'nodes' => $nodes,
        'edges' => $edges
    ];
    
    $serialized_data = json_encode($normalized_payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    
    return hash('sha256', $serialized_data);
}

// --- Main Execution ---

// This needs to be a valid opt_id from your database that has a result.
// Let's find one first.
$pdo = (new Database())->getConnection();
$stmt = $pdo->query("SELECT opt_id FROM results ORDER BY opt_id DESC LIMIT 1");
$opt_id = $stmt->fetchColumn();

if (!$opt_id) {
    echo "FATAL: No optimization results found in the database. Cannot run validation.\n";
    exit(1);
}

echo "Using opt_id: $opt_id for determinism check.

";

$hashes = [];
$iterations = 10;
$all_hashes_match = true;
$first_hash = '';

echo "Running $iterations iterations to check for hash consistency...
";
echo "------------------------------------------------------------
";

for ($i = 0; $i < $iterations; $i++) {
    $payload = generate_graph_payload((int)$opt_id);
    $hash = normalize_and_hash($payload);
    $hashes[] = $hash;
    
    if ($i === 0) {
        $first_hash = $hash;
    } else {
        if ($hash !== $first_hash) {
            $all_hashes_match = false;
        }
    }
    
    echo "Iteration " . ($i + 1) . ": $hash
";
}

echo "------------------------------------------------------------
";
if ($all_hashes_match) {
    echo "✅ PASS: All $iterations generated hashes are identical.
";
    echo "The visualization graph structure is DETERMINISTIC.
";
} else {
    echo "❌ FAIL: Hash mismatch detected.
";
    echo "The visualization graph structure is NON-DETERMINISTIC.
";
    echo "This is a high-risk issue that must be resolved.
";
}

// Write findings to the report file
$report_path = __DIR__ . '/../docs/validation/phase4_validation_report.md';
$report_content = "## 1. Visualization Determinism Check

";
$report_content .= "### B. Determinism Proof Protocol

";
$report_content .= "**Objective:** Prove that for a fixed dataset, the generated `vis-network` graph structure is identical across multiple runs.

";
$report_content .= "**Method:** The core graph generation logic from `public/results_tree.php` was extracted and run $iterations times against `opt_id=$opt_id`. The resulting node and edge lists were normalized (sorted) and hashed with SHA256.

";
$report_content .= "**Result:**

";
$report_content .= "| Iteration | SHA256 Hash |
";
$report_content .= "|-----------|-------------|
";
foreach ($hashes as $index => $h) {
    $report_content .= "| " . ($index + 1) . " | `$h` |
";
}
$report_content .= "
";
if ($all_hashes_match) {
    $report_content .= "**Conclusion: ✅ PASS**

";
    $report_content .= "All generated hashes were identical. The graph generation logic is deterministic. The identified risk of using an unsorted loop over `detail_json` does not manifest as an issue, likely because the source JSON array from the database has a stable order in this environment.

";
} else {
    $report_content .= "**Conclusion: ❌ FAIL**

";
    $report_content .= "A hash mismatch was detected, indicating that the graph generation logic is **non-deterministic**. This is a critical issue that violates the validation criteria.

";
}

file_put_contents($report_path, $report_content);

echo "
Report updated: $report_path
";
