<?php
declare(strict_types=1);

namespace App\Services;

use App\Models\User;
use App\Repositories\Contracts\UserRepositoryInterface;
use InvalidArgumentException;

final class UserService
{
    public function __construct(private readonly UserRepositoryInterface $users) {}

    /** @return User[] */
    public function list(): array
    {
        return $this->users->all();
    }

    public function register(string $name, string $email): User
    {
        $name = trim($name);
        $email = strtolower(trim($email));

        if ($name === '' || mb_strlen($name) > 100) {
            throw new InvalidArgumentException('Name is required (max 100 characters).');
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException('A valid email is required.');
        }
        if ($this->users->findByEmail($email)) {
            throw new InvalidArgumentException('That email is already registered.');
        }
        return $this->users->create($name, $email);
    }
}
