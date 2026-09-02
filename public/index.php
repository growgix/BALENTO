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
use App\Controllers\PincodeController;
use App\Controllers\CouponController;
use App\Controllers\OrderController;
use App\Controllers\NewsletterController;
use App\Controllers\LookbookController;
use App\Controllers\AdminController;

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
// Public Storefront Routes
// -----------------------------------------------------------------------------
$router->group(['prefix' => '/api'], function (Router $api) {
    // Products Catalog
    $api->get('/products', [ProductController::class, 'index']);
    $api->get('/products/{slug_or_id}', [ProductController::class, 'show']);

    // Pincode & Delivery Serviceability
    $api->post('/pincode/check', [PincodeController::class, 'check'], [RateLimitMiddleware::forRoute('pincode')]);

    // Coupon & Pricing Validation
    $api->post('/coupons/validate', [CouponController::class, 'validate'], [RateLimitMiddleware::forRoute('coupon')]);

    // Atomic Checkout & Orders
    $api->post('/orders/checkout', [OrderController::class, 'checkout'], [RateLimitMiddleware::forRoute('checkout')]);

    // Public Order Tracking
    $api->get('/orders/track/{order_number}', [OrderController::class, 'track']);

    // Newsletter Subscription
    $api->post('/newsletter/subscribe', [NewsletterController::class, 'subscribe'], [RateLimitMiddleware::forRoute('general')]);

    // Curated Street Style Lookbook
    $api->get('/lookbook', [LookbookController::class, 'index']);

    // Admin Public Login
    $api->post('/admin/login', [AdminController::class, 'login'], [RateLimitMiddleware::forRoute('login')]);
});

// -----------------------------------------------------------------------------
// Protected Admin Backoffice Routes
// -----------------------------------------------------------------------------
$router->group(['prefix' => '/api/admin', 'middleware' => new AuthMiddleware(['admin', 'manager'])], function (Router $admin) {
    // Profile & Diagnostics
    $admin->get('/me', [AdminController::class, 'me']);
    $admin->get('/dashboard/stats', [AdminController::class, 'dashboardStats']);

    // Orders Management
    $admin->get('/orders', [AdminController::class, 'orders']);
    $admin->put('/orders/{id}/status', [AdminController::class, 'updateOrderStatus']);

    // Product Management (CRUD)
    $admin->post('/products', [AdminController::class, 'createProduct']);
    $admin->put('/products/{id}', [AdminController::class, 'updateProduct']);
    $admin->delete('/products/{id}', [AdminController::class, 'deleteProduct']);

    // Inventory Control
    $admin->put('/inventory/adjust', [AdminController::class, 'adjustInventory']);

    // Coupons Management
    $admin->post('/coupons', [AdminController::class, 'createCoupon']);
});

// 4. Dispatch Request & Emit Response
$response = $router->dispatch($request);
$response->send();
