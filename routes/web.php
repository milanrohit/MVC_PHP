<?php
declare(strict_types=1);

use Core\Router;
use App\Frontend\Controllers\HomeController as FrontendHome;
use App\Backend\Controllers\HomeController as BackendHome;

/** @var Router $router */

// ----------------------------------------------------
// 1. PUBLIC FRONTEND ROUTES
// ----------------------------------------------------
$router->get('/', [FrontendHome::class, 'index']);


// ----------------------------------------------------
// 2. API / BACKEND DATA ROUTES
// ----------------------------------------------------
$router->get('/api/users', [BackendHome::class, 'users']);
$router->post('/api/users', [BackendHome::class, 'store']);

// Optional: Add an explicit route to access the new backend panel index
$router->get('/admin', [BackendHome::class, 'index']);
