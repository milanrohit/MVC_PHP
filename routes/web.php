<?php
declare(strict_types=1);

use App\Backend\Controllers\HomeController as BackendHome;
use App\Frontend\Controllers\HomeController as FrontendHome;
use Core\Router;

/** @var Router $router */

// ----------------------------------------------------
// 1. PUBLIC FRONTEND ROUTES
// ----------------------------------------------------
$router->get('/', [FrontendHome::class, 'index']);

// ----------------------------------------------------
// 2. API ROUTES (used by the jQuery users demo on the home page)
// ----------------------------------------------------
$router->get('/api/users', [FrontendHome::class, 'users']);
$router->post('/api/users', [FrontendHome::class, 'store']);

// ----------------------------------------------------
// 3. BACKEND (ADMIN) ROUTES
// ----------------------------------------------------
$router->get('/admin', [BackendHome::class, 'index']);
