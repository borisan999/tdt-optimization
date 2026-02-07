<?php
/**
 * Epic 2 – Canonical Results Viewer
 * --------------------------------
 * Reads execution metadata from `optimizations`
 * Reads outputs strictly from JSON fields in `results`
 * No backward compatibility with legacy schemas
 */

/*ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);*/
require_once __DIR__ . '/../app/auth/require_login.php';
include __DIR__ . '/templates/header.php';
include __DIR__ . '/templates/navbar.php';

require_once __DIR__ . '/../app/config/db.php';


$opt_id = intval($_GET['opt_id'] ?? 0);
if ($opt_id <= 0) {
    die('opt_id is required');
}

$DB  = new Database();
$pdo = $DB->getConnection();

/* ---------------------------------------------------------
   Fetch canonical optimization result
--------------------------------------------------------- */
$sql = "
SELECT
    o.opt_id,
    o.dataset_id,
    o.status,
    o.start_time,
    o.end_time,
    r.summary_json,
    r.detail_json,
    r.inputs_json
FROM optimizations o
LEFT JOIN results r ON r.opt_id = o.opt_id
WHERE o.opt_id = :opt_id
";

$st = $pdo->prepare($sql);
$st->execute(['opt_id' => $opt_id]);
$row = $st->fetch(PDO::FETCH_ASSOC);

if (!$row) {
    die('Optimization not found');
}

    /**
     * Safely decode JSON into an array (or null). Logs errors.
     *
     * @param string|null $json
     * @return mixed|null
     */
     function decodeJson(?string $json)
    {
        if (empty($json)) {
            return null;
        }
        $decoded = json_decode($json, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            error_log("ResultsParser::decodeJson JSON error: " . json_last_error_msg() . " raw: " . substr($json, 0, 1024));
            return null;
        }
        return $decoded;
    }

    /**
     * Map parameters array to name => value for convenience.
     *
     * @param array $parameters
     * @return array
     */
    function parameterMap(array $parameters): array
    {
        $map = [];
        foreach ($parameters as $p) {
            if (isset($p['parameter'])) {
                $map[$p['parameter']] = $p['value'] ?? null;
            }
        }
        return $map;
    }

/* ---------------------------------------------------------
   Decode JSON safely
--------------------------------------------------------- */
$warnings = [];

$summary = [];
if (!empty($row['summary_json'])) {
    $summary = json_decode($row['summary_json'], true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        $warnings[] = 'summary_json decode error: ' . json_last_error_msg();
        $summary = [];
    }
}

$details = [];
if (!empty($row['detail_json'])) {
    $details = json_decode($row['detail_json'], true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        $warnings[] = 'detail_json decode error: ' . json_last_error_msg();
        $details = [];
    }
}

$nivelesTU = [];
if (is_array($details)) {
    foreach ($details as $tu) {
        if (isset($tu['Nivel TU Final (dBµV)'])) {
            $nivelesTU[] = (float)$tu['Nivel TU Final (dBµV)'];
        }
    }
}

$inputs = [];
if (!empty($row['inputs_json'])) {
    $inputs = json_decode($row['inputs_json'], true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        $warnings[] = 'inputs_json decode error: ' . json_last_error_msg();
        $inputs = [];
    }
}
if (empty($details) || !is_array($details)) {
    if (!is_array($details)) {
        throw new RuntimeException(
            'Result detail_json malformed for opt_id=' . $opt_id
        );
    }

    if (count($details) === 0) {
        $warnings[] = 'No valid TU rows for this optimization (engineering infeasible case)';
    }
}
if (!empty($details)) {
    $niveles = array_column($details, 'Nivel TU Final (dBµV)');
    $summary['min_level'] = min($niveles);
    $summary['max_level'] = max($niveles);
} else {
    // Preserve stored summary; do not recompute
    $summary['min_level'] = null;
    $summary['max_level'] = null;
}

/* ---------------------------------------------------------
   Derived values
--------------------------------------------------------- */
$status = $row['status'] ?? 'unknown';

$runtime_sec = null;
if (!empty($row['start_time']) && !empty($row['end_time'])) {
    $runtime_sec = strtotime($row['end_time']) - strtotime($row['start_time']);
}

/* ---------------------------------------------------------
   Canonical summary regeneration (server-side)
--------------------------------------------------------- */

