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
    if (is_array($v) || is_object($v)) {
        $v = json_encode($v, JSON_UNESCAPED_UNICODE);
    } elseif ($v === null) {
        $v = '';
    }

    $v = (string)$v;

    if (strpbrk($v, ",\n\"") !== false) {
        return '"' . str_replace('"', '""', $v) . '"';
    }
    return $v;
}

function build_inventory_from_detail(array $details): array
{
    $inventory = [
        ['Item' => 'TUs',        'Quantity' => 0, 'Unit' => 'units'],
        ['Item' => 'Cable',      'Quantity' => 0, 'Unit' => 'm'],
        ['Item' => 'Connectors', 'Quantity' => 0, 'Unit' => 'units'],
    ];

    $deviceCounts = [];

    foreach ($details as $tu) {

        // TU count
        $inventory[0]['Quantity']++;

        // Cable + connectors
        foreach ($tu as $key => $value) {
            if (
                is_numeric($value)
                && stripos($key, 'largo') !== false
            ) {
                $inventory[1]['Quantity'] += (float)$value;
                $inventory[2]['Quantity'] += 2;
            }
        }

        // Devices (derivadores / repartidores / etc)
        if (!empty($tu['Dispositivos']) && is_array($tu['Dispositivos'])) {
            foreach ($tu['Dispositivos'] as $d) {
                $model = $d['Modelo'] ?? 'UNKNOWN';
                $deviceCounts[$model] = ($deviceCounts[$model] ?? 0) + 1;
            }
        }
    }

    // Append devices to inventory table
    foreach ($deviceCounts as $model => $qty) {
        $inventory[] = [
            'Item'     => $model,
            'Quantity' => $qty,
            'Unit'     => 'units'
        ];
    }

    return $inventory;
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
        if (empty($row['inputs_json'])) {
            die('inputs_json is empty');
        }

        $inputs = json_decode($row['inputs_json'], true);
        if (json_last_error() !== JSON_ERROR_NONE || !is_array($inputs)) {
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

        // Collect all unique keys from all rows for a robust header
        $allKeys = [];
        foreach ($details as $tu) {
            if (is_array($tu)) {
                foreach ($tu as $key => $value) {
                    $allKeys[$key] = true;
                }
            }
        }
        $headers = array_keys($allKeys);
        echo implode(',', array_map('csv_escape', $headers)) . "\n";

        // Keywords for formatting numeric values to 2 decimal places
        $numericFormatKeywords = ['Nivel', 'Perdidas', 'Atenuacion', 'Factor'];

        foreach ($details as $tu) {
            $line = [];
            foreach ($headers as $h) {
                $value = $tu[$h] ?? '';

                // Check if the value is numeric and the header matches formatting keywords
                if (is_numeric($value)) {
                    foreach ($numericFormatKeywords as $keyword) {
                        if (stripos($h, $keyword) !== false) {
                            $value = number_format((float)$value, 2, '.', '');
                            break; 
                        }
                    }
                }
                $line[] = csv_escape($value);
            }
            echo implode(',', $line) . "\n";
        }
        break;

 /* -----------------------------------------------------
   INVENTORY EXPORT (DERIVED FROM DETAIL_JSON)
----------------------------------------------------- */
case 'inventory':

    $details = json_decode($row['detail_json'], true);
    if (!is_array($details) || empty($details)) {
        die('Invalid or empty detail_json');
    }

    $inv = build_inventory_from_detail($details);

    // Header
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


}

exit;
