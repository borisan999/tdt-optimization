<?php
require_once __DIR__ . '/../config/db.php';

class DerivadorModel
{
    private $pdo;

    public function __construct()
    {
        $this->pdo = (new Database())->getConnection();
    }

    public function getAll(): array
    {
        return $this->pdo
            ->query("SELECT * FROM derivadores ORDER BY derivacion ASC")
            ->fetchAll();
    }

    public function getById(int $id): ?array
    {
        $stmt = $this->pdo->prepare(
            "SELECT * FROM derivadores WHERE deriv_id = ?"
        );
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    public function insert(array $d): void
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO derivadores
                (modelo, derivacion, paso, salidas, perdida_insercion, descripcion)
            VALUES (?, ?, ?, ?, ?, ?)
        ");

        $stmt->execute([
            $d['modelo'],
            $d['derivacion'],
            $d['paso'],
            $d['salidas'],
            $d['perdida_insercion'],
            $d['descripcion'],
        ]);
    }

    public function update(array $d): void
    {
        $stmt = $this->pdo->prepare("
            UPDATE derivadores SET
                modelo = ?,
                derivacion = ?,
                paso = ?,
                salidas = ?,
                perdida_insercion = ?,
                descripcion = ?
            WHERE deriv_id = ?
        ");

        $stmt->execute([
            $d['modelo'],
            $d['derivacion'],
            $d['paso'],
            $d['salidas'],
            $d['perdida_insercion'],
            $d['descripcion'],
            $d['deriv_id'],
        ]);
    }

    public function delete(int $id): void
    {
        $stmt = $this->pdo->prepare(
            "DELETE FROM derivadores WHERE deriv_id = ?"
        );
        $stmt->execute([$id]);
    }
}
