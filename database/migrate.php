<?php
// Usage: php database/migrate.php   (or: docker compose exec app php database/migrate.php)
declare(strict_types=1);

define('BASE_PATH', dirname(__DIR__));
require_once BASE_PATH . '/vendor/autoload.php';

Core\Env::load(BASE_PATH . '/.env');
Core\Config::load(BASE_PATH . '/config');

/**
 * Execute database structural checks and run migrations.
 */
function runDatabaseMigrations(): void
{
    // 1. Fetch connection variables from configuration
    $dbHost = (string) Core\Config::get('database.host', 'db');
    $dbPort = (string) Core\Config::get('database.port', '3306');
    $dbUser = (string) Core\Config::get('database.username', 'root');
    $dbPass = (string) Core\Config::get('database.password', 'secret');
    $dbName = (string) Core\Config::get('database.database', 'mvc_app');

    // 2. Strict Early Validation (Satisfies SonarLint S2077 and ensures engine safety)
    if (!preg_match('/^\w{1,64}$/', $dbName)) {
        echo PHP_EOL . "\033[41m\033[37m ERROR \033[0m Security Check Failed: Invalid database name configuration format." . PHP_EOL;
        exit(1);
    }

    // The strict allowlist above excludes SQL syntax; quote this identifier for MySQL.
    $sanitizedDbName = $dbName;
    $quotedDbName = '`' . $dbName . '`';

    try {
        // 3. Connect to the DBMS directly without selecting a DB
        $dsnWithoutDb = "mysql:host={$dbHost};port={$dbPort};charset=utf8mb4";
        $pdoInit = new PDO($dsnWithoutDb, $dbUser, $dbPass, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);

        // Check if the database already exists using a safe parameterized query
        $stmt = $pdoInit->prepare("SELECT SCHEMA_NAME FROM INFORMATION_SCHEMA.SCHEMATA WHERE SCHEMA_NAME = :dbname");
        $stmt->execute(['dbname' => $sanitizedDbName]);
        $dbExists = $stmt->fetchColumn() !== false;

        if (!$dbExists) {
            try {
                // Database identifiers cannot be bound as SQL parameters; $dbName passed strict allowlist validation above.
                $pdoInit->exec("CREATE DATABASE {$quotedDbName} CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"); // NOSONAR
                
                // Beautiful Database Creation Badge
                echo PHP_EOL . "\033[42m\033[30m SUCCESS \033[0m Database \033[1;33m'{$sanitizedDbName}'\033[0m was created successfully." . PHP_EOL;
            } catch (PDOException $e) {
                echo PHP_EOL . "\033[41m\033[37m ERROR \033[0m Creating database '{$sanitizedDbName}': " . $e->getMessage() . PHP_EOL;
                exit(1);
            }
        } else {
            // Select the existing database
            try {
                // Identifiers cannot be bound; this value is quoted after strict allowlist validation.
                $pdoInit->exec(sprintf('USE %s', $quotedDbName));
            } catch (PDOException $e) {
                echo PHP_EOL . "\033[41m\033[37m ERROR \033[0m Selecting database '{$sanitizedDbName}': " . $e->getMessage() . PHP_EOL;
                exit(1);
            }

            // Parameterized query checking against system catalog tables
            $tableCheck = $pdoInit->prepare(
                'SELECT 1 FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = :dbname AND TABLE_NAME = :table_name LIMIT 1'
            );
            $tableCheck->execute([
                'dbname'     => $sanitizedDbName,
                'table_name' => 'migrations'
            ]);
            
            // PHP 8.5+ Strict type verification on column fetch
            if ($tableCheck->fetchColumn() !== false) {
                echo PHP_EOL . "\033[43m\033[30m WARNING \033[0m DATABASE STRUCTURE ALREADY EXISTS. INITIAL MIGRATIONS CANNOT BE RE-RUN." . PHP_EOL . PHP_EOL;
                exit(0);
            }
        }

    } catch (PDOException $e) {
        echo PHP_EOL . "\033[41m\033[37m ERROR \033[0m Database Initialization Error: " . $e->getMessage() . PHP_EOL;
        exit(1);
    }

    // 4. Run your migration engine now that safety checks are cleared
    try {
        $ran = (new Core\Migrator(Core\Database::connection(), __DIR__ . '/migrations'))->run();

        if ($ran) {
            // Beautiful Bordered Header
            echo PHP_EOL . "\033[1;36m┌────────────────────────────────────────────────────────┐\033[0m" . PHP_EOL;
            echo "\033[1;36m│ \033[1;32m✔\033[0m \033[1;37mMIGRATIONS EXECUTED SUCCESSFULLY                      \033[1;36m│\033[0m" . PHP_EOL;
            echo "\033[1;36m└────────────────────────────────────────────────────────┘\033[0m" . PHP_EOL;

            // Punchy Bullet Points for each migration file
            foreach ($ran as $migration) {
                echo "  \033[32m✔\033[0m \033[37m{$migration}\033[0m" . PHP_EOL;
            }
            echo PHP_EOL;
        } else {
            echo PHP_EOL . "\033[1;34mℹ Info:\033[0m Nothing new to migrate. Database is already up to date." . PHP_EOL . PHP_EOL;
        }
    } catch (Exception $e) {
        echo PHP_EOL . "\033[41m\033[37m ERROR \033[0m Migration Execution Error: " . $e->getMessage() . PHP_EOL;
        exit(1);
    }
}

// Execute the routine
runDatabaseMigrations();
