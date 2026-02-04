<?php

require_once __DIR__ . '/../config/db.php';

class RepartidorModel
{
    private $pdo;

    public function __construct()
    {
        $this->pdo = (new Database())->getConnection();
    }

    public function getAll(): array
    {
        $stmt = $this->pdo->query(
            "SELECT * FROM repartidores ORDER BY salidas ASC"
        );
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getById(int $id): ?array
    {
        $stmt = $this->pdo->prepare(
            "SELECT * FROM repartidores WHERE rep_id = ?"
        );
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function delete(int $id): void
    {
        $stmt = $this->pdo->prepare(
            "DELETE FROM repartidores WHERE rep_id = ?"
        );
        $stmt->execute([$id]);
    }
    public function insert(array $data): void
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO repartidores
                (modelo, perdida_insercion, salidas, frecuencia, descripcion)
            VALUES
                (?, ?, ?, ?, ?)
        ");

        $stmt->execute([
            $data['modelo'],
            $data['perdida_insercion'],
            $data['salidas'],
            $data['frecuencia'],
            $data['descripcion'],
        ]);
    }

    public function update(array $data): void
    {
        $stmt = $this->pdo->prepare("
            UPDATE repartidores
            SET
                modelo = ?,
                perdida_insercion = ?,
                salidas = ?,
                frecuencia = ?,
                descripcion = ?
            WHERE rep_id = ?
        ");

        $stmt->execute([
            $data['modelo'],
            $data['perdida_insercion'],
            $data['salidas'],
            $data['frecuencia'],
            $data['descripcion'],
            $data['rep_id'],
        ]);
    }

}
