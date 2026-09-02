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
// Health Check & Base Routes
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
// Route Registrations (Placeholder routes dispatched to controllers in next phases)
// -----------------------------------------------------------------------------
$router->group(['prefix' => '/api'], function (Router $api) {
    // Phase 5: Product routes
    // Phase 6: Pincode & Coupon routes
    // Phase 7: Order & Checkout routes
    // Phase 8: Tracking routes
    // Phase 9: Newsletter & Lookbook routes
    // Phase 10-11: Admin routes
});

// 4. Dispatch Request & Emit Response
$response = $router->dispatch($request);
$response->send();
