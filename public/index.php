<?php
declare(strict_types=1);

define('BASE_PATH', dirname(__DIR__));

$autoload = BASE_PATH . '/vendor/autoload.php';
if (!file_exists($autoload)) {
    http_response_code(500);
    exit('Run "composer install" first.');
}
require $autoload;

(new Core\Application(BASE_PATH))->run();