// Compliance limits (prefer inputs_json, fallback safe defaults)
$COMPLIANCE_MIN = $inputs['compliance_min'] ?? 48.0;
$COMPLIANCE_MAX = $inputs['compliance_max'] ?? 69.0;

$levels = [];

foreach ($details as $tu) {
    if (isset($tu['Nivel TU Final (dBµV)']) && is_numeric($tu['Nivel TU Final (dBµV)'])) {
        $levels[] = (float)$tu['Nivel TU Final (dBµV)'];
    }
}

$tu_total = count($levels);

$summary_regen = [
    'tu_total' => $tu_total,
    'compliance_min' => $COMPLIANCE_MIN,
    'compliance_max' => $COMPLIANCE_MAX,
];

if ($tu_total > 0) {
    $min_level = min($levels);
    $max_level = max($levels);

    $low = 0;
    $high = 0;

    foreach ($levels as $v) {
        if ($v < $COMPLIANCE_MIN) $low++;
        elseif ($v > $COMPLIANCE_MAX) $high++;
    }

    $in_spec = $tu_total - $low - $high;

    $summary_regen += [
        'min_level'       => round($min_level, 2),
        'max_level'       => round($max_level, 2),
        'tu_in_spec'      => $in_spec,
        'tu_low'          => $low,
        'tu_high'         => $high,
        'compliance_pct'  => round(($in_spec / $tu_total) * 100, 2),
    ];
} else {
    $summary_regen += [
        'min_level'      => null,
        'max_level'      => null,
        'tu_in_spec'     => 0,
        'tu_low'         => 0,
        'tu_high'        => 0,
        'compliance_pct' => null,
    ];
}

/* Override decoded summary_json with canonical regenerated one */
$summary = $summary_regen;

/* ---------------------------------------------------------
   Violations extraction (canonical)
--------------------------------------------------------- */

$violations = [];

foreach ($details as $tu) {
    if (!isset($tu['Nivel TU Final (dBµV)']) || !is_numeric($tu['Nivel TU Final (dBµV)'])) {
        continue;
    }

    $nivel = (float)$tu['Nivel TU Final (dBµV)'];

    if ($nivel < $summary['compliance_min']) {
        $tu['_violation_type'] = 'LOW';
        $tu['_violation_delta'] = round($nivel - $summary['compliance_min'], 2);
        $violations[] = $tu;
    }
    elseif ($nivel > $summary['compliance_max']) {
        $tu['_violation_type'] = 'HIGH';
        $tu['_violation_delta'] = round($nivel - $summary['compliance_max'], 2);
        $violations[] = $tu;
    }
}
$excel_enabled = !empty($summary) && !empty($details);

