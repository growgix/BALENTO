<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/Core/Autoloader.php';
\App\Core\Autoloader::register();

use App\Core\Env;
use App\Core\Config;
use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use App\Core\Router;
use App\Middleware\AuthMiddleware;
use App\Controllers\AdminController;
use App\Services\AuthService;

echo "=== TESTING PHASE 10 & 11: ADMIN AUTHENTICATION & MANAGEMENT APIS ===\n";

Env::load(__DIR__ . '/../.env.example');
Config::init(__DIR__ . '/../config');

// 1. Setup SQLite in-memory test database
$pdo = new PDO('sqlite::memory:', null, null, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
]);

$pdo->exec("
    CREATE TABLE admins (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        username TEXT NOT NULL UNIQUE,
        email TEXT NOT NULL UNIQUE,
        password_hash TEXT NOT NULL,
        role TEXT DEFAULT 'admin',
        is_active INTEGER DEFAULT 1,
        last_login_at DATETIME,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
    );

    CREATE TABLE categories (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        name TEXT NOT NULL,
        slug TEXT NOT NULL
    );

    CREATE TABLE products (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        category_id INTEGER NOT NULL,
        name TEXT NOT NULL,
        slug TEXT NOT NULL UNIQUE,
        tag TEXT,
        price NUMERIC NOT NULL,
        compare_at_price NUMERIC,
        description TEXT NOT NULL,
        dimensions TEXT,
        weight TEXT,
        is_active INTEGER DEFAULT 1,
        sort_order INTEGER DEFAULT 0,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
    );

    CREATE TABLE product_variants (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        product_id INTEGER NOT NULL,
        sku TEXT NOT NULL UNIQUE,
        color_name TEXT NOT NULL,
        color_hex TEXT NOT NULL,
        stock_quantity INTEGER DEFAULT 0,
        is_active INTEGER DEFAULT 1,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
    );

    CREATE TABLE product_features (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        product_id INTEGER NOT NULL,
        feature_text TEXT NOT NULL,
        sort_order INTEGER DEFAULT 0,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    );

    CREATE TABLE product_images (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        product_id INTEGER NOT NULL,
        variant_id INTEGER,
        image_url TEXT NOT NULL,
        alt_text TEXT,
        image_type TEXT DEFAULT 'gallery',
        sort_order INTEGER DEFAULT 0,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    );

    CREATE TABLE orders (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        order_number TEXT NOT NULL UNIQUE,
        customer_name TEXT NOT NULL,
        customer_email TEXT NOT NULL,
        customer_phone TEXT NOT NULL,
        shipping_address TEXT NOT NULL,
        city TEXT NOT NULL,
        state TEXT DEFAULT 'India',
        pincode TEXT NOT NULL,
        subtotal NUMERIC NOT NULL,
        discount_amount NUMERIC DEFAULT 0.00,
        shipping_fee NUMERIC DEFAULT 0.00,
        total_amount NUMERIC NOT NULL,
        coupon_code TEXT,
        payment_method TEXT DEFAULT 'upi',
        payment_status TEXT DEFAULT 'pending',
        order_status TEXT DEFAULT 'placed',
        is_gift INTEGER DEFAULT 0,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
    );

    CREATE TABLE coupons (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        code TEXT NOT NULL UNIQUE,
        discount_type TEXT DEFAULT 'percentage',
        discount_value NUMERIC NOT NULL,
        min_order_amount NUMERIC DEFAULT 0.00,
        max_discount_cap NUMERIC,
        usage_limit INTEGER,
        usage_count INTEGER DEFAULT 0,
        is_active INTEGER DEFAULT 1,
        starts_at DATETIME,
        expires_at DATETIME,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
    );

    CREATE TABLE newsletter_subscribers (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        email TEXT NOT NULL UNIQUE,
        is_active INTEGER DEFAULT 1
    );

    CREATE TABLE audit_logs (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        admin_id INTEGER,
        admin_username TEXT,
        action TEXT,
        entity_type TEXT,
        entity_id TEXT,
        details TEXT,
        ip_address TEXT,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    );
");

// Seed default admin (Password: Password@123)
$adminHash = AuthService::hashPassword('Password@123');
$pdo->exec("
    INSERT INTO admins (id, username, email, password_hash, role, is_active) VALUES
    (1, 'admin', 'admin@balento.com', '{$adminHash}', 'admin', 1);

    INSERT INTO categories (id, name, slug) VALUES (1, 'Totes', 'tote');
    
    INSERT INTO products (id, category_id, name, slug, price, description, is_active) VALUES
    (1, 1, 'Verona Tote', 'verona-tote', 2499.00, 'Spacious tote', 1);

    INSERT INTO product_variants (id, product_id, sku, color_name, color_hex, stock_quantity, is_active) VALUES
    (1, 1, 'BAL-VER-BLK', 'Black', '#1c1b1b', 5, 1);

    INSERT INTO orders (id, order_number, customer_name, customer_email, customer_phone, shipping_address, city, pincode, subtotal, total_amount, payment_status, order_status) VALUES
    (1, 'BAL-2026-112233', 'Priya Mehta', 'priya@example.com', '9876543210', 'Address', 'Bengaluru', '560034', 2499.00, 2499.00, 'paid', 'placed');
");

Database::setConnection('mysql', $pdo);

// Setup router with admin routes
$router = new Router();
$router->post('/api/admin/login', [AdminController::class, 'login']);

$router->group(['prefix' => '/api/admin', 'middleware' => new AuthMiddleware(['admin'])], function (Router $admin) {
    $admin->get('/me', [AdminController::class, 'me']);
    $admin->get('/dashboard/stats', [AdminController::class, 'dashboardStats']);
    $admin->get('/orders', [AdminController::class, 'orders']);
    $admin->put('/orders/{id}/status', [AdminController::class, 'updateOrderStatus']);
    $admin->post('/products', [AdminController::class, 'createProduct']);
    $admin->put('/products/{id}', [AdminController::class, 'updateProduct']);
    $admin->delete('/products/{id}', [AdminController::class, 'deleteProduct']);
    $admin->put('/inventory/adjust', [AdminController::class, 'adjustInventory']);
    $admin->post('/coupons', [AdminController::class, 'createCoupon']);
});

// 2. Test Admin Login (Successful)
$loginReq = new Request('POST', '/api/admin/login', [], ['username' => 'admin', 'password' => 'Password@123'], '{"username":"admin","password":"Password@123"}', ['content-type' => 'application/json']);
$loginRes = $router->dispatch($loginReq);

assert($loginRes->getStatusCode() === 200);
$authData = $loginRes->getPayload()['data'];
assert(!empty($authData['token']));
assert($authData['admin']['username'] === 'admin');
$authToken = $authData['token'];
echo " [OK] Admin Auth: Login with Argon2id/Bcrypt hash verification & JWT token issuance verified\n";

// 3. Test Admin Login (Failed)
$badLoginReq = new Request('POST', '/api/admin/login', [], ['username' => 'admin', 'password' => 'WrongPassword!'], '{"username":"admin","password":"WrongPassword!"}', ['content-type' => 'application/json']);
$badLoginRes = $router->dispatch($badLoginReq);
assert($badLoginRes->getStatusCode() === 401);
echo " [OK] Admin Auth: Invalid password rejection (401) verified\n";

// 4. Test Protected Profile (GET /api/admin/me)
$meReq = new Request('GET', '/api/admin/me', [], [], '', ['authorization' => "Bearer {$authToken}"]);
$meRes = $router->dispatch($meReq);
assert($meRes->getStatusCode() === 200);
assert($meRes->getPayload()['data']['username'] === 'admin');
echo " [OK] Admin Guard: Protected profile route verified\n";

// 5. Test Dashboard Statistics (GET /api/admin/dashboard/stats)
$dashReq = new Request('GET', '/api/admin/dashboard/stats', [], [], '', ['authorization' => "Bearer {$authToken}"]);
$dashRes = $router->dispatch($dashReq);
assert($dashRes->getStatusCode() === 200);
$stats = $dashRes->getPayload()['data'];
assert($stats['total_revenue'] === 2499.00);
assert($stats['total_orders'] === 1);
assert($stats['orders_by_status']['placed'] === 1);
assert(count($stats['low_stock_alerts']) === 1);
assert($stats['low_stock_alerts'][0]['stock_quantity'] === 5);
echo " [OK] Admin Dashboard: Aggregate revenue, status counts, and low-stock alerts verified\n";

// 6. Test Orders Management (GET /api/admin/orders & PUT /api/admin/orders/{id}/status)
$ordersReq = new Request('GET', '/api/admin/orders', [], [], '', ['authorization' => "Bearer {$authToken}"]);
$ordersRes = $router->dispatch($ordersReq);
assert($ordersRes->getStatusCode() === 200);
assert(count($ordersRes->getPayload()['data']['orders']) === 1);

// Update order status to 'shipped'
$updateStatusReq = new Request('PUT', '/api/admin/orders/1/status', [], ['order_status' => 'shipped'], '{"order_status":"shipped"}', [
    'content-type' => 'application/json',
    'authorization' => "Bearer {$authToken}",
]);
$updateStatusRes = $router->dispatch($updateStatusReq);
assert($updateStatusRes->getStatusCode() === 200);
$updatedStatus = $pdo->query("SELECT order_status FROM orders WHERE id = 1")->fetchColumn();
assert($updatedStatus === 'shipped');
echo " [OK] Admin Orders: Orders listing and status updates verified\n";

// 7. Test Product Management (POST /api/admin/products, PUT, DELETE)
$newProductPayload = [
    'category_id' => 1,
    'name' => 'Amalfi Satchel',
    'price' => 2699.00,
    'description' => 'Fine grain luxury satchel.',
];
$createProdReq = new Request('POST', '/api/admin/products', [], $newProductPayload, json_encode($newProductPayload), [
    'content-type' => 'application/json',
    'authorization' => "Bearer {$authToken}",
]);
$createProdRes = $router->dispatch($createProdReq);
assert($createProdRes->getStatusCode() === 201);
$newProdId = (int) $createProdRes->getPayload()['data']['product_id'];

// Update product
$updateProdReq = new Request('PUT', "/api/admin/products/{$newProdId}", [], ['price' => 2799.00], '{"price":2799.00}', [
    'content-type' => 'application/json',
    'authorization' => "Bearer {$authToken}",
]);
$updateProdRes = $router->dispatch($updateProdReq);
assert($updateProdRes->getStatusCode() === 200);
$updatedPrice = (float) $pdo->query("SELECT price FROM products WHERE id = {$newProdId}")->fetchColumn();
assert($updatedPrice === 2799.00);

// Delete (Deactivate) product
$deleteProdReq = new Request('DELETE', "/api/admin/products/{$newProdId}", [], [], '', ['authorization' => "Bearer {$authToken}"]);
$deleteProdRes = $router->dispatch($deleteProdReq);
assert($deleteProdRes->getStatusCode() === 200);
$isActive = (int) $pdo->query("SELECT is_active FROM products WHERE id = {$newProdId}")->fetchColumn();
assert($isActive === 0);
echo " [OK] Admin Products: CRUD operations (create, update price, soft deactivation) verified\n";

// 8. Test Inventory Adjustment (PUT /api/admin/inventory/adjust)
$adjReq = new Request('PUT', '/api/admin/inventory/adjust', [], ['variant_id' => 1, 'adjustment' => 20], '{"variant_id":1,"adjustment":20}', [
    'content-type' => 'application/json',
    'authorization' => "Bearer {$authToken}",
]);
$adjRes = $router->dispatch($adjReq);
assert($adjRes->getStatusCode() === 200);
$newStock = (int) $pdo->query("SELECT stock_quantity FROM product_variants WHERE id = 1")->fetchColumn();
assert($newStock === 25); // 5 + 20 = 25
echo " [OK] Admin Inventory: Stock quantity adjustments verified\n";

// 9. Test Coupon Creation (POST /api/admin/coupons)
$couponPayload = [
    'code' => 'SUMMER20',
    'discount_type' => 'percentage',
    'discount_value' => 20.00,
    'min_order_amount' => 2000.00,
];
$createCoupReq = new Request('POST', '/api/admin/coupons', [], $couponPayload, json_encode($couponPayload), [
    'content-type' => 'application/json',
    'authorization' => "Bearer {$authToken}",
]);
$createCoupRes = $router->dispatch($createCoupReq);
assert($createCoupRes->getStatusCode() === 201);
$newCoupCode = $pdo->query("SELECT code FROM coupons WHERE id = 1")->fetchColumn();
assert($newCoupCode === 'SUMMER20');
echo " [OK] Admin Coupons: Promotion creation verified\n";

echo "\nALL PHASE 10 & 11 ADMIN TESTS PASSED PERFECTLY!\n";
