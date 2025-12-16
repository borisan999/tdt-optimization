<?php
/**
 * Epic 2 – Canonical CSV Exporter
 * --------------------------------
 * Exports data strictly from canonical JSON fields in `results`
 * Supported types:
 *   - inputs     -> results.inputs_json
 *   - detail     -> results.detail_json
 *   - inventory  -> results.summary_json['inventory'] (if present)
 *
 * No legacy tables. No backward compatibility.
 */

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../app/config/db.php';

$opt_id = intval($_GET['opt_id'] ?? 0);
$type   = $_GET['type'] ?? '';

if ($opt_id <= 0) {
    http_response_code(400);
    die('opt_id is required');
}

$allowed = ['inputs', 'detail', 'inventory'];
if (!in_array($type, $allowed, true)) {
    http_response_code(400);
    die('Invalid export type');
}

$DB  = new Database();
$pdo = $DB->getConnection();

$sql = "SELECT summary_json, detail_json, inputs_json FROM results WHERE opt_id = :opt_id";
$st  = $pdo->prepare($sql);
$st->execute(['opt_id' => $opt_id]);
$row = $st->fetch(PDO::FETCH_ASSOC);

if (!$row) {
    http_response_code(404);
    die('Result not found');
}

function csv_escape($v): string {
    $v = (string)$v;
    if (strpbrk($v, ",\n\"") !== false) {
        return '"' . str_replace('"', '""', $v) . '"';
    }
    return $v;
}

$filename = "opt_{$opt_id}_{$type}.csv";

header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename=' . $filename);

echo "\xEF\xBB\xBF"; // UTF-8 BOM for Excel compatibility

switch ($type) {

    /* -----------------------------------------------------
       INPUT PARAMETERS EXPORT
    ----------------------------------------------------- */
    case 'inputs':
        $inputs = json_decode($row['inputs_json'], true);
        if (!is_array($inputs)) {
            die('Invalid inputs_json');
        }

        echo "name,value\n";
        foreach ($inputs as $k => $v) {
            echo csv_escape($k) . "," . csv_escape($v) . "\n";
        }
        break;

    /* -----------------------------------------------------
       DETAIL (PER TU) EXPORT
    ----------------------------------------------------- */
    case 'detail':
        $details = json_decode($row['detail_json'], true);
        if (!is_array($details) || empty($details)) {
            die('Invalid or empty detail_json');
        }

        // Header from first row keys
        $headers = array_keys($details[0]);
        echo implode(',', array_map('csv_escape', $headers)) . "\n";

        foreach ($details as $tu) {
            $line = [];
            foreach ($headers as $h) {
                $line[] = csv_escape($tu[$h] ?? '');
            }
            echo implode(',', $line) . "\n";
        }
        break;

    /* -----------------------------------------------------
       INVENTORY EXPORT (FROM SUMMARY_JSON)
    ----------------------------------------------------- */
    case 'inventory':
        $summary = json_decode($row['summary_json'], true);
        if (!is_array($summary) || empty($summary['inventory'])) {
            die('Inventory not found in summary_json');
        }

        $inv = $summary['inventory'];
        if (!is_array($inv) || empty($inv)) {
            die('Invalid inventory structure');
        }

        // Header from first inventory row
        $headers = array_keys($inv[0]);
        echo implode(',', array_map('csv_escape', $headers)) . "\n";

        foreach ($inv as $rowInv) {
            $line = [];
            foreach ($headers as $h) {
                $line[] = csv_escape($rowInv[$h] ?? '');
            }
            echo implode(',', $line) . "\n";
        }
        break;

        case 'violations':

            // reuse canonical logic
            $violations = [];

            foreach ($details as $tu) {
                if (!isset($tu['Nivel TU Final (dBµV)']) || !is_numeric($tu['Nivel TU Final (dBµV)'])) {
                    continue;
                }

                $nivel = (float)$tu['Nivel TU Final (dBµV)'];

                if ($nivel < $COMPLIANCE_MIN) {
                    $tu['violation_type'] = 'LOW';
                    $tu['violation_delta_db'] = round($nivel - $COMPLIANCE_MIN, 2);
                    $violations[] = $tu;
                }
                elseif ($nivel > $COMPLIANCE_MAX) {
                    $tu['violation_type'] = 'HIGH';
                    $tu['violation_delta_db'] = round($nivel - $COMPLIANCE_MAX, 2);
                    $violations[] = $tu;
                }
            }

            if (empty($violations)) {
                fputcsv($out, ['No violations']);
                break;
            }

            // header
            fputcsv($out, array_keys($violations[0]));

            foreach ($violations as $row) {
                fputcsv($out, $row);
            }

            break;

}

exit;
