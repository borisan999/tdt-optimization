<?php
require_once __DIR__ . '/../config/db.php';

class UserModel
{
    private $pdo;

    public function __construct()
    {
        $db = new Database();
        $this->pdo = $db->getConnection();
    }

    public function getAll(): array
    {
        return $this->pdo
            ->query(
                'SELECT user_id, username, email, is_active
                FROM users
                WHERE is_active = 1
                ORDER BY username'
            )
            ->fetchAll();
    }

    public function getAllIncludingInactive(): array
    {
        return $this->pdo
            ->query(
                'SELECT user_id, username, email, is_active
                FROM users
                ORDER BY username'
            )
            ->fetchAll();
    }


    public function getById(int $id): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT user_id, username FROM users WHERE user_id = :id'
        );
        $stmt->execute(['id' => $id]);
        return $stmt->fetch() ?: null;
    }

    public function insert(array $data): void
    {
        $sql = "
            INSERT INTO users (username, email, password_hash, is_active)
            VALUES (:username, :email, :password_hash, :is_active)
        ";

        $stmt = $this->pdo->prepare($sql);

        $stmt->execute([
            ':username'      => $data['username'],
            ':email'         => $data['email'] ?? null,
            ':password_hash' => password_hash($data['password'], PASSWORD_DEFAULT),
            ':is_active'     => 1,
        ]);
    }


    public function update(array $data): void
    {
        $fields = [
            'username = :username',
            'email = :email',
            'is_active = :is_active',
        ];

        $params = [
            ':username'  => $data['username'],
            ':email'     => $data['email'] ?? null,
            ':is_active' => $data['is_active'] ?? 1,
            ':user_id'   => $data['user_id'],
        ];

        // Optional password update
        if (!empty($data['password'])) {
            $fields[] = 'password_hash = :password_hash';
            $params[':password_hash'] = password_hash($data['password'], PASSWORD_DEFAULT);
        }

        $sql = "
            UPDATE users
            SET " . implode(', ', $fields) . "
            WHERE user_id = :user_id
        ";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
    }


    public function disable(int $id): void
    {
        $stmt = $this->pdo->prepare(
            'UPDATE users SET is_active = 0 WHERE user_id = :id'
        );
        $stmt->execute(['id' => $id]);
    }

    public function findByUsername(string $username): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM users WHERE username = :username LIMIT 1'
        );
        $stmt->execute(['username' => $username]);
        return $stmt->fetch() ?: null;
    }

    public function reactivate(int $userId, array $data): void
    {
        $stmt = $this->pdo->prepare(
            'UPDATE users
            SET password_hash = :password_hash,
                email = :email,
                is_active = 1
            WHERE user_id = :user_id'
        );

        $stmt->execute([
            ':password_hash' => password_hash($data['password'], PASSWORD_DEFAULT),
            ':email'         => $data['email'] ?? null,
            ':user_id'       => $userId,
        ]);
    }


}
