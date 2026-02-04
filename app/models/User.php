<?php
declare(strict_types=1);

class User
{
    public static function findByUsername(PDO $pdo, string $username): ?array
    {
        $stmt = $pdo->prepare(
            'SELECT user_id, username, password_hash FROM users WHERE username = :username  AND is_active = 1 LIMIT 1'
        );
        $stmt->execute(['username' => $username]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        return $user ?: null;
    }
}
