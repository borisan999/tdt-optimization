<?php
// scripts/migrate_legacy_detail.php
require_once __DIR__ . '/../app/config/db.php'; // Correct path to your database.php

echo "=== Legacy Detail Migration ===
";

// Initialize Database connection
$db = new Database();
$pdo = $db->getConnection();

if (!$pdo) {
    echo "Error: Could not connect to the database.
";
    exit(1);
}

$stmt = $pdo->query("SELECT opt_id, detail_json, summary_json FROM results");
$results = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($results as $row) {

    $optId = $row['opt_id'];
    $detail = json_decode($row['detail_json'], true);
    $summary_original = json_decode($row['summary_json'], true); // Keep original summary for other KPIs

    if (!is_array($detail) || empty($detail)) {
        echo "Skipping opt_id={$optId} (empty or invalid detail_json)
";
        continue;
    }

    // Detect legacy: presence of Spanish flat keys, e.g., 'Toma'
    // This is the trigger to run the migration for this specific opt_id
    if (!isset($detail[0]['Toma']) && !isset($detail[0]['tu_id'])) { // Check both old and new canonical keys
        echo "Skipping opt_id={$optId} (already canonical or unrecognized structure)
";
        continue;
    }

    // If it has 'Toma' (legacy flat format), then proceed with migration
    if (isset($detail[0]['Toma'])) {
        echo "Migrating opt_id={$optId} (Legacy flat details detected)...
";

        $canonical_tus = [];
        $violations_count = 0; // Count violations for new summary

        // Assuming basic fields for compliance min/max, if not in detail, use sensible defaults
        $default_compliance_min = 48.0;
        $default_compliance_max = 69.0;
        
        // Try to get compliance limits from inputs_json if available, or summary
        // For simplicity in this script, using static defaults.
        // In a full system, you might decode inputs_json to get these dynamically.

        foreach ($detail as $rowTU) {
            $nivelTU = (float)($rowTU['Nivel en Toma (dBµV)'] ?? 0);
            
            // Assuming compliance values are consistent across TUs or globally defined
            // For now, use the static defaults
            $nivelMin = $default_compliance_min;
            $nivelMax = $default_compliance_max;

            // Update to use actual compliance values if available in summary_original or derived
            if (isset($summary_original['compliance_min'])) {
                $nivelMin = (float)$summary_original['compliance_min'];
            }
            if (isset($summary_original['compliance_max'])) {
                $nivelMax = (float)$summary_original['compliance_max'];
            }
            
            $cumple = ($nivelTU >= $nivelMin && $nivelTU <= $nivelMax);

            if (!$cumple) {
                $violations_count++;
            }

            $canonical_tus[] = [
                'tu_id' => (string)($rowTU['Toma'] ?? ''),
                'piso'  => (int)($rowTU['Piso'] ?? 0),
                'apto'  => (string)($rowTU['Apto'] ?? ''), // apto can be alphanumeric like '1A'
                'bloque'=> (int)($rowTU['Bloque'] ?? 0),

                'nivel_tu'  => $nivelTU,
                'nivel_min' => $nivelMin,
                'nivel_max' => $nivelMax,
                'cumple'    => $cumple,

                'losses' => [
                    'antena_troncal' =>
                        (float)($rowTU['Pérdida Antena→Troncal (cable) (dB)'] ?? 0) +
                        (float)($rowTU['Pérdida Antena↔Troncal (conectores) (dB)'] ?? 0),

                    'repartidor' =>
                        (float)($rowTU['Pérdida Repartidor Troncal (dB)'] ?? 0),

                    'feeder' =>
                        (float)($rowTU['Pérdida Feeder (cable) (dB)'] ?? 0) +
                        (float)($rowTU['Pérdida Feeder (conectores) (dB)'] ?? 0),

                    'riser' =>
                        (float)($rowTU['Pérdida Riser dentro del Bloque (dB)'] ?? 0),
                ]
            ];
        }

        // Rebuild summary based on new canonical structure
        $new_summary = $summary_original; // Start with original summary, override relevant parts
        $new_summary['total_tus']   = count($canonical_tus);
        $new_summary['violations']  = $violations_count;
        $new_summary['compliance_ratio'] =
            count($canonical_tus) > 0
                ? (count($canonical_tus) - $violations_count) / count($canonical_tus)
                : 0;
        
        // Add min/max/avg levels to new summary from the new canonical_tus data
        if (!empty($canonical_tus)) {
            $niveles = array_column($canonical_tus, 'nivel_tu');
            $new_summary['min_level'] = min($niveles);
            $new_summary['max_level'] = max($niveles);
            $new_summary['avg_level'] = array_sum($niveles) / count($niveles);
        } else {
            $new_summary['min_level'] = null;
            $new_summary['max_level'] = null;
            $new_summary['avg_level'] = null;
        }

        $update = $pdo->prepare("
            UPDATE results
            SET detail_json = :detail,
                summary_json = :summary
            WHERE opt_id = :opt_id
        ");

        $update->execute([
            ':detail'  => json_encode($canonical_tus, JSON_UNESCAPED_UNICODE),
            ':summary' => json_encode($new_summary, JSON_UNESCAPED_UNICODE),
            ':opt_id'  => $optId
        ]);

        echo "opt_id={$optId} migrated successfully.
";
    } else {
        echo "Skipping opt_id={$optId} (not in legacy flat format).
";
    }
}

echo "=== Migration Complete ===
";