/* ---------------------------------------------------------
   HTML Output
--------------------------------------------------------- */
?>
<div class="container my-4">

    <h2 class="mb-3">Optimization Result Details</h2>
        <div class="mb-3 d-flex gap-2">
            <!-- <a class="btn btn-outline-primary btn-sm"
            href="export_csv.php?opt_id=<?= intval($opt_id) ?>&type=inputs">
                Export Inputs (CSV)
            </a> -->

            <a class="btn btn-success btn-sm"
            href="export_input_excel.php?opt_id=<?= intval($opt_id) ?>">
                <i class="fas fa-file-excel"></i> Export Inputs (XLSX)
            </a>

            <a class="btn btn-outline-success btn-sm"
            href="export_csv.php?opt_id=<?= intval($opt_id) ?>&type=detail">
                Export TUs Detail (CSV)
            </a>

            <a class="btn btn-outline-secondary btn-sm"
            href="export_csv.php?opt_id=<?= intval($opt_id) ?>&type=inventory">
                Export Inventory (CSV)
            </a>
           <!--  <a
                href="<?= $excel_enabled ? "export_excel.php?opt_id=" . intval($opt_id) : "#" ?>"
                class="btn btn-outline-primary btn-sm <?= $excel_enabled ? "" : "disabled" ?>"
                title="<?= $excel_enabled ? "Engineering-validated Excel export" : "Missing summary or detail data" ?>"
            >
                Export Excel (Engineering)
            </a> -->
            <a href="export_docx.php?opt_id=<?= $opt_id ?>"
                class="btn btn-primary">
                Export Memoria de Diseño (DOCX)
            </a>
        </div>
    <table class="table table-bordered table-sm">
        <tr><th>Optimization ID</th><td><?= htmlspecialchars($row['opt_id']) ?></td></tr>
        <tr><th>Status</th><td><?= htmlspecialchars($status) ?></td></tr>
        <tr><th>Dataset ID</th><td><?= htmlspecialchars($row['dataset_id']) ?></td></tr>
        <tr><th>Created</th><td><?= htmlspecialchars($row['end_time'] ?? 'N/A') ?></td></tr>
    </table>

    <?php if (!empty($warnings)): ?>
        <div class="alert alert-warning">
            <strong>Parsing warnings:</strong>
            <ul>
                <?php foreach ($warnings as $w): ?>
                    <li><?= htmlspecialchars($w) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>
    
    <div class="row mb-4 text-center">
        <div class="col-md-3">
            <div class="card shadow-sm">
                <div class="card-body">
                    <div class="fw-bold text-muted">Total TUs</div>
                    <div class="fs-3"><?= $summary['tu_total'] ?></div>
                </div>
            </div>
        </div>

        <div class="col-md-3">
            <div class="card shadow-sm">
                <div class="card-body">
                    <div class="fw-bold text-muted">% Cumplimiento</div>
                    <div class="fs-3">
                        <?= $summary['compliance_pct'] !== null ? $summary['compliance_pct'].'%' : '—' ?>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-3">
            <div class="card shadow-sm border-warning">
                <div class="card-body">
                    <div class="fw-bold text-muted">Bajo norma</div>
                    <div class="fs-3 text-warning"><?= $summary['tu_low'] ?></div>
                </div>
            </div>
        </div>

        <div class="col-md-3">
            <div class="card shadow-sm border-danger">
                <div class="card-body">
                    <div class="fw-bold text-muted">Sobre norma</div>
                    <div class="fs-3 text-danger"><?= $summary['tu_high'] ?></div>
                </div>
            </div>
        </div>
    </div>
    <h4>Summary Metrics</h4>
<div class="row mb-4">
    <div class="col-md-6">
        <canvas id="summaryChart"></canvas>
    </div>
    <div class="col-md-6">
        <table class="table table-bordered table-sm">
            <tbody>
            <?php if (!empty($summary)): ?>
                <?php foreach ($summary as $k => $v): ?>
                    <tr>
                        <th><?= htmlspecialchars($k) ?></th>
                        <td><?= is_scalar($v) ? htmlspecialchars((string)$v) : json_encode($v) ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr><td>No summary data</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
const nivelesTU = <?= json_encode($nivelesTU, JSON_NUMERIC_CHECK) ?>.filter(v => Number.isFinite(v));
const summary   = <?= json_encode($summary ?? [], JSON_NUMERIC_CHECK) ?>;

const minLevel = summary.min_level ?? null;
const maxLevel = summary.max_level ?? null;
const COMPLIANCE_MIN = summary.compliance_min;
const COMPLIANCE_MAX = summary.compliance_max;

function buildHistogram(values, binSize = 1) {
    const bins = {};
    values.forEach(v => {
        const b = Math.floor(v / binSize) * binSize;
        bins[b] = (bins[b] || 0) + 1;
    });

    const keys = Object.keys(bins).map(Number).sort((a, b) => a - b);

    return {
        labels: keys.map(k => `${k.toFixed(1)}–${(k + binSize).toFixed(1)}`),
        data: keys.map(k => bins[k]),
        centers: keys.map(k => k + binSize / 2) // ← ADD THIS LINE
    };
}


 /* wait until DOM is ready for nivelChart (it appears later in the HTML) */
