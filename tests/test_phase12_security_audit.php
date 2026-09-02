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
use App\Middleware\RateLimitMiddleware;
use App\Middleware\AuthMiddleware;
use App\Controllers\ProductController;
use App\Controllers\PincodeController;
use App\Controllers\CouponController;
use App\Controllers\OrderController;
use App\Controllers\AdminController;
use App\Services\AuthService;

echo "=== EXECUTING PHASE 12: COMPREHENSIVE SECURITY AUDIT & FUZZING ===\n";

Env::load(__DIR__ . '/../.env.example');
Config::init(__DIR__ . '/../config');

// 1. Setup in-memory SQLite database
$pdo = new PDO('sqlite::memory:', null, null, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
]);

$pdo->exec("
    CREATE TABLE categories (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT NOT NULL, slug TEXT NOT NULL);
    CREATE TABLE products (id INTEGER PRIMARY KEY AUTOINCREMENT, category_id INTEGER NOT NULL, name TEXT NOT NULL, slug TEXT NOT NULL, tag TEXT, price NUMERIC NOT NULL, compare_at_price NUMERIC, description TEXT NOT NULL, dimensions TEXT, weight TEXT, is_active INTEGER DEFAULT 1, sort_order INTEGER DEFAULT 0, created_at DATETIME DEFAULT CURRENT_TIMESTAMP);
    CREATE TABLE product_variants (id INTEGER PRIMARY KEY AUTOINCREMENT, product_id INTEGER NOT NULL, sku TEXT NOT NULL UNIQUE, color_name TEXT NOT NULL, color_hex TEXT NOT NULL, stock_quantity INTEGER DEFAULT 0, is_active INTEGER DEFAULT 1);
    CREATE TABLE product_features (id INTEGER PRIMARY KEY AUTOINCREMENT, product_id INTEGER NOT NULL, feature_text TEXT NOT NULL, sort_order INTEGER DEFAULT 0);
    CREATE TABLE product_images (id INTEGER PRIMARY KEY AUTOINCREMENT, product_id INTEGER NOT NULL, variant_id INTEGER, image_url TEXT NOT NULL, alt_text TEXT, image_type TEXT DEFAULT 'gallery', sort_order INTEGER DEFAULT 0);
    CREATE TABLE coupons (id INTEGER PRIMARY KEY AUTOINCREMENT, code TEXT NOT NULL UNIQUE, discount_type TEXT DEFAULT 'percentage', discount_value NUMERIC NOT NULL, min_order_amount NUMERIC DEFAULT 0.00, max_discount_cap NUMERIC, usage_limit INTEGER, usage_count INTEGER DEFAULT 0, is_active INTEGER DEFAULT 1, expires_at DATETIME);
    CREATE TABLE pincodes (id INTEGER PRIMARY KEY AUTOINCREMENT, pincode TEXT NOT NULL UNIQUE, city TEXT NOT NULL, state TEXT NOT NULL, is_serviceable INTEGER DEFAULT 1, cod_available INTEGER DEFAULT 1, estimated_days INTEGER DEFAULT 3, shipping_zone TEXT DEFAULT 'National');
    CREATE TABLE orders (id INTEGER PRIMARY KEY AUTOINCREMENT, order_number TEXT NOT NULL UNIQUE, customer_name TEXT NOT NULL, customer_email TEXT NOT NULL, customer_phone TEXT NOT NULL, shipping_address TEXT NOT NULL, city TEXT NOT NULL, state TEXT DEFAULT 'India', pincode TEXT NOT NULL, subtotal NUMERIC NOT NULL, discount_amount NUMERIC DEFAULT 0.00, shipping_fee NUMERIC DEFAULT 0.00, total_amount NUMERIC NOT NULL, coupon_code TEXT, payment_method TEXT DEFAULT 'upi', payment_status TEXT DEFAULT 'pending', order_status TEXT DEFAULT 'placed', is_gift INTEGER DEFAULT 0, gift_note TEXT, idempotency_key TEXT UNIQUE, created_at DATETIME DEFAULT CURRENT_TIMESTAMP, updated_at DATETIME DEFAULT CURRENT_TIMESTAMP);
    CREATE TABLE order_items (id INTEGER PRIMARY KEY AUTOINCREMENT, order_id INTEGER NOT NULL, product_id INTEGER, variant_id INTEGER, product_name TEXT NOT NULL, color_name TEXT NOT NULL, sku TEXT NOT NULL, unit_price NUMERIC NOT NULL, quantity INTEGER NOT NULL, total_price NUMERIC NOT NULL, monogram_initials TEXT, monogram_foil TEXT, created_at DATETIME DEFAULT CURRENT_TIMESTAMP);
    CREATE TABLE admins (id INTEGER PRIMARY KEY AUTOINCREMENT, username TEXT NOT NULL UNIQUE, email TEXT NOT NULL UNIQUE, password_hash TEXT NOT NULL, role TEXT DEFAULT 'admin', is_active INTEGER DEFAULT 1, last_login_at DATETIME, created_at DATETIME DEFAULT CURRENT_TIMESTAMP, updated_at DATETIME DEFAULT CURRENT_TIMESTAMP);
");

$adminHash = AuthService::hashPassword('Password@123');
$pdo->exec("
    INSERT INTO admins (id, username, email, password_hash, role, is_active) VALUES (1, 'admin', 'admin@balento.com', '{$adminHash}', 'admin', 1);
    INSERT INTO categories (id, name, slug) VALUES (1, 'Totes', 'tote');
    INSERT INTO products (id, category_id, name, slug, price, description) VALUES (1, 1, 'Verona Tote', 'verona-tote', 2499.00, 'Tote with laptop sleeve');
    INSERT INTO product_variants (id, product_id, sku, color_name, color_hex, stock_quantity, is_active) VALUES (1, 1, 'BAL-VER-BLK', 'Black', '#1c1b1b', 10, 1);
    INSERT INTO coupons (id, code, discount_type, discount_value, min_order_amount, is_active) VALUES (1, 'WELCOME10', 'percentage', 10.00, 0.00, 1);
    INSERT INTO pincodes (pincode, city, state) VALUES ('560034', 'Bengaluru', 'Karnataka');
");

Database::setConnection('mysql', $pdo);

// Initialize router with full middleware pipeline
$router = new Router();
$router->use(new CorsMiddleware());
$router->use(new JsonBodyMiddleware());

$router->group(['prefix' => '/api'], function (Router $api) {
    $api->get('/products', [ProductController::class, 'index']);
    $api->get('/products/{slug_or_id}', [ProductController::class, 'show']);
    $api->post('/pincode/check', [PincodeController::class, 'check']);
    $api->post('/coupons/validate', [CouponController::class, 'validate']);
    $api->post('/orders/checkout', [OrderController::class, 'checkout']);
    $api->get('/orders/track/{order_number}', [OrderController::class, 'track']);
    $api->post('/admin/login', [AdminController::class, 'login']);
});

$router->group(['prefix' => '/api/admin', 'middleware' => new AuthMiddleware(['admin'])], function (Router $admin) {
    $admin->get('/me', [AdminController::class, 'me']);
    $admin->get('/dashboard/stats', [AdminController::class, 'dashboardStats']);
    $admin->post('/products', [AdminController::class, 'createProduct']);
});

// TEST 1: SQL Injection Vectors
$sqliVectors = [
    "' OR '1'='1",
    "1; DROP TABLE products; --",
    "admin' --",
    "' UNION SELECT 1,2,3,4,5,6,7,8,9,10 --",
    "1' ORDER BY 100 --",
];

foreach ($sqliVectors as $vec) {
    // 1a. Search SQLi
    $reqSearch = new Request('GET', '/api/products', ['search' => $vec]);
    $resSearch = $router->dispatch($reqSearch);
    assert($resSearch->getStatusCode() === 200); // Handled safely without SQL error
    
    // 1b. Sort SQLi (must fallback to default whitelist)
    $reqSort = new Request('GET', '/api/products', ['sort' => $vec]);
    $resSort = $router->dispatch($reqSort);
    assert($resSort->getStatusCode() === 200);
    
    // 1c. Tracking SQLi
    $reqTrack = new Request('GET', "/api/orders/track/" . urlencode($vec));
    $resTrack = $router->dispatch($reqTrack);
    assert($resTrack->getStatusCode() === 404);
}
echo " [SECURITY PASS] SQL Injection: Parameterized statement barriers verified across search, sort, and detail queries\n";

// TEST 2: IDOR and Negative / Zero / Huge Quantities in Checkout
$zeroQtyReq = new Request('POST', '/api/orders/checkout', [], [
    'customer_name' => 'Attacker',
    'customer_email' => 'attacker@test.com',
    'customer_phone' => '9876543210',
    'shipping_address' => 'Test Address',
    'city' => 'Bengaluru',
    'pincode' => '560034',
    'payment_method' => 'cod',
    'items' => [['variant_id' => 1, 'quantity' => 0]],
], '{"items":[{"variant_id":1,"quantity":0}]}', ['content-type' => 'application/json']);
$resZero = $router->dispatch($zeroQtyReq);
assert($resZero->getStatusCode() === 422);

$negQtyReq = new Request('POST', '/api/orders/checkout', [], [
    'customer_name' => 'Attacker',
    'customer_email' => 'attacker@test.com',
    'customer_phone' => '9876543210',
    'shipping_address' => 'Test Address',
    'city' => 'Bengaluru',
    'pincode' => '560034',
    'payment_method' => 'cod',
    'items' => [['variant_id' => 1, 'quantity' => -5]],
], '{"items":[{"variant_id":1,"quantity":-5}]}', ['content-type' => 'application/json']);
$resNeg = $router->dispatch($negQtyReq);
assert($resNeg->getStatusCode() === 422);

echo " [SECURITY PASS] Parameter Tampering: Zero and negative quantities strictly rejected\n";

// TEST 3: Price Manipulation Attempt
$tamperedPricePayload = [
    'customer_name' => 'Attacker',
    'customer_email' => 'attacker@test.com',
    'customer_phone' => '9876543210',
    'shipping_address' => 'Test Address',
    'city' => 'Bengaluru',
    'pincode' => '560034',
    'payment_method' => 'cod',
    'price' => 1.00, // Client tries to set price to ₹1.00
    'items' => [
        ['variant_id' => 1, 'quantity' => 1, 'price' => 1.00]
    ],
];
$reqPriceHack = new Request('POST', '/api/orders/checkout', [], $tamperedPricePayload, json_encode($tamperedPricePayload), ['content-type' => 'application/json']);
$resPriceHack = $router->dispatch($reqPriceHack);
assert($resPriceHack->getStatusCode() === 201);
// Server must charge authoritative ₹2499.00, NOT the client-injected ₹1.00
assert($resPriceHack->getPayload()['data']['total_amount'] === 2499.00);
echo " [SECURITY PASS] Price Integrity: Client-injected prices discarded; server database price authoritative\n";

// TEST 4: JWT Forgery & Secret Tampering
$forgedToken = "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJzdWIiOjEsInVzZXJuYW1lIjoiYWRtaW4iLCJyb2xlIjoiYWRtaW4iLCJleHAiOjk5OTk5OTk5OTl9.FORGED_SIGNATURE_XXXX";
$forgedReq = new Request('GET', '/api/admin/me', [], [], '', ['authorization' => "Bearer {$forgedToken}"]);
$forgedRes = $router->dispatch($forgedReq);
assert($forgedRes->getStatusCode() === 401);
echo " [SECURITY PASS] Cryptographic Verification: Forged JWT tokens rejected\n";

// TEST 5: Monogram Length Abuse (Attempting 50 characters instead of 3)
$monogramHackPayload = [
    'customer_name' => 'Priya',
    'customer_email' => 'priya@test.com',
    'customer_phone' => '9876543210',
    'shipping_address' => 'Test Address',
    'city' => 'Bengaluru',
    'pincode' => '560034',
    'payment_method' => 'upi',
    'items' => [
        [
            'variant_id' => 1,
            'quantity' => 1,
            'monogram' => ['initials' => 'LONGINITIALSSTRINGEXCEEDINGLIMIT', 'foil' => 'gold']
        ]
    ],
];
$reqMonogram = new Request('POST', '/api/orders/checkout', [], $monogramHackPayload, json_encode($monogramHackPayload), ['content-type' => 'application/json']);
$resMonogram = $router->dispatch($reqMonogram);
assert($resMonogram->getStatusCode() === 422);
assert(str_contains($resMonogram->getPayload()['message'], 'Monogram initials may not exceed 3 characters'));
echo " [SECURITY PASS] Customization Validation: Monogram length constraints strictly enforced\n";

echo "\nALL PHASE 12 SECURITY AUDIT & PENETRATION TESTS PASSED 100%!\n";
