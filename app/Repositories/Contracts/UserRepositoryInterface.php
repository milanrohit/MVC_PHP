<?php
declare(strict_types=1);

namespace App\Repositories\Contracts;

use App\Models\User;

interface UserRepositoryInterface
{
    /** @return User[] */
    public function all(): array;

    public function findByEmail(string $email): ?User;

    public function create(string $name, string $email): User;
}