document.addEventListener('DOMContentLoaded', () => {
    if (nivelesTU.length > 0) {
        const nivelCanvas = document.getElementById('nivelChart');
        if (nivelCanvas instanceof HTMLCanvasElement) {
            const hist = buildHistogram(nivelesTU, 1);
            const dataMinX = Math.min(...hist.centers);
            const dataMaxX = Math.max(...hist.centers);
            new Chart(nivelCanvas.getContext('2d'), {
                type: 'bar',
                data: {
                    datasets: [{
                        label: 'Cantidad de TUs',
                        data: hist.centers.map((x, i) => ({
                            x: x,
                            y: hist.data[i]
                        })),
                        backgroundColor: ctx => {
                            const x = ctx.raw.x;

                            if (x < COMPLIANCE_MIN || x > COMPLIANCE_MAX) {
                                return 'rgba(220, 53, 69, 0.8)';   // 🔴 out of spec
                            }
                            return 'rgba(13, 110, 253, 0.8)';     // 🔵 in spec
                        }
                    }]
                },
                options: {
                    responsive: true,
                        plugins: {
                            legend: { display: false },

                            title: {
                                display: true,
                                text: 'Distribución de Nivel TU Final (dBµV)'
                            },

                            subtitle: {
                                display: (minLevel !== null || maxLevel !== null),
                                text: [
                                    minLevel !== null ? `Nivel mínimo: ${minLevel.toFixed(2)} dBµV` : null,
                                    maxLevel !== null ? `Nivel máximo: ${maxLevel.toFixed(2)} dBµV` : null
                                ].filter(Boolean).join('  |  ')
                            },

                            annotation: {
                                annotations: {
                                    lowOutOfSpec: {
                                        type: 'box',
                                        xMin: Math.floor(minLevel) - 1,
                                        xMax: COMPLIANCE_MIN,
                                        backgroundColor: 'rgba(255, 0, 0, 0.06)',
                                        borderWidth: 0,
                                        drawTime: 'beforeDatasetsDraw'
                                    },

                                    complianceBand: {
                                        type: 'box',
                                        xMin: Math.max(COMPLIANCE_MIN, Math.floor(minLevel)),
                                        xMax: Math.min(COMPLIANCE_MAX, Math.ceil(maxLevel)),
                                        backgroundColor: 'rgba(0, 180, 0, 0.08)',
                                        borderWidth: 0,
                                        drawTime: 'beforeDatasetsDraw',
                                        label: {
                                            display: true,
                                            content: 'Banda normativa (48–69 dBµV)',
                                            position: 'start',
                                            color: '#0a5'
                                        }
                                    },

                                    highOutOfSpec: {
                                        type: 'box',
                                        xMin: COMPLIANCE_MAX,
                                        xMax: Math.ceil(maxLevel) + 1,
                                        backgroundColor: 'rgba(255, 0, 0, 0.06)',
                                        borderWidth: 0,
                                        drawTime: 'beforeDatasetsDraw'
                                    }
                                }
                            }
                        },

                    scales: {
                        x: {
                            type: 'linear',
                            min: Math.min(dataMinX, COMPLIANCE_MIN) - 1,
                            max: Math.max(dataMaxX, COMPLIANCE_MAX) + 1,
                            title: {
                                display: true,
                                text: 'Nivel TU Final (dBµV)'
                            },
                            ticks: {
                                stepSize: 1
                            }
                        },
                        y: {
                        beginAtZero: true,
                        ticks: {
                            precision: 0,
                            stepSize: 1
                        },
                        suggestedMax: hist.data.length > 0
                            ? Math.max(...hist.data) + 1
                            : 1,
                        title: {
                            display: true,
                            text: 'Cantidad de TUs'
                        }
                    }
                    }
                }

            });
        } else {
            console.warn("nivelChart canvas not found or not a canvas element");
        }
    }
});

const labels = [];
const values = [];
for (const [k, v] of Object.entries(summary)) {
    if (typeof v === 'number') {
        labels.push(k);
        values.push(v);
    }
}
const summaryCanvas = document.getElementById('summaryChart');
if (labels.length > 0 && summaryCanvas instanceof HTMLCanvasElement) {
    new Chart(summaryCanvas.getContext('2d'), {
        type: 'bar',
        data: {
            labels: labels,
            datasets: [{
                label: 'Summary KPIs',
                data: values
            }]
        },
        options: {
            responsive: true,
            scales: { y: { beginAtZero: true } }
        }
    });
}
</script>

    <h4>Detail-Level Analysis</h4>

<div class="row mb-4">
    <div class="col-md-6">
        <canvas id="detailHistogram"></canvas>
    </div>
    <div class="col-md-6">
        <div class="alert alert-info">
            <strong>Metric:</strong> Nivel TU Final (dBµV)<br>
            Histogram distribution across all TUs.
        </div>
    </div>
