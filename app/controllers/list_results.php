<?php
require_once __DIR__ . "/../config/db.php";

header("Content-Type: application/json");

try {
    $db = new Database();
    $pdo = $db->getConnection();

    $limit = intval($_GET["limit"] ?? 5);

    // MAIN QUERY — GET OPTIMIZATIONS WITH BASIC INFO
    $sql = "
        SELECT 
            o.opt_id,
            o.status,
            o.created_at
        FROM optimizations o
        ORDER BY o.opt_id DESC
        LIMIT :limit
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->bindValue(":limit", $limit, PDO::PARAM_INT);
    $stmt->execute();

    $optimizations = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // FOR EACH OPTIMIZATION — GET STATS FROM results TABLE
    foreach ($optimizations as &$opt) {

        $optId = $opt["opt_id"];

        $sqlStats = "
            SELECT parameter, value
            FROM results
            WHERE opt_id = :opt_id
              AND parameter IN (
                'stats_min_level_dBuV',
                'stats_max_level_dBuV',
                'stats_avg_level_dBuV',
                'stats_min_loss_dB',
                'stats_max_loss_dB'
              )
        ";

        $stmtStats = $pdo->prepare($sqlStats);
        $stmtStats->execute(["opt_id" => $optId]);
        $rows = $stmtStats->fetchAll(PDO::FETCH_ASSOC);

        // DEFAULT STRUCTURE
        $opt["stats"] = [
            "min_level"  => null,
            "max_level"  => null,
            "avg_level"  => null,
            "min_loss"   => null,
            "max_loss"   => null,
        ];

        foreach ($rows as $r) {
            switch ($r["parameter"]) {
                case "stats_min_level_dBuV":
                    $opt["stats"]["min_level"] = floatval($r["value"]);
                    break;
                case "stats_max_level_dBuV":
                    $opt["stats"]["max_level"] = floatval($r["value"]);
                    break;
                case "stats_avg_level_dBuV":
                    $opt["stats"]["avg_level"] = floatval($r["value"]);
                    break;
                case "stats_min_loss_dB":
                    $opt["stats"]["min_loss"] = floatval($r["value"]);
                    break;
                case "stats_max_loss_dB":
                    $opt["stats"]["max_loss"] = floatval($r["value"]);
                    break;
            }
        }
    }

    echo json_encode([
        "status" => "success",
        "results" => $optimizations
    ], JSON_PRETTY_PRINT);

} catch (Exception $e) {
    echo json_encode([
        "status" => "error",
        "message" => $e->getMessage()
    ]);
}
