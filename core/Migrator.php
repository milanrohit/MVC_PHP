<?php
declare(strict_types=1);

namespace Core;

use PDO;

final class Migrator
{
    public function __construct(private readonly PDO $pdo, private readonly string $dir) {}

    /** @return string[] names of migrations that were run */
    public function run(): array
    {
        $this->pdo->exec('CREATE TABLE IF NOT EXISTS migrations (
            name VARCHAR(190) PRIMARY KEY,
            ran_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB');

        $done = $this->pdo->query('SELECT name FROM migrations')->fetchAll(PDO::FETCH_COLUMN);
        $files = glob($this->dir . '/*.sql') ?: [];
        sort($files);

        $ran = [];
        foreach ($files as $file) {
            $name = basename($file);
            if (in_array($name, $done, true)) {
                continue;
            }
            $this->pdo->exec((string) file_get_contents($file));
            $this->pdo->prepare('INSERT INTO migrations (name) VALUES (:n)')->execute(['n' => $name]);
            $ran[] = $name;
        }
        return $ran;
    }
}
