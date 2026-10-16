<?php
// Usage: php database/migrate.php   (or: docker compose exec app php database/migrate.php)
declare(strict_types=1);

define('BASE_PATH', dirname(__DIR__));
require BASE_PATH . '/vendor/autoload.php';

Core\Env::load(BASE_PATH . '/.env');
Core\Config::load(BASE_PATH . '/config');

$ran = (new Core\Migrator(Core\Database::connection(), __DIR__ . '/migrations'))->run();
echo $ran ? 'Ran: ' . implode(', ', $ran) . PHP_EOL : "Nothing to migrate." . PHP_EOL;
