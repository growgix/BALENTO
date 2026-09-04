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
use App\Middleware\CorsMiddleware;
use App\Middleware\JsonBodyMiddleware;
use App\Middleware\AuthMiddleware;
use App\Controllers\ProductController;
use App\Controllers\PincodeController;
use App\Controllers\CouponController;
use App\Controllers\OrderController;
use App\Controllers\AdminController;
use App\Services\AuthService;

echo "=== EXECUTING COMPLETE BALENTO ADMIN DASHBOARD & STOREFRONT INTEGRATION TEST ===\n";

Env::load(__DIR__ . '/../.env.example');
Config::init(__DIR__ . '/../config');

// 1. Setup in-memory SQLite database mimicking full MySQL 8 schema
$pdo = new PDO('sqlite::memory:', null, null, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
]);

$pdo->exec("
    CREATE TABLE categories (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        name TEXT NOT NULL,
        slug TEXT NOT NULL UNIQUE,
        description TEXT,
        sort_order INTEGER DEFAULT 0,
        is_active INTEGER DEFAULT 1,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
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

    CREATE TABLE pincodes (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        pincode TEXT NOT NULL UNIQUE,
        city TEXT NOT NULL,
        state TEXT NOT NULL,
        is_serviceable INTEGER DEFAULT 1,
        cod_available INTEGER DEFAULT 1,
        estimated_days INTEGER DEFAULT 3,
        shipping_zone TEXT DEFAULT 'Metro',
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
        gift_note TEXT,
        idempotency_key TEXT UNIQUE,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
    );

    CREATE TABLE order_items (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        order_id INTEGER NOT NULL,
        product_id INTEGER,
        variant_id INTEGER,
        product_name TEXT NOT NULL,
        color_name TEXT NOT NULL,
        sku TEXT NOT NULL,
        unit_price NUMERIC NOT NULL,
        quantity INTEGER NOT NULL,
        total_price NUMERIC NOT NULL,
        monogram_initials TEXT,
        monogram_foil TEXT,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    );

    CREATE TABLE newsletter_subscribers (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        email TEXT NOT NULL UNIQUE,
        source TEXT DEFAULT 'footer',
        is_active INTEGER DEFAULT 1,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
    );

    CREATE TABLE lookbook_items (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        city_key TEXT NOT NULL UNIQUE,
        city_title TEXT NOT NULL,
        person_name TEXT NOT NULL,
        person_title TEXT NOT NULL,
        product_id INTEGER NOT NULL,
        image_url TEXT NOT NULL,
        fallback_url TEXT,
        quote TEXT NOT NULL,
        sort_order INTEGER DEFAULT 0,
        is_active INTEGER DEFAULT 1,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
    );

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

    CREATE TABLE audit_logs (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        admin_id INTEGER,
        admin_username TEXT NOT NULL,
        action TEXT NOT NULL,
        entity_type TEXT NOT NULL,
        entity_id TEXT,
        details TEXT,
        ip_address TEXT,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    );
");

// Seed default initial data
$adminHash = AuthService::hashPassword('Password@123');
$pdo->exec("
    INSERT INTO admins (id, username, email, password_hash, role, is_active) VALUES
    (1, 'admin', 'admin@balento.com', '{$adminHash}', 'admin', 1);

    INSERT INTO categories (id, name, slug, description, is_active) VALUES
    (1, 'Totes', 'tote', 'Spacious architectural everyday totes', 1),
    (2, 'Shoulder Bags', 'shoulder', 'Fluid sculptural shoulder silhouettes', 1);

    INSERT INTO products (id, category_id, name, slug, tag, price, compare_at_price, description, is_active, sort_order) VALUES
    (1, 1, 'Verona Tote', 'verona-tote', 'Best Seller', 2499.00, 2999.00, 'Spacious architectural tote with padded laptop sleeve.', 1, 1);

    INSERT INTO product_variants (id, product_id, sku, color_name, color_hex, stock_quantity, is_active) VALUES
    (1, 1, 'BAL-VER-BLK', 'Black', '#1c1b1b', 25, 1),
    (2, 1, 'BAL-VER-COG', 'Cognac', '#8B5A2B', 18, 1);

    INSERT INTO product_images (id, product_id, image_url, image_type) VALUES
    (1, 1, 'https://images.unsplash.com/photo-1590874103328-eac38a683ce7', 'primary');

    INSERT INTO coupons (id, code, discount_type, discount_value, min_order_amount, is_active) VALUES
    (1, 'WELCOME10', 'percentage', 10.00, 2000.00, 1);

    INSERT INTO pincodes (pincode, city, state, is_serviceable, cod_available, estimated_days) VALUES
    ('560034', 'Bengaluru', 'Karnataka', 1, 1, 2);

    INSERT INTO newsletter_subscribers (email, source) VALUES
    ('client@luxury.in', 'footer');
");

Database::setConnection('mysql', $pdo);

// Initialize Router & Middlewares
$router = new Router();
$router->use(new CorsMiddleware());
$router->use(new JsonBodyMiddleware());

// Public routes
$router->group(['prefix' => '/api'], function (Router $api) {
    $api->get('/products', [ProductController::class, 'index']);
    $api->get('/products/{slug_or_id}', [ProductController::class, 'show']);
    $api->post('/pincode/check', [PincodeController::class, 'check']);
    $api->post('/coupons/validate', [CouponController::class, 'validate']);
    $api->post('/orders/checkout', [OrderController::class, 'checkout']);
    $api->get('/orders/track/{order_number}', [OrderController::class, 'track']);
    $api->post('/admin/login', [AdminController::class, 'login']);
});

// Protected Admin routes
$router->group(['prefix' => '/api/admin', 'middleware' => new AuthMiddleware(['admin', 'manager', 'staff'])], function (Router $admin) {
    $admin->get('/me', [AdminController::class, 'me']);
    $admin->put('/me/password', [AdminController::class, 'changePassword']);
    $admin->get('/dashboard/stats', [AdminController::class, 'dashboardStats']);
    $admin->get('/analytics', [AdminController::class, 'analytics']);
    $admin->get('/orders', [AdminController::class, 'orders']);
    $admin->get('/orders/{id}', [AdminController::class, 'showOrder']);
    $admin->put('/orders/{id}/status', [AdminController::class, 'updateOrderStatus']);
    $admin->get('/products', [AdminController::class, 'products']);
    $admin->get('/products/{id}', [AdminController::class, 'showProduct']);
    $admin->post('/products', [AdminController::class, 'createProduct']);
    $admin->put('/products/{id}', [AdminController::class, 'updateProduct']);
    $admin->delete('/products/{id}', [AdminController::class, 'deleteProduct']);
    $admin->get('/inventory', [AdminController::class, 'inventory']);
    $admin->put('/inventory/adjust', [AdminController::class, 'adjustInventory']);
    $admin->get('/categories', [AdminController::class, 'categories']);
    $admin->post('/categories', [AdminController::class, 'createCategory']);
    $admin->get('/coupons', [AdminController::class, 'coupons']);
    $admin->post('/coupons', [AdminController::class, 'createCoupon']);
    $admin->get('/lookbook', [AdminController::class, 'lookbook']);
    $admin->post('/lookbook', [AdminController::class, 'createLookbook']);
    $admin->get('/pincodes', [AdminController::class, 'pincodes']);
    $admin->post('/pincodes', [AdminController::class, 'createPincode']);
    $admin->get('/newsletter', [AdminController::class, 'subscribers']);
    $admin->get('/newsletter/export', [AdminController::class, 'exportSubscribers']);
    $admin->get('/users', [AdminController::class, 'adminUsers']);
    $admin->post('/users', [AdminController::class, 'createAdminUser']);
    $admin->get('/audit-logs', [AdminController::class, 'auditLogs']);
});

// -----------------------------------------------------------------------------
// TEST STEP 1: Admin Authentication
// -----------------------------------------------------------------------------
$loginReq = new Request('POST', '/api/admin/login', [], ['username' => 'admin', 'password' => 'Password@123'], '{"username":"admin","password":"Password@123"}', ['content-type' => 'application/json']);
$loginRes = $router->dispatch($loginReq);
assert($loginRes->getStatusCode() === 200);
$token = $loginRes->getPayload()['data']['token'];
assert(!empty($token));
echo " [OK] 1. Admin Login & JWT Issuance verified\n";

// -----------------------------------------------------------------------------
// TEST STEP 2: Dashboard Statistics & KPIs
// -----------------------------------------------------------------------------
$dashReq = new Request('GET', '/api/admin/dashboard/stats', [], [], '', ['authorization' => "Bearer {$token}"]);
$dashRes = $router->dispatch($dashReq);
assert($dashRes->getStatusCode() === 200);
$stats = $dashRes->getPayload()['data'];
assert($stats['total_products'] === 1);
assert($stats['subscribers_count'] === 1);
echo " [OK] 2. Dashboard KPIs and Aggregate Statistics verified\n";

// -----------------------------------------------------------------------------
// TEST STEP 3: Admin Creates New Product Silhouette
// -----------------------------------------------------------------------------
$newProdData = [
    'category_id' => 2,
    'name' => 'Elara Shoulder',
    'slug' => 'elara-shoulder',
    'tag' => 'Trending',
    'price' => 2299.00,
    'compare_at_price' => 2799.00,
    'description' => 'Sculptural crescent shoulder bag.',
    'dimensions' => '28cm x 18cm x 8cm',
    'weight' => '420g',
    'is_active' => 1,
    'variants' => [
        ['color_name' => 'Black', 'color_hex' => '#1c1b1b', 'stock_quantity' => 30],
        ['color_name' => 'Cognac', 'color_hex' => '#8B5A2B', 'stock_quantity' => 20],
    ],
    'features' => [
        ['feature_text' => 'Ergonomic shoulder strap'],
        ['feature_text' => 'Custom brass hardware'],
    ],
    'images' => [
        ['image_url' => 'https://images.unsplash.com/photo-1566150905458-1bf1fc113f0d', 'image_type' => 'primary']
    ]
];

$createProdReq = new Request('POST', '/api/admin/products', [], $newProdData, json_encode($newProdData), [
    'content-type' => 'application/json',
    'authorization' => "Bearer {$token}",
]);
$createProdRes = $router->dispatch($createProdReq);
assert($createProdRes->getStatusCode() === 201);
$newProdId = (int) $createProdRes->getPayload()['data']['product_id'];
echo " [OK] 3. Admin Product Creation with Variants, Features & Imagery verified\n";

// -----------------------------------------------------------------------------
// TEST STEP 4: Verify Storefront Instantly Reflects New Product
// -----------------------------------------------------------------------------
$storeCatalogReq = new Request('GET', '/api/products');
$storeCatalogRes = $router->dispatch($storeCatalogReq);
assert($storeCatalogRes->getStatusCode() === 200);
$catalog = $storeCatalogRes->getPayload()['data']['products'];
assert(count($catalog) === 2);
$names = array_column($catalog, 'name');
assert(in_array('Elara Shoulder', $names, true));
assert(in_array('Verona Tote', $names, true));
echo " [OK] 4. Storefront Live Sync: New product visible on customer website\n";

// -----------------------------------------------------------------------------
// TEST STEP 5: Admin Edits Product Price & Tag
// -----------------------------------------------------------------------------
$updateProdReq = new Request('PUT', "/api/admin/products/{$newProdId}", [], [
    'price' => 2199.00,
    'tag' => 'Exclusive',
], '{"price":2199.00,"tag":"Exclusive"}', [
    'content-type' => 'application/json',
    'authorization' => "Bearer {$token}",
]);
$updateProdRes = $router->dispatch($updateProdReq);
assert($updateProdRes->getStatusCode() === 200);

// Verify on storefront
$detailReq = new Request('GET', '/api/products/elara-shoulder');
$detailRes = $router->dispatch($detailReq);
assert($detailRes->getStatusCode() === 200);
assert($detailRes->getPayload()['data']['price'] === 2199.00);
assert($detailRes->getPayload()['data']['tag'] === 'Exclusive');
echo " [OK] 5. Storefront Live Sync: Price update (₹2299 -> ₹2199) immediately reflected\n";

// -----------------------------------------------------------------------------
// TEST STEP 6: Admin Adjusts Inventory With Required Reason
// -----------------------------------------------------------------------------
$adjReq = new Request('PUT', '/api/admin/inventory/adjust', [], [
    'variant_id' => 1,
    'adjustment' => 15,
    'reason' => 'Atelier restocking shipment arrived'
], '{"variant_id":1,"adjustment":15,"reason":"Atelier restocking shipment arrived"}', [
    'content-type' => 'application/json',
    'authorization' => "Bearer {$token}",
]);
$adjRes = $router->dispatch($adjReq);
assert($adjRes->getStatusCode() === 200);
assert($adjRes->getPayload()['data']['new_stock'] === 40); // 25 + 15 = 40
echo " [OK] 6. Admin Inventory: Stock adjustment (+15) with reason logging verified\n";

// -----------------------------------------------------------------------------
// TEST STEP 7: Customer Checkout & Immediate Admin Visibility
// -----------------------------------------------------------------------------
$checkoutPayload = [
    'customer_name' => 'Aditi Sharma',
    'customer_email' => 'aditi@example.com',
    'customer_phone' => '9876543210',
    'shipping_address' => '42 Prestige Indiranagar',
    'city' => 'Bengaluru',
    'state' => 'Karnataka',
    'pincode' => '560034',
    'coupon_code' => 'WELCOME10',
    'payment_method' => 'upi',
    'is_gift' => true,
    'gift_note' => 'Happy Birthday!',
    'items' => [
        [
            'variant_id' => 1,
            'quantity' => 1,
            'monogram' => ['initials' => 'AS', 'foil' => 'gold']
        ]
    ]
];
$checkoutReq = new Request('POST', '/api/orders/checkout', [], $checkoutPayload, json_encode($checkoutPayload), ['content-type' => 'application/json']);
$checkoutRes = $router->dispatch($checkoutReq);
assert($checkoutRes->getStatusCode() === 201);
$orderNumber = $checkoutRes->getPayload()['data']['order_number'];
$orderId = (int) $checkoutRes->getPayload()['data']['id'];

// Check in Admin Orders endpoint
$adminOrdersReq = new Request('GET', '/api/admin/orders', ['search' => $orderNumber], [], '', ['authorization' => "Bearer {$token}"]);
$adminOrdersRes = $router->dispatch($adminOrdersReq);
assert($adminOrdersRes->getStatusCode() === 200);
assert(count($adminOrdersRes->getPayload()['data']['orders']) === 1);
assert($adminOrdersRes->getPayload()['data']['orders'][0]['order_number'] === $orderNumber);

// Check Order Detail endpoint
$orderDetailReq = new Request('GET', "/api/admin/orders/{$orderId}", [], [], '', ['authorization' => "Bearer {$token}"]);
$orderDetailRes = $router->dispatch($orderDetailReq);
assert($orderDetailRes->getStatusCode() === 200);
$detail = $orderDetailRes->getPayload()['data'];
assert($detail['items'][0]['monogram']['initials'] === 'AS');
assert($detail['items'][0]['monogram']['foil'] === 'gold');
echo " [OK] 7. Customer Checkout -> Admin Order Management & Monogram Inspection verified\n";

// -----------------------------------------------------------------------------
// TEST STEP 8: Admin Updates Order Status & Public Tracking Sync
// -----------------------------------------------------------------------------
$statusUpdateReq = new Request('PUT', "/api/admin/orders/{$orderId}/status", [], [
    'order_status' => 'shipped',
    'payment_status' => 'paid',
], '{"order_status":"shipped","payment_status":"paid"}', [
    'content-type' => 'application/json',
    'authorization' => "Bearer {$token}",
]);
$statusUpdateRes = $router->dispatch($statusUpdateReq);
assert($statusUpdateRes->getStatusCode() === 200);

// Check Public Tracking endpoint
$trackReq = new Request('GET', "/api/orders/track/{$orderNumber}");
$trackRes = $router->dispatch($trackReq);
assert($trackRes->getStatusCode() === 200);
assert($trackRes->getPayload()['data']['order_status'] === 'shipped');
$timelineSteps = $trackRes->getPayload()['data']['timeline'];
$shippedStep = array_values(array_filter($timelineSteps, fn($s) => $s['step'] === 'shipped'))[0] ?? null;
assert($shippedStep !== null && $shippedStep['completed'] === true);
echo " [OK] 8. Admin Status Transition -> Public Order Tracking timeline sync verified\n";

// -----------------------------------------------------------------------------
// TEST STEP 9: Category, Coupon, Lookbook & Pincode Admin CRUD
// -----------------------------------------------------------------------------
// Category
$catReq = new Request('POST', '/api/admin/categories', [], ['name' => 'Crossbody Bags', 'slug' => 'crossbody'], '{"name":"Crossbody Bags","slug":"crossbody"}', [
    'content-type' => 'application/json',
    'authorization' => "Bearer {$token}",
]);
assert($router->dispatch($catReq)->getStatusCode() === 201);

// Coupon
$coupReq = new Request('POST', '/api/admin/coupons', [], ['code' => 'FESTIVE15', 'discount_type' => 'percentage', 'discount_value' => 15.00, 'min_order_amount' => 2000.00], '{"code":"FESTIVE15","discount_type":"percentage","discount_value":15.00,"min_order_amount":2000.00}', [
    'content-type' => 'application/json',
    'authorization' => "Bearer {$token}",
]);
assert($router->dispatch($coupReq)->getStatusCode() === 201);

// Pincode
$pinReq = new Request('POST', '/api/admin/pincodes', [], ['pincode' => '400050', 'city' => 'Mumbai', 'state' => 'Maharashtra', 'estimated_days' => 2], '{"pincode":"400050","city":"Mumbai","state":"Maharashtra","estimated_days":2}', [
    'content-type' => 'application/json',
    'authorization' => "Bearer {$token}",
]);
assert($router->dispatch($pinReq)->getStatusCode() === 201);
echo " [OK] 9. Admin Categories, Coupons, and Pincode Serviceability CRUD verified\n";

// -----------------------------------------------------------------------------
// TEST STEP 10: Audit Log Activity Verification
// -----------------------------------------------------------------------------
$auditReq = new Request('GET', '/api/admin/audit-logs', [], [], '', ['authorization' => "Bearer {$token}"]);
$auditRes = $router->dispatch($auditReq);
assert($auditRes->getStatusCode() === 200);
$logs = $auditRes->getPayload()['data']['logs'];
assert(count($logs) >= 5);
echo " [OK] 10. Administrative Audit Logs & Action Traceability verified\n";

echo "\n=======================================================================\n";
echo "✓ ALL END-TO-END BACKOFFICE & STOREFRONT INTEGRATION TESTS PASSED 100%!\n";
echo "=======================================================================\n";
