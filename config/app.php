<?php
return [
    'name'      => Core\Env::get('APP_NAME', 'MVC_PHP_ECOMMERCE'),
    'env'       => Core\Env::get('APP_ENV', 'local'),
    'debug'     => filter_var(Core\Env::get('APP_DEBUG', 'false'), FILTER_VALIDATE_BOOLEAN),
    'base_path' => Core\Env::get('APP_BASE_PATH', ''),
];
