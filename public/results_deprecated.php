<?php
// public/results.php - Results dashboard for optimization (backwards-compatible)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . "/../app/config/db.php";

if (session_status() === PHP_SESSION_NONE) session_start();

$opt_id = intval($_GET['opt_id'] ?? 0);
if ($opt_id <= 0) {
    die('Optimization id required. Use ?opt_id=###');
}

$debug = isset($_GET['debug']) && $_GET['debug'] == '1';

$db = new Database();
$pdo = $db->getConnection();

// ----------------------
// 1) Load summary rows
// ----------------------
$summary_rows = [];
try {
    $stmt = $pdo->prepare("SELECT parameter, value, unit, deviation, meta_json, created_at 
                           FROM results 
                           WHERE opt_id = :opt_id
                           ORDER BY result_id ASC");
    $stmt->execute([':opt_id' => $opt_id]);
    $summary_rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    if ($debug) echo "<pre>Summary query error: " . $e->getMessage() . "</pre>";
    $summary_rows = [];
}

// build a lookup for summary values
$summary = [];
$meta_from_summary = null;
foreach ($summary_rows as $r) {
    $param = $r['parameter'] ?? null;
    $val = $r['value'] ?? null;
    $summary[$param] = [
        'value' => $val,
        'unit' => $r['unit'] ?? null,
        'deviation' => $r['deviation'] ?? null,
        'meta_json' => $r['meta_json'] ?? null
    ];
    if (!$meta_from_summary && !empty($r['meta_json'])) {
        $decoded = json_decode($r['meta_json'], true);
        if ($decoded) $meta_from_summary = $decoded;
    }
}

// ----------------------
// 2) Try load normalized TU rows from results_detail
// ----------------------
$tu_rows = [];
$detail_rows = [];
try {
    $stmt = $pdo->prepare("SELECT id, opt_id , name, value, unit, deviation,meta 
                           FROM results_detail
                           WHERE opt_id = :opt_id
                           ORDER BY id ASC, name ASC");
    $stmt->execute([':opt_id' => $opt_id]);
    $detail_rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    // results_detail might not exist or different schema - we'll fallback
    if ($debug) echo "<pre>Detail query error (this may be normal): " . $e->getMessage() . "</pre>";
    $detail_rows = [];
}

// If results_detail provided rows, convert to tu_rows
if (!empty($detail_rows)) {
    foreach ($detail_rows as $dr) {
        $tu_id = $dr['id'] ?? ('tu_' . ($dr['created_at'] ?? uniqid()));
        $param = $dr['param'] ?? 'value';
        $value = $dr['value'] ?? null;
        $meta = null;
        if (!empty($dr['meta_json'])) {
            $meta = json_decode($dr['meta_json'], true) ?: null;
        }
        if (!isset($tu_rows[$tu_id])) $tu_rows[$tu_id] = ['tu_id' => $tu_id, 'meta' => [], 'params' => []];
        $tu_rows[$tu_id]['params'][$param] = $value;
        if ($meta) $tu_rows[$tu_id]['meta'] = array_merge($tu_rows[$tu_id]['meta'], $meta);
    }
} else {
    // ----------------------
    // 3) Fallback: scan results table for parameters like 'nivel_%' and build TU rows
    // ----------------------
    try {
        $stmt = $pdo->prepare("SELECT parameter, value, deviation, meta_json FROM results WHERE opt_id = :opt_id AND parameter LIKE 'nivel_%' ORDER BY result_id ASC");
        $stmt->execute([':opt_id' => $opt_id]);
        $nivel_rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
        if ($debug) echo "<pre>Fallback nivel query error: " . $e->getMessage() . "</pre>";
        $nivel_rows = [];
    }

    foreach ($nivel_rows as $nr) {
        $parameter = $nr['parameter'] ?? '';
        $value = $nr['value'] ?? null;
        $deviation = $nr['deviation'] ?? null;
        $meta = null;
        if (!empty($nr['meta_json'])) {
            $meta = json_decode($nr['meta_json'], true) ?: null;
        }

        // extract piso, apt, tu via regex variants
        /*if (preg_match('/p(?:iso)?[_-]?(\d+)[^0-9a-zA-Z]*a?[_-]?(\d+)[^0-9a-zA-Z]*t[_-]?(\d*)/i', $parameter, $m)) {
            $piso = $m[1];
            $apt = $m[2];
            $tu_index = $m[3] !== '' ? $m[3] : '1';
            $tu_id = sprintf('P%s-A%s-T%s', $piso, $apt, $tu_index);
        } else {
            // fallback: use the raw parameter as id
            $tu_id = $parameter ?: ('tu_' . uniqid());
            $piso = null; $apt = null; $tu_index = null;
        }*/
        if (preg_match('/nivel_p(\d+)_a(\d+)_t(\d+)/i', $parameter, $m)) {
            $piso = $m[1];
            $apt = $m[2];
            $tu_index = $m[3];
            $tu_id = sprintf('P%s-A%s-T%s', $piso, $apt, $tu_index);
        } else {
            $tu_id = $parameter ?: ('tu_' . uniqid());
            $piso = null; 
            $apt = null; 
            $tu_index = null;
        }

        if (!isset($tu_rows[$tu_id])) {
            $tu_rows[$tu_id] = ['tu_id' => $tu_id, 'meta' => [], 'params' => []];
        }

        $tu_rows[$tu_id]['params']['nivel'] = is_numeric($value) ? floatval($value) : $value;
        $tu_rows[$tu_id]['params']['deviation'] = $deviation;
        if ($meta) $tu_rows[$tu_id]['meta'] = array_merge($tu_rows[$tu_id]['meta'], $meta);

        if (isset($piso)) $tu_rows[$tu_id]['meta']['piso'] = $piso;
        if (isset($apt)) $tu_rows[$tu_id]['meta']['apartamento'] = $apt;
        if (isset($tu_index)) $tu_rows[$tu_id]['meta']['tu_index'] = $tu_index;
    }
}

// sort TUs by natural piso-apt-tu if possible
uksort($tu_rows, function($a, $b){
    $parse = function($s){
        if (preg_match('/P(\d+)-A(\d+)-T(\d+)/i', $s, $m)) {
            return [intval($m[1]), intval($m[2]), intval($m[3])];
        }
        return [PHP_INT_MAX, PHP_INT_MAX, PHP_INT_MAX];
    };
    $pa = $parse($a); $pb = $parse($b);
    if ($pa[0] !== $pb[0]) return $pa[0] - $pb[0];
    if ($pa[1] !== $pb[1]) return $pa[1] - $pb[1];
    return $pa[2] - $pb[2];
});

// ----------------------
// 4) Extract chosen lists & block summaries from summary rows or meta_from_summary
// ----------------------
$chosen_derivadores = [];
$chosen_repartidores = [];
$stats = [];
$block_summaries = [];

if ($meta_from_summary) {
    if (isset($meta_from_summary['chosen_derivadores'])) {
        $chosen_derivadores = $meta_from_summary['chosen_derivadores'];
    }
    if (isset($meta_from_summary['chosen_repartidores'])) {
        $chosen_repartidores = $meta_from_summary['chosen_repartidores'];
    }
    if (isset($meta_from_summary['stats'])) {
        $stats = $meta_from_summary['stats'];
    }
    if (isset($meta_from_summary['blocks'])) {
        $block_summaries = $meta_from_summary['blocks'];
    }
}

// Also check specific summary rows saved as stats_*
foreach ($summary as $param => $info) {
    if ($param === null) continue;
    if (strpos($param, 'stats_') === 0) {
        $stats[$param] = $info['value'];
    }
    if (strpos($param, 'block_') === 0 && strpos($param, '_summary') !== false) {
        if (!empty($info['meta_json'])) {
            $b = json_decode($info['meta_json'], true);
            if ($b) {
                $block_summaries[$param] = $b;
            }
        }
    }
    if (!empty($info['meta_json'])) {
        $dec = json_decode($info['meta_json'], true);
        if ($dec) {
            if (isset($dec['chosen_derivadores'])) $chosen_derivadores = $dec['chosen_derivadores'];
            if (isset($dec['chosen_repartidores'])) $chosen_repartidores = $dec['chosen_repartidores'];
            if (isset($dec['stats'])) $stats = array_merge($stats, $dec['stats']);
        }
    }
}

// ----------------------
// 5) Build arrays for charts & stats
// ----------------------
$tu_count = count($tu_rows);
$levels = [];
$tu_table = []; // rows for HTML table including metadata

foreach ($tu_rows as $tu_id => $tu) {
    $nivel = null;
    if (isset($tu['params']['nivel'])) {
        $nivel = is_numeric($tu['params']['nivel']) ? floatval($tu['params']['nivel']) : $tu['params']['nivel'];
    } elseif (isset($tu['params']['nivel_dBuV'])) {
        $nivel = is_numeric($tu['params']['nivel_dBuV']) ? floatval($tu['params']['nivel_dBuV']) : $tu['params']['nivel_dBuV'];
    }
    $deviation = $tu['params']['deviation'] ?? null;

    $piso = $tu['meta']['piso'] ?? null;
    $apartamento = $tu['meta']['apartamento'] ?? null;
    $tu_index = $tu['meta']['tu_index'] ?? null;
    $meta_display = $tu['meta'];

    $tu_table[] = [
        'tu_id' => $tu_id,
        'piso' => $piso,
        'apartamento' => $apartamento,
        'tu_index' => $tu_index,
        'nivel' => $nivel,
        'deviation' => $deviation,
        'meta' => $meta_display
    ];

    if ($nivel !== null) $levels[] = $nivel;
}

// compute min/max/avg
$min_level = !empty($levels) ? min($levels) : null;
$max_level = !empty($levels) ? max($levels) : null;
$avg_level = !empty($levels) ? array_sum($levels) / count($levels) : null;

// Prepare JS arrays (labels / values)
$js_labels = [];
$js_values = [];
foreach ($tu_table as $r) {
    $label = $r['tu_id'];
    if ($r['piso'] !== null && $r['apartamento'] !== null) {
        $label = sprintf('P%s A%s T%s', $r['piso'] ?? '?', $r['apartamento'] ?? '?', $r['tu_index'] ?? '1');
    }
    $js_labels[] = $label;
    $js_values[] = $r['nivel'] !== null ? $r['nivel'] : 0;
}

$stmt_opt = $pdo->prepare("SELECT * FROM optimizations WHERE opt_id = :opt_id");
$stmt_opt->execute(['opt_id' => $opt_id]);
$opt = $stmt_opt->fetch(PDO::FETCH_ASSOC);

// Debug prints if requested
if ($debug) {
    echo "<pre style='background:#111;color:#bada55;padding:10px;'>";
    echo "DEBUG: opt_id = " . htmlspecialchars($opt_id) . "\n\n";
    echo "SUMMARY rows:\n";
    print_r($summary_rows);
    echo "\nDETAIL rows:\n";
    print_r($detail_rows);
    echo "\nComputed \$tu_rows:\n";
    print_r($tu_rows);
    echo "</pre>";
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Optimization Results #<?= htmlspecialchars($opt_id) ?></title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
  <style>
    .card-small { width: 18rem; }
    .mono { font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, "Roboto Mono", monospace; }
  </style>
</head>
<body class="bg-light">
<div class="container my-4">

  <div class="d-flex justify-content-between align-items-center mb-3">
    <h2>Optimization Results — #<?= htmlspecialchars($opt_id) ?></h2>
    <div>
      <a class="btn btn-outline-secondary" href="enter_data.php">← Back</a>
      <a href="results_export.php?opt_id=<?= $opt_id ?>&format=excel" class="btn btn-success btn-sm">⬇ Export Excel</a>
      <a href="?opt_id=<?= $opt_id ?>&debug=1" class="btn btn-outline-dark btn-sm">Debug</a>
    </div>
  </div>

  <div class="row g-3 mb-4">
    <div class="col-md-3">
      <div class="card card-small">
        <div class="card-body">
          <h6 class="card-title">Status</h6>
          <p class="card-text display-6 mono"><?= htmlspecialchars($status = isset($opt['status']) ? $opt['status'] : 'unknown') ?></p>
        </div>
      </div>
    </div>

    <div class="col-md-3">
      <div class="card card-small">
        <div class="card-body">
          <h6 class="card-title">TU Count</h6>
          <p class="card-text display-6 mono"><?= htmlspecialchars($tu_count) ?></p>
        </div>
      </div>
    </div>

    <div class="col-md-3">
      <div class="card card-small">
        <div class="card-body">
          <h6 class="card-title">Min Level</h6>
          <p class="card-text display-6 mono"><?= $min_level !== null ? number_format($min_level, 3) : '—' ?></p>
        </div>
      </div>
    </div>

    <div class="col-md-3">
      <div class="card card-small">
        <div class="card-body">
          <h6 class="card-title">Max Level</h6>
          <p class="card-text display-6 mono"><?= $max_level !== null ? number_format($max_level, 3) : '—' ?></p>
        </div>
      </div>
    </div>
  </div>

  <div class="row">
    <div class="col-lg-8 mb-4">
      <div class="card">
        <div class="card-header bg-primary text-white">Levels per TU</div>
        <div class="card-body">
          <canvas id="levelsChart" height="120"></canvas>
        </div>
      </div>

      <div class="card mt-3">
        <div class="card-header">Histogram</div>
        <div class="card-body">
          <canvas id="histChart" height="100"></canvas>
        </div>
      </div>
    </div>

    <div class="col-lg-4">
      <div class="card mb-3">
        <div class="card-header">Chosen Derivadores</div>
        <div class="card-body">
          <pre class="mono small"><?= htmlspecialchars(json_encode($chosen_derivadores, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE)) ?></pre>
        </div>
      </div>

      <div class="card mb-3">
        <div class="card-header">Chosen Repartidores</div>
        <div class="card-body">
          <pre class="mono small"><?= htmlspecialchars(json_encode($chosen_repartidores, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE)) ?></pre>
        </div>
      </div>

      <div class="card">
        <div class="card-header">Raw Summary</div>
        <div class="card-body small">
          <table class="table table-sm">
            <thead><tr><th>Parameter</th><th>Value</th></tr></thead>
            <tbody>
            <?php if (empty($summary)): ?>
              <tr><td colspan="2" class="text-muted">No summary rows found for this optimization.</td></tr>
            <?php else: ?>
              <?php foreach ($summary as $param => $info): ?>
                <tr>
                  <td class="mono small"><?= htmlspecialchars($param) ?></td>
                  <td><?= htmlspecialchars((string)$info['value']) ?> <?= $info['unit'] ? '<span class="text-muted">'.$info['unit'].'</span>' : '' ?></td>
                </tr>
              <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>

</div>

<script>
  const jsLabels = <?= json_encode($js_labels) ?>;
  const jsValues = <?= json_encode($js_values) ?>;

  // Levels chart
  const ctx = document.getElementById('levelsChart').getContext('2d');
  const levelsChart = new Chart(ctx, {
    type: 'bar',
    data: {
      labels: jsLabels,
      datasets: [{
        label: 'Nivel TU (dBµV)',
        data: jsValues,
        borderWidth: 1
      }]
    },
    options: {
      maintainAspectRatio: false,
      scales: {
        x: { ticks: { maxRotation: 90, minRotation: 45 } },
        y: { beginAtZero: false }
      },
      plugins: { legend: { display: false } }
    }
  });

  // Histogram
  function computeBins(values, nBins=10){
    if (!values || values.length===0) return {bins:[], counts:[]};
    const min = Math.min(...values); const max = Math.max(...values);
    const step = (max - min) / nBins || 1;
    const bins = Array.from({length:nBins}, (_,i)=> (min + i*step).toFixed(2));
    const counts = Array(nBins).fill(0);
    values.forEach(v=>{
      let idx = Math.floor((v - min) / (step||1));
      if (idx<0) idx=0; if (idx>=nBins) idx=nBins-1;
      counts[idx]++;
    });
    return {bins, counts};
  }

  const histData = computeBins(jsValues, 12);
  const histCtx = document.getElementById('histChart').getContext('2d');
  new Chart(histCtx, {
    type: 'bar',
    data: { labels: histData.bins, datasets:[{label:'Frequency', data: histData.counts, borderWidth:1}] },
    options:{maintainAspectRatio:false, plugins:{legend:{display:false}}}
  });
</script>

</body>
</html>
