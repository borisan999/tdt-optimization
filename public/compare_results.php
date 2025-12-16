<?php
// compare_results.php
// EPIC 2.3 – Optimization Results Comparison

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . "/../app/config/db.php";

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$DB  = new Database();
$pdo = $DB->getConnection();

$optA = intval($_GET['opt_a'] ?? 0);
$optB = intval($_GET['opt_b'] ?? 0);

if ($optA <= 0 || $optB <= 0 || $optA === $optB) {
    die('Two different optimization IDs (opt_a, opt_b) are required.');
}

function loadResult(PDO $pdo, int $optId): array {
    $sql = "
        SELECT o.opt_id, o.status, o.start_time, o.end_time,
               r.summary_json, r.detail_json
        FROM optimizations o
        JOIN results r ON r.opt_id = o.opt_id
        WHERE o.opt_id = :opt_id
        LIMIT 1
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute(['opt_id' => $optId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
        die("Optimization {$optId} not found or has no results.");
    }

    $row['summary'] = json_decode($row['summary_json'], true) ?: [];
    $row['detail']  = json_decode($row['detail_json'], true)  ?: [];

    return $row;
}

$resA = loadResult($pdo, $optA);
$resB = loadResult($pdo, $optB);

// KPI keys we expect in summary_json
$kpis = [
    'tu_total'        => 'Total TUs',
    'min_level'       => 'Min Level (dBµV)',
    'max_level'       => 'Max Level (dBµV)',
    'avg_level'       => 'Avg Level (dBµV)',
    'tu_in_spec'      => 'TU In Spec',
    'tu_low'          => 'TU Low',
    'tu_high'         => 'TU High',
    'compliance_pct'  => 'Compliance %'
];

function deltaClass($delta): string {
    if ($delta > 0) return 'delta-positive';
    if ($delta < 0) return 'delta-negative';
    return 'delta-neutral';
}

// Prepare histogram data (Nivel TU Final)
function extractLevels(array $detail): array {
    $levels = [];
    foreach ($detail as $row) {
        if (isset($row['Nivel TU Final'])) {
            $levels[] = floatval($row['Nivel TU Final']);
        }
    }
    return $levels;
}

function extractFinalLevels(array $detailJson): array
{
    $levels = [];

    foreach ($detailJson as $tu) {
        if (isset($tu['Nivel TU Final (dBµV)'])) {
            $levels[] = floatval($tu['Nivel TU Final (dBµV)']);
        }
    }

    return $levels;
}

$levelsA = extractFinalLevels($resA['detail']);
$levelsB = extractFinalLevels($resB['detail']);

if (empty($levelsA) || empty($levelsB)) {
    throw new RuntimeException(
        'Histogram data empty. Check detail_json keys.'
    );
}

function mapTUById(array $detailJson): array
{
    $map = [];
    foreach ($detailJson as $tu) {
        if (!isset($tu['Toma'])) continue;

        $map[$tu['Toma']] = [
            'level' => $tu['Nivel TU Final (dBµV)'] ?? null,
            'loss'  => $tu['Pérdida Total (dB)'] ?? null,
        ];
    }
    return $map;
}

$mapA = mapTUById($resA['detail']);
$mapB = mapTUById($resB['detail']);

$tuDiffs = [];

$allTUs = array_unique(array_merge(array_keys($mapA), array_keys($mapB)));

foreach ($allTUs as $tuId) {
    $a = $mapA[$tuId]['level'] ?? null;
    $b = $mapB[$tuId]['level'] ?? null;

    $delta = (is_numeric($a) && is_numeric($b)) ? $b - $a : null;

    $tuDiffs[] = [
        'toma'  => $tuId,
        'a'     => $a,
        'b'     => $b,
        'delta' => $delta
    ];
}

?><!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Optimization Comparison</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        .delta-positive { color: #198754; font-weight: 600; }
        .delta-negative { color: #dc3545; font-weight: 600; }
        .delta-neutral  { color: #6c757d; }
    </style>
</head>
<body class="bg-light">
<div class="container-fluid p-4">

    <h2 class="mb-3">Optimization Results Comparison</h2>

    <div class="mb-4">
        <strong>Run A:</strong> opt_id <?= htmlspecialchars($resA['opt_id']) ?> (<?= htmlspecialchars($resA['status']) ?>)<br>
        <strong>Run B:</strong> opt_id <?= htmlspecialchars($resB['opt_id']) ?> (<?= htmlspecialchars($resB['status']) ?>)
    </div>

    <!-- KPI Comparison Table -->
    <div class="card mb-4">
        <div class="card-header fw-bold">KPI Comparison</div>
        <div class="card-body p-0">
            <table class="table table-striped table-bordered mb-0">
                <thead class="table-light">
                <tr>
                    <th>KPI</th>
                    <th>Run A</th>
                    <th>Run B</th>
                    <th>Δ</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($kpis as $key => $label):
                    $a = $resA['summary'][$key] ?? null;
                    $b = $resB['summary'][$key] ?? null;
                    $delta = (is_numeric($a) && is_numeric($b)) ? $a - $b : null;
                ?>
                <tr>
                    <td><?= htmlspecialchars($label) ?></td>
                    <td><?= htmlspecialchars($a ?? '-') ?></td>
                    <td><?= htmlspecialchars($b ?? '-') ?></td>
                    <td class="<?= $delta !== null ? deltaClass($delta) : '' ?>">
                        <?= $delta !== null ? number_format($delta, 2) : '-' ?>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Histogram Comparison -->
    <div class="card mb-4">
        <div class="card-header fw-bold">Nivel TU Final Distribution</div>
        <div class="card-body">
            <canvas id="histChart" height="120"></canvas>
        </div>
    </div>

    <div class="card mt-4">
        <div class="card-header fw-bold">
            TU-by-TU Level Comparison
        </div>
        <div class="card-body p-0">
            <table class="table table-sm table-bordered mb-0 align-middle">
            <thead class="table-light">
                <tr>
                <th>TU</th>
                <th>Run A (dBµV)</th>
                <th>Run B (dBµV)</th>
                <th>Δ (B − A)</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($tuDiffs as $row): ?>
                <?php
                    $delta = $row['delta'];
                    $deltaClass =
                        $delta === null ? '' :
                        (abs($delta) < 0.1 ? 'table-success' :
                        (abs($delta) < 1.0 ? 'table-warning' : 'table-danger'));
                ?>
                <tr class="<?= $deltaClass ?>">
                    <td><?= htmlspecialchars($row['toma']) ?></td>
                    <td><?= is_numeric($row['a']) ? number_format($row['a'], 2) : '-' ?></td>
                    <td><?= is_numeric($row['b']) ? number_format($row['b'], 2) : '-' ?></td>
                    <td>
                    <?= is_numeric($delta) ? sprintf('%+.2f', $delta) : '-' ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
            </table>
        </div>
    </div>
</div>

<script>


function histogram(data, binSize) {
    if (!data.length) return { labels: [], values: [] };

    let min = Math.min(...data);
    let max = Math.max(...data);

    // CRITICAL FIX: prevent zero-range histograms
    if (min === max) {
        min -= binSize / 2;
        max += binSize / 2;
    }

    const bins = Math.max(1, Math.ceil((max - min) / binSize));
    const counts = Array(bins).fill(0);

    data.forEach(v => {
        const idx = Math.min(
            Math.floor((v - min) / binSize),
            bins - 1
        );
        counts[idx]++;
    });

    const labels = [];
    for (let i = 0; i < bins; i++) {
        const from = (min + i * binSize).toFixed(1);
        const to   = (min + (i + 1) * binSize).toFixed(1);
        labels.push(`${from} – ${to}`);
    }

    return { labels, values: counts };
}

/* const levelsA = <?= json_encode($levelsA) ?>;
const levelsB = <?= json_encode($levelsB) ?>;
 */
const levelsA = <?= json_encode($levelsA, JSON_NUMERIC_CHECK) ?>;
const levelsB = <?= json_encode($levelsB, JSON_NUMERIC_CHECK) ?>;

const binSize = 1.0; // dBµV
const hA = histogram(levelsA, binSize);
const hB = histogram(levelsB, binSize);

console.log('Levels A:', levelsA);
console.log('Levels B:', levelsB);
const ctx = document.getElementById('histChart');
const labels = hA.labels.length ? hA.labels : hB.labels;
new Chart(ctx, {
    type: 'bar',
    data: {
        labels: labels,
        datasets: [
            {
                label: 'Run A (<?= htmlspecialchars($resA['opt_id']) ?>)',
                data: hA.values,
                backgroundColor: 'rgba(13,110,253,0.5)'
            },
            {
                label: 'Run B (<?= htmlspecialchars($resB['opt_id']) ?>)',
                data: hB.values,
                backgroundColor: 'rgba(220,53,69,0.5)'
            }
        ]
    },
    options: {
        responsive: true,
        scales: {
            x: { stacked: false },
            y: { beginAtZero: true }
        }
    }
});
</script>

</body>
</html>