</div>
<h4>Distribución – Nivel TU Final (dBµV)</h4>
<canvas id="nivelChart" height="120"></canvas>
<h4 class="mt-4">Violaciones de Nivel TU</h4>

<?php if (count($violations) === 0): ?>
    <div class="alert alert-success">
        Todas las tomas cumplen con la banda normativa.
    </div>
<?php else: ?>

<div class="d-flex justify-content-between align-items-center mb-2">
    <div class="text-muted">
        <?= count($violations) ?> tomas fuera de norma
    </div>
    <a class="btn btn-outline-danger btn-sm"
       href="export_csv.php?opt_id=<?= intval($opt_id) ?>&type=violations">
        Exportar violaciones (CSV)
    </a>
</div>

<div class="table-responsive">
<table class="table table-sm table-bordered table-striped align-middle">
    <thead class="table-light">
        <tr>
            <th>Toma</th>
            <th>Piso</th>
            <th>Apto</th>
            <th>Nivel TU (dBµV)</th>
            <th>Tipo</th>
            <th>Δ vs Norma (dB)</th>
        </tr>
    </thead>
    <tbody>
    <?php foreach ($violations as $v): ?>
        <tr class="<?= $v['_violation_type'] === 'LOW' ? 'table-warning' : 'table-danger' ?>">
            <td><?= htmlspecialchars($v['Toma'] ?? '—') ?></td>
            <td><?= htmlspecialchars($v['Piso'] ?? '—') ?></td>
            <td><?= htmlspecialchars($v['Apto'] ?? '—') ?></td>
            <td><?= number_format($v['Nivel TU Final (dBµV)'], 2) ?></td>
            <td>
                <?= $v['_violation_type'] === 'LOW' ? 'Bajo norma' : 'Sobre norma' ?>
            </td>
            <td><?= number_format($v['_violation_delta'], 2) ?></td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
</div>

<?php endif; ?>

<h4>Detail JSON (<?= count($details) ?> TUs)</h4>
    <div class="table-responsive">
        <table class="table table-striped table-bordered table-sm">
            <thead>
            <tr>
                <?php if (!empty($details)): ?>
                    <?php foreach (array_keys($details[0]) as $col): ?>
                        <th><?= htmlspecialchars($col) ?></th>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($details as $rowDetail): ?>
                <tr>
                    <?php foreach ($rowDetail as $val): ?>
                        <td><?= htmlspecialchars((string)$val) ?></td>
                    <?php endforeach; ?>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <h4>Inputs JSON</h4>
    <pre class="bg-white p-3 border rounded"><?= htmlspecialchars(json_encode($inputs, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) ?></pre>

</div>
<script>
    const detailRows = <?= json_encode($details, JSON_UNESCAPED_UNICODE) ?>;
    const nivelValues = [];

    detailRows.forEach(r => {
        const v = r['Nivel TU Final (dBµV)'];
        if (typeof v === 'number') nivelValues.push(v);
    });

    function buildDetailHistogram(values, bins = 10) {
        if (values.length === 0) return { labels: [], counts: [] };

        const min = Math.min(...values);
        const max = Math.max(...values);
        const step = (max - min) / bins || 1;

        const counts = Array(bins).fill(0);

        values.forEach(v => {
            const idx = Math.min(bins - 1, Math.floor((v - min) / step));
            counts[idx]++;
        });

        const labels = counts.map((_, i) =>
            `${(min + i * step).toFixed(1)}–${(min + (i + 1) * step).toFixed(1)}`
        );

        return { labels, counts };
    }

    const detailHist = buildDetailHistogram(nivelValues, 10);

    if (detailHist.labels.length > 0) {
        new Chart(document.getElementById('detailHistogram'), {
            type: 'bar',
            data: {
                labels: detailHist.labels,
                datasets: [{
                    label: 'Nivel TU Final (dBµV)',
                    data: detailHist.counts
                }]
            },
            options: {
                responsive: true,
                scales: {
                    x: { title: { display: true, text: 'Nivel TU Final (dBµV)' } },
                    y: { beginAtZero: true, title: { display: true, text: 'Cantidad de TUs' } }
                }
            }
        });
    }
</script>
<?php include __DIR__ . '/templates/footer.php'; ?>
