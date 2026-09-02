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
use App\Controllers\OrderController;

echo "=== TESTING PHASE 7: ATOMIC CHECKOUT & CONCURRENCY SYSTEM ===\n";

Env::load(__DIR__ . '/../.env.example');
Config::init(__DIR__ . '/../config');

// 1. Setup SQLite in-memory test database
$pdo = new PDO('sqlite::memory:', null, null, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
]);

$pdo->exec("
    CREATE TABLE categories (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        name TEXT NOT NULL,
        slug TEXT NOT NULL
    );

    CREATE TABLE products (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        category_id INTEGER NOT NULL,
        name TEXT NOT NULL,
        slug TEXT NOT NULL,
        price NUMERIC NOT NULL,
        is_active INTEGER DEFAULT 1
    );

    CREATE TABLE product_variants (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        product_id INTEGER NOT NULL,
        sku TEXT NOT NULL UNIQUE,
        color_name TEXT NOT NULL,
        color_hex TEXT NOT NULL,
        stock_quantity INTEGER DEFAULT 0,
        is_active INTEGER DEFAULT 1
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
        expires_at DATETIME
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
");

// Insert seed catalog
$pdo->exec("
    INSERT INTO categories (id, name, slug) VALUES (1, 'Totes', 'tote');
    INSERT INTO products (id, category_id, name, slug, price) VALUES (1, 1, 'Verona Tote', 'verona-tote', 2499.00);
    
    INSERT INTO product_variants (id, product_id, sku, color_name, color_hex, stock_quantity, is_active) VALUES 
    (1, 1, 'BAL-VER-BLK', 'Black', '#1c1b1b', 10, 1),
    (2, 1, 'BAL-VER-COG', 'Cognac', '#8B5A2B', 1, 1);

    INSERT INTO coupons (id, code, discount_type, discount_value, min_order_amount, usage_count, is_active) VALUES
    (1, 'WELCOME10', 'percentage', 10.00, 0.00, 0, 1);
");

Database::setConnection('mysql', $pdo);

$router = new Router();
$router->post('/api/orders/checkout', [OrderController::class, 'checkout']);

// 2. Test Successful Checkout
$checkoutPayload = [
    'customer_name' => 'Priya Mehta',
    'customer_email' => 'priya.mehta@example.com',
    'customer_phone' => '+91 98765 43210',
    'shipping_address' => 'Flat 402, Embassy Residency, Koramangala',
    'city' => 'Bengaluru',
    'state' => 'Karnataka',
    'pincode' => '560034',
    'payment_method' => 'upi',
    'is_gift' => true,
    'gift_note' => 'Please wrap beautifully with luxury seal.',
    'coupon_code' => 'WELCOME10',
    'items' => [
        [
            'variant_id' => 1,
            'quantity' => 2,
            'monogram' => [
                'initials' => 'PM',
                'foil' => 'gold',
            ],
        ],
    ],
];

$req1 = new Request('POST', '/api/orders/checkout', [], $checkoutPayload, json_encode($checkoutPayload), [
    'content-type' => 'application/json',
    'x-idempotency-key' => 'idemp_order_test_001',
]);

$res1 = $router->dispatch($req1);
assert($res1->getStatusCode() === 201);
$orderData1 = $res1->getPayload()['data'];

// Subtotal = 2 * 2499 = 4998, 10% coupon = 499.80, Shipping = 0 (>= 2000), Total = 4498.20
assert($orderData1['subtotal'] === 4998.00);
assert($orderData1['discount_amount'] === 499.80);
assert($orderData1['shipping_fee'] === 0.00);
assert($orderData1['total_amount'] === 4498.20);
assert(preg_match('/^BAL-2026-[A-Z0-9]{6}$/', $orderData1['order_number']) === 1);

// Verify inventory stock decremented from 10 to 8
$stockRemaining = (int) $pdo->query("SELECT stock_quantity FROM product_variants WHERE id = 1")->fetchColumn();
assert($stockRemaining === 8);

// Verify coupon usage count incremented to 1
$couponUsage = (int) $pdo->query("SELECT usage_count FROM coupons WHERE id = 1")->fetchColumn();
assert($couponUsage === 1);

echo " [OK] Checkout: Successful order placement, price calculation, stock decrement & coupon usage verified\n";

// 3. Test Idempotency: Repeating identical request returns original order without duplicate stock decrement
$resIdempotent = $router->dispatch($req1);
assert($resIdempotent->getStatusCode() === 200);
assert($resIdempotent->getPayload()['data']['order_number'] === $orderData1['order_number']);

$stockAfterReplay = (int) $pdo->query("SELECT stock_quantity FROM product_variants WHERE id = 1")->fetchColumn();
assert($stockAfterReplay === 8); // Still 8, not decremented again
echo " [OK] Checkout: Idempotency safety mechanism verified\n";

// 4. Test Insufficient Stock & Atomic Rollback
$oversellPayload = [
    'customer_name' => 'Rahul Sharma',
    'customer_email' => 'rahul@example.com',
    'customer_phone' => '9876543211',
    'shipping_address' => 'Bandra West',
    'city' => 'Mumbai',
    'pincode' => '400050',
    'payment_method' => 'cod',
    'items' => [
        [
            'variant_id' => 2, // Stock is 1
            'quantity' => 5,   // Requesting 5 -> must fail
        ],
    ],
];

$reqOversell = new Request('POST', '/api/orders/checkout', [], $oversellPayload, json_encode($oversellPayload), [
    'content-type' => 'application/json',
]);

$resOversell = $router->dispatch($reqOversell);
assert($resOversell->getStatusCode() === 422);
assert(str_contains($resOversell->getPayload()['message'], 'Insufficient stock'));

// Verify stock of variant 2 remains untouched at 1
$stockVariant2 = (int) $pdo->query("SELECT stock_quantity FROM product_variants WHERE id = 2")->fetchColumn();
assert($stockVariant2 === 1);

// Verify only 1 order exists in database (the first successful one)
$totalOrders = (int) $pdo->query("SELECT COUNT(*) FROM orders")->fetchColumn();
assert($totalOrders === 1);
echo " [OK] Checkout: Insufficient stock detection and atomic transaction rollback verified\n";

echo "\nALL PHASE 7 CHECKOUT & CONCURRENCY TESTS PASSED PERFECTLY!\n";
