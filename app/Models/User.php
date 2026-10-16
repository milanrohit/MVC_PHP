<?php
declare(strict_types=1);

namespace App\Models;

final class User
{
    public function __construct(
        public readonly ?int $id,
        public readonly string $name,
        public readonly string $email,
        public readonly ?string $createdAt = null
    ) {}

    public static function fromRow(array $row): self
    {
        return new self((int) $row['id'], $row['name'], $row['email'], $row['created_at'] ?? null);
    }

    public function toArray(): array
    {
        return ['id' => $this->id, 'name' => $this->name, 'email' => $this->email, 'created_at' => $this->createdAt];
    }
}
