<?php

// Usage: php database/migrate.php   (or: docker compose exec app php database/migrate.php)

declare(strict_types=1);

use Core\Config;
use Core\Database;
use Core\Env;
use Core\Migrator;

define('BASE_PATH', dirname(__DIR__));
require_once BASE_PATH . '/vendor/autoload.php';

Env::load(BASE_PATH . '/.env');
Config::load(BASE_PATH . '/config');

function printCliStatus(string $badgeColor, string $label, string $message): void
{
    echo PHP_EOL . "{$badgeColor}\033[37m {$label} \033[0m {$message}" . PHP_EOL;
}

function exitWithError(string $message): never
{
    printCliStatus("\033[41m", 'ERROR', $message);
    exit(1);
}

function ensureDatabaseReady(string $dbName): void
{
    if (preg_match('/^\w{1,64}$/', $dbName) !== 1) {
        exitWithError('Security Check Failed: Invalid database name configuration format.');
    }

    $dbHost = (string) Config::get('database.host', 'db');
    $dbPort = (string) Config::get('database.port', '3306');
    $dbUser = (string) Config::get('database.username', 'root');
    $dbPass = (string) Config::get('database.password', 'secret');
    $dsn = "mysql:host={$dbHost};port={$dbPort};charset=utf8mb4";

    try {
        $pdo = new PDO($dsn, $dbUser, $dbPass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);

        $stmt = $pdo->prepare('SELECT SCHEMA_NAME FROM INFORMATION_SCHEMA.SCHEMATA WHERE SCHEMA_NAME = :dbname');
        $stmt->execute(['dbname' => $dbName]);

        if ($stmt->fetchColumn() === false) {
            createDatabase($pdo, $dbName);

            return;
        }

        verifyExistingDatabase($pdo, $dbName);
    } catch (PDOException $e) {
        exitWithError('Database Initialization Error: ' . $e->getMessage());
    }
}

function createDatabase(PDO $pdo, string $dbName): void
{
    $quotedDbName = '`' . $dbName . '`';

    try {
        // Identifiers cannot be bound as SQL parameters; $dbName passed strict allowlist validation.
        $pdo->exec("CREATE DATABASE {$quotedDbName} CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"); // NOSONAR

        echo PHP_EOL . "\033[42m\033[30m SUCCESS \033[0m Database "
            . "\033[1;33m'{$dbName}'\033[0m was created successfully." . PHP_EOL;
    } catch (PDOException $e) {
        exitWithError("Creating database '{$dbName}': " . $e->getMessage());
    }
}

function verifyExistingDatabase(PDO $pdo, string $dbName): void
{
    $quotedDbName = '`' . $dbName . '`';

    try {
        // Identifiers cannot be bound; $dbName passed strict allowlist validation.
        $pdo->exec(sprintf('USE %s', $quotedDbName)); // NOSONAR
    } catch (PDOException $e) {
        exitWithError("Selecting database '{$dbName}': " . $e->getMessage());
    }

    $tableCheck = $pdo->prepare(
        'SELECT 1 FROM INFORMATION_SCHEMA.TABLES '
        . 'WHERE TABLE_SCHEMA = :dbname AND TABLE_NAME = :table_name LIMIT 1',
    );
    $tableCheck->execute([
        'dbname' => $dbName,
        'table_name' => 'migrations',
    ]);

    if ($tableCheck->fetchColumn() !== false) {
        echo PHP_EOL . "\033[43m\033[30m WARNING \033[0m "
            . 'DATABASE STRUCTURE ALREADY EXISTS. INITIAL MIGRATIONS CANNOT BE RE-RUN.' . PHP_EOL . PHP_EOL;
        exit(0);
    }
}

function executeMigrations(): void
{
    try {
        $ran = (new Migrator(Database::connection(), __DIR__ . '/migrations'))->run();

        if ($ran === []) {
            echo PHP_EOL . "\033[1;34mℹ Info:\033[0m "
                . 'Nothing new to migrate. Database is already up to date.' . PHP_EOL . PHP_EOL;

            return;
        }

        $border = str_repeat('─', 56);
        echo PHP_EOL . "\033[1;36m┌{$border}┐\033[0m" . PHP_EOL;
        echo "\033[1;36m│ \033[1;32m✔\033[0m \033[1;37mMIGRATIONS EXECUTED SUCCESSFULLY"
            . str_repeat(' ', 22) . "\033[1;36m│\033[0m" . PHP_EOL;
        echo "\033[1;36m└{$border}┘\033[0m" . PHP_EOL;

        foreach ($ran as $migration) {
            echo "  \033[32m✔\033[0m \033[37m{$migration}\033[0m" . PHP_EOL;
        }
        echo PHP_EOL;
    } catch (Throwable $e) {
        exitWithError('Migration Execution Error: ' . $e->getMessage());
    }
}

function runDatabaseMigrations(): void
{
    $dbName = (string) Config::get('database.database', 'mvc_app');
    ensureDatabaseReady($dbName);
    executeMigrations();
}

runDatabaseMigrations();
