<?php

declare(strict_types=1);

require_once '/var/www/html/vendor/autoload.php';

// FIXED: Changed from App\Support to App\Helpers to match your actual file directory
use App\Helpers\SqlHelper;
use App\Support\DatabaseSeeder;

$dbHost = 'db';
$dbName = 'mvc_app';
$dbUser = 'root';
$dbPass = 'secret';

try {
    $pdo = new \PDO(
        "mysql:host={$dbHost};dbname={$dbName};charset=utf8mb4",
        $dbUser,
        $dbPass,
        [
            \PDO::ATTR_ERRMODE            => \PDO::ERRMODE_EXCEPTION,
            \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
            \PDO::ATTR_EMULATE_PREPARES   => false,
            \PDO::ATTR_TIMEOUT            => 5
        ]
    );

    SqlHelper::setConnection($pdo);
    DatabaseSeeder::run();

} catch (\PDOException $e) {
    echo "\033[1;31mDocker DB Connection Failure: " . $e->getMessage() . "\033[0m\n";
    exit(1);
} catch (\Throwable $e) {
    echo "\033[1;31mFatal Processing Error: " . $e->getMessage() . "\033[0m\n";
    exit(1);
}
