<?php

class ResultsDetail
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Inserts a detail record into the results_detail table.
     *
     * @param int         $opt_id
     * @param string      $name
     * @param mixed       $value  Scalar, array, or object
     * @param string|null $unit
     * @param mixed|null  $deviation
     * @param mixed|null  $meta   Scalar, array, or object
     *
     * @return string Last inserted ID
     * @throws Exception
     */
    public function insert(
        int $opt_id,
        string $name,
        mixed $value,
        ?string $unit = null,
        mixed $deviation = null,
        mixed $meta = null
    ): string {
        if (empty($opt_id)) {
            throw new Exception("opt_id cannot be null or empty.");
        }

        $sql = "INSERT INTO results_detail
                (opt_id, name, value, unit, deviation, meta)
                VALUES
                (:opt_id, :name, :value, :unit, :deviation, :meta)";

        $stmt = $this->pdo->prepare($sql);

        // Encode value if it's array or object
        $encodedValue = null;
        if (is_null($value)) {
            $encodedValue = null;
        } elseif (is_scalar($value)) {
            $encodedValue = $value;
        } else {
            $encodedValue = json_encode($value, JSON_UNESCAPED_UNICODE);
        }

        // Encode meta if it's array or object
        $encodedMeta = null;
        if (!is_null($meta)) {
            $encodedMeta = is_scalar($meta) ? $meta : json_encode($meta, JSON_UNESCAPED_UNICODE);
        }

        $success = $stmt->execute([
            ':opt_id'    => $opt_id,
            ':name'      => $name,
            ':value'     => $encodedValue,
            ':unit'      => $unit,
            ':deviation' => $deviation,
            ':meta'      => $encodedMeta
        ]);

        if (!$success) {
            $errorInfo = $stmt->errorInfo();
            throw new Exception("Failed to insert ResultsDetail: " . implode(" | ", $errorInfo));
        }

        return $this->pdo->lastInsertId();
    }
}
