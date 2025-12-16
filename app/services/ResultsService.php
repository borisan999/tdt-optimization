<?php
class ResultsService
{
    public static function listAll(PDO $pdo): array
    {
        $sql = "
            SELECT
                o.opt_id,
                o.status,
                o.start_time AS created_at,
                r.summary_json
            FROM optimizations o
            INNER JOIN results r ON r.opt_id = o.opt_id
            ORDER BY o.opt_id DESC
        ";

        $stmt = $pdo->prepare($sql);
        $stmt->execute();

        $rows = [];

        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {

            $summary = json_decode($row['summary_json'], true) ?? [];

            $rows[] = [
                'opt_id' => (int)$row['opt_id'],
                'status' => $row['status'],
                'created_at' => $row['created_at'],
                'stats' => [
                    'min_level' => $summary['min_level'] ?? null,
                    'max_level' => $summary['max_level'] ?? null,
                    'avg_level' => $summary['avg_level'] ?? null,
                    'min_loss'  => $summary['min_loss']  ?? null,
                    'max_loss'  => $summary['max_loss']  ?? null,
                ]
            ];
        }

        return $rows;
    }
}
