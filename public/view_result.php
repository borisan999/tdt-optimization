<?php
/**
 * Canonical Results Viewer (Strict)
 * ----------------------------------
 * - Reads execution metadata from `optimizations`
 * - Reads canonical JSON strictly via ResultParser
 * - No manual JSON decoding
 * - No summary regeneration
 * - No legacy compatibility
 */

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../app/auth/require_login.php';
require_once __DIR__ . '/../app/config/db.php';
require_once __DIR__ . '/../app/helpers/ResultParser.php';

include __DIR__ . '/templates/header.php';
include __DIR__ . '/templates/navbar.php';

use app\helpers\ResultParser;

/* ---------------------------------------------------------
   Validate opt_id
--------------------------------------------------------- */

$opt_id = intval($_GET['opt_id'] ?? 0);
if ($opt_id <= 0) {
    die('opt_id is required');
}

/* ---------------------------------------------------------
   Fetch DB row
--------------------------------------------------------- */

$DB  = new Database();
$pdo = $DB->getConnection();


$stmt = $pdo->prepare("
    SELECT o.opt_id, o.dataset_id, o.status, o.created_at
    FROM optimizations o
    WHERE o.opt_id = :id
");
$stmt->execute(['id' => $opt_id]);
$opt = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$opt) {
    die("Optimization not found.");
}

$dataset_id = $opt['dataset_id'] ?? null;
$status     = $opt['status'] ?? 'unknown';
$created_at = $opt['created_at'] ?? null;

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

/* ---------------------------------------------------------
   Parse canonical via ResultParser
--------------------------------------------------------- */

$parser = ResultParser::fromDbRow($row);

if ($parser->hasErrors()) {
    throw new RuntimeException(
        'ResultParser error: ' . implode('; ', $parser->errors())
    );
}

$meta      = $parser->meta();
$canonical = $parser->canonical();
$warnings  = $parser->warnings();

$summary = $canonical['summary'] ?? [];
$details = $canonical['detail'] ?? [];
$inputs  = $canonical['inputs'] ?? [];

/* ---------------------------------------------------------
   Derived view-only values
--------------------------------------------------------- */

$status = $meta['status'] ?? 'unknown';

$runtime_sec = null;
$created = null;
if (!empty($meta['end_time'])) {
    if ($meta['end_time'] instanceof DateTime) {
        $created = $meta['end_time']->format('Y-m-d H:i:s');
    } else {
        $created = (string)$meta['end_time'];
    }
}

/* ---------------------------------------------------------
   Extract TU levels (for charts)
--------------------------------------------------------- */

$levels = [];

foreach ($details as $tu) {
    if (isset($tu['Nivel TU Final (dBµV)']) && is_numeric($tu['Nivel TU Final (dBµV)'])) {
        $levels[] = (float)$tu['Nivel TU Final (dBµV)'];
    }
}

/* ---------------------------------------------------------
   Violations (presentation-only logic)
--------------------------------------------------------- */

$violations = [];

$COMPLIANCE_MIN = $summary['compliance_min'] ?? 48.0;
$COMPLIANCE_MAX = $summary['compliance_max'] ?? 69.0;

foreach ($details as $tu) {

    if (!isset($tu['Nivel TU Final (dBµV)'])) {
        continue;
    }

    $nivel = (float)$tu['Nivel TU Final (dBµV)'];

    if ($nivel < $COMPLIANCE_MIN) {
        $tu['_violation_type']  = 'LOW';
        $tu['_violation_delta'] = round($nivel - $COMPLIANCE_MIN, 2);
        $violations[] = $tu;
    }
    elseif ($nivel > $COMPLIANCE_MAX) {
        $tu['_violation_type']  = 'HIGH';
        $tu['_violation_delta'] = round($nivel - $COMPLIANCE_MAX, 2);
        $violations[] = $tu;
    }
}

?>
<div class="container my-4">

    <h2 class="mb-3">Optimization Result Details</h2>

    <table class="table table-bordered table-sm">
        <tr>
            <th>Optimization ID</th>
            <td><?= htmlspecialchars((string)$meta['opt_id']) ?></td>
        </tr>
        <tr>
            <th>Status</th>
            <td><?= htmlspecialchars((string)$status) ?></td>
        </tr>
        <tr>
            <th>Dataset ID</th>
            <td><?= $dataset_id !== null ? htmlspecialchars((string)$dataset_id) : '—' ?></td>
        </tr>
        <tr>
            <th>Created</th>
            <td><?= $created !== null ? htmlspecialchars($created) : '—' ?></td>
        </tr>
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

    <h4>Summary Metrics</h4>

    <table class="table table-bordered table-sm">
        <tbody>
        <?php foreach ($summary as $k => $v): ?>
            <tr>
                <th><?= htmlspecialchars($k) ?></th>
                <td><?= is_scalar($v) ? htmlspecialchars((string)$v) : json_encode($v) ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>

    <h4>Violaciones de Nivel TU</h4>

    <?php if (count($violations) === 0): ?>
        <div class="alert alert-success">
            Todas las tomas cumplen con la banda normativa.
        </div>
    <?php else: ?>

    <div class="table-responsive">
        <table class="table table-sm table-bordered table-striped">
            <thead>
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
                    <td><?= $v['_violation_type'] === 'LOW' ? 'Bajo norma' : 'Sobre norma' ?></td>
                    <td><?= number_format($v['_violation_delta'], 2) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <div class="row mb-4 text-center">
        <div class="col-md-3">
            <div class="card shadow-sm">
                <div class="card-body">
                    <div class="fw-bold text-muted">Total TUs</div>
                    <div class="fs-3"><?= $summary['tu_total'] ?? 0 ?></div>
                </div>
            </div>
        </div>

        <div class="col-md-3">
            <div class="card shadow-sm">
                <div class="card-body">
                    <div class="fw-bold text-muted">% Cumplimiento</div>
                    <div class="fs-3">
                        <?= isset($summary['compliance_pct']) ? $summary['compliance_pct'].'%' : '—' ?>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-3">
            <div class="card shadow-sm border-warning">
                <div class="card-body">
                    <div class="fw-bold text-muted">Bajo norma</div>
                    <div class="fs-3 text-warning"><?= $summary['tu_low'] ?? 0 ?></div>
                </div>
            </div>
        </div>

        <div class="col-md-3">
            <div class="card shadow-sm border-danger">
                <div class="card-body">
                    <div class="fw-bold text-muted">Sobre norma</div>
                    <div class="fs-3 text-danger"><?= $summary['tu_high'] ?? 0 ?></div>
                </div>
            </div>
        </div>
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
                    <?php foreach ($rowDetail as $col_key => $val): ?>
                        <td>
                        <?php if (is_array($val)): ?>
                            <?php if ($col_key === 'losses'): ?>
                                <?php foreach ($val as $loss_item): ?>
                                    <?php if (isset($loss_item['segment']) && isset($loss_item['value'])): ?>
                                        <?= htmlspecialchars($loss_item['segment'] . ': ' . $loss_item['value']) ?><br>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <?= htmlspecialchars(json_encode($val, JSON_UNESCAPED_UNICODE)) ?>
                            <?php endif; ?>
                        <?php else: ?>
                            <?= htmlspecialchars((string)$val) ?>
                        <?php endif; ?>
                        </td>
                    <?php endforeach; ?>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <h4>Inputs JSON</h4>
    <pre class="bg-white p-3 border rounded">
<?= htmlspecialchars(json_encode($inputs, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) ?>
    </pre>

</div>

<?php include __DIR__ . '/templates/footer.php'; ?>
