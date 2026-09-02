<?php

declare(strict_types=1);

/**
 * BALENTO E-Commerce REST API Front Controller.
 */

// 1. Initialize Autoloader
require_once __DIR__ . '/../src/Core/Autoloader.php';
\App\Core\Autoloader::register();

use App\Core\Env;
use App\Core\Config;
use App\Core\Request;
use App\Core\Response;
use App\Core\Router;
use App\Middleware\CorsMiddleware;
use App\Middleware\JsonBodyMiddleware;
use App\Middleware\RateLimitMiddleware;
use App\Middleware\AuthMiddleware;
use App\Controllers\ProductController;

// 2. Load Environment Variables & Configuration
$rootDir = dirname(__DIR__);
Env::load($rootDir . '/.env');
if (!Env::get('APP_NAME')) {
    Env::load($rootDir . '/.env.example');
}
Config::init($rootDir . '/config');

// Set Application Timezone
date_default_timezone_set((string) Config::get('app.timezone', 'Asia/Kolkata'));

// 3. Build HTTP Request & Router
$request = Request::createFromGlobals();
$router = new Router();

// Attach Global Middlewares
$router->use(new CorsMiddleware());
$router->use(new JsonBodyMiddleware());

// -----------------------------------------------------------------------------
// Health Check & Diagnostic Routes
// -----------------------------------------------------------------------------
$router->get('/api/health', function (Request $req) {
    return Response::success([
        'status' => 'healthy',
        'service' => Config::get('app.name', 'BALENTO API'),
        'environment' => Config::get('app.env', 'production'),
        'timestamp' => date('c'),
    ], 'BALENTO API is running seamlessly.');
});

// -----------------------------------------------------------------------------
// Public Product Catalog Routes
// -----------------------------------------------------------------------------
$router->group(['prefix' => '/api'], function (Router $api) {
    $api->get('/products', [ProductController::class, 'index']);
    $api->get('/products/{slug_or_id}', [ProductController::class, 'show']);
});

// 4. Dispatch Request & Emit Response
$response = $router->dispatch($request);
$response->send();
