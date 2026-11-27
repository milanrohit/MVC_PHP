<?php

declare(strict_types=1);

use Core\Env;

// Environment-aware host: explicit DB_HOST wins; inside Docker default to "db"; XAMPP -> 127.0.0.1
$inDocker = file_exists('/.dockerenv');
$dbHost = Env::get('DB_HOST') ?: ($inDocker ? 'db' : '127.0.0.1');

return [
    'driver'   => 'mysql',
    'host'     => $dbHost,
    'port'     => (int) Env::get('DB_PORT', '3306'),
    'database' => Env::get('DB_DATABASE', 'mvc_app'),
    'username' => Env::get('DB_USERNAME', 'root'),
    'password' => Env::get('DB_PASSWORD', ''),
    'charset'  => 'utf8mb4',
];
