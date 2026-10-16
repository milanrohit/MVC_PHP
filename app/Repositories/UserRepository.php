<?php
declare(strict_types=1);

namespace App\Repositories;

use App\Models\User;
use App\Repositories\Contracts\UserRepositoryInterface;
use PDO;

final class UserRepository implements UserRepositoryInterface
{
    public function __construct(private readonly PDO $pdo) {}

    public function all(): array
    {
        $rows = $this->pdo->query('SELECT id, name, email, created_at FROM users ORDER BY id DESC')->fetchAll();
        return array_map([User::class, 'fromRow'], $rows);
    }

    public function findByEmail(string $email): ?User
    {
        $stmt = $this->pdo->prepare('SELECT id, name, email, created_at FROM users WHERE email = :email LIMIT 1');
        $stmt->execute(['email' => $email]);
        $row = $stmt->fetch();
        return $row ? User::fromRow($row) : null;
    }

    public function create(string $name, string $email): User
    {
        $stmt = $this->pdo->prepare('INSERT INTO users (name, email) VALUES (:name, :email)');
        $stmt->execute(['name' => $name, 'email' => $email]);
        return new User((int) $this->pdo->lastInsertId(), $name, $email);
    }
}
