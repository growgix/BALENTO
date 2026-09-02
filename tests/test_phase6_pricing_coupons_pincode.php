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
use App\Validation\Validator;
use App\Controllers\PincodeController;
use App\Controllers\CouponController;
use App\Services\PricingService;

echo "=== TESTING PHASE 6: PINCODE, PRICING & COUPON SERVICES ===\n";

Env::load(__DIR__ . '/../.env.example');
Config::init(__DIR__ . '/../config');

// 1. Setup SQLite in-memory test database with coupons and pincodes
$pdo = new PDO('sqlite::memory:', null, null, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
]);

$pdo->exec("
    CREATE TABLE pincodes (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        pincode TEXT NOT NULL UNIQUE,
        city TEXT NOT NULL,
        state TEXT NOT NULL,
        is_serviceable INTEGER DEFAULT 1,
        cod_available INTEGER DEFAULT 1,
        estimated_days INTEGER DEFAULT 3,
        shipping_zone TEXT DEFAULT 'National',
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
");

// Insert seed data
$pdo->exec("
    INSERT INTO pincodes (pincode, city, state, is_serviceable, cod_available, estimated_days, shipping_zone) VALUES
    ('560034', 'Bengaluru', 'Karnataka', 1, 1, 2, 'South Metro'),
    ('400050', 'Mumbai', 'Maharashtra', 1, 1, 2, 'West Metro'),
    ('110003', 'New Delhi', 'Delhi', 1, 1, 2, 'North Metro');

    INSERT INTO coupons (code, discount_type, discount_value, min_order_amount, max_discount_cap, usage_limit, usage_count, is_active) VALUES
    ('WELCOME10', 'percentage', 10.00, 0.00, 1000.00, NULL, 0, 1),
    ('BALENTO', 'percentage', 10.00, 0.00, 1000.00, NULL, 0, 1),
    ('PRIVILEGE500', 'fixed', 500.00, 2200.00, NULL, 100, 0, 1),
    ('EXPIRED20', 'percentage', 20.00, 0.00, NULL, NULL, 0, 0);
");

Database::setConnection('mysql', $pdo);

// 2. Test Validator
$valGood = Validator::make([
    'email' => 'priya.mehta@example.com',
    'phone' => '+91 98765 43210',
    'pincode' => '560034',
])->required('email')->email('email')
  ->required('phone')->phone('phone')
  ->required('pincode')->pincode('pincode');
assert($valGood->passes());

$valBad = Validator::make([
    'email' => 'invalid-email',
    'phone' => '12345',
    'pincode' => '056003', // Leading zero invalid in Indian PIN
])->email('email')->phone('phone')->pincode('pincode');
assert($valBad->fails());
assert(isset($valBad->getErrors()['email']));
assert(isset($valBad->getErrors()['phone']));
assert(isset($valBad->getErrors()['pincode']));
echo " [OK] Validator: Email, Indian Phone, and 6-digit Pincode rules verified\n";

// 3. Test Pricing Engine Free Shipping Thresholds & Calculations
$pricingEngine = new PricingService();

// Cart >= ₹2,000 -> Free Shipping
$p1 = $pricingEngine->calculatePricing(2499.00);
assert($p1['shipping_fee'] === 0.00);
assert($p1['is_free_shipping'] === true);
assert($p1['total_amount'] === 2499.00);

// Cart < ₹2,000 -> ₹150 Flat Shipping
$p2 = $pricingEngine->calculatePricing(1500.00);
assert($p2['shipping_fee'] === 150.00);
assert($p2['is_free_shipping'] === false);
assert($p2['total_amount'] === 1650.00);

// Cart ₹2,499 with 10% coupon WELCOME10 -> ₹249.90 discount, Free shipping, Total = ₹2,249.10
$p3 = $pricingEngine->calculatePricing(2499.00, 'WELCOME10');
assert($p3['discount_amount'] === 249.90);
assert($p3['shipping_fee'] === 0.00);
assert($p3['total_amount'] === 2249.10);
assert($p3['coupon']['valid'] === true);

// Cart ₹2,499 with ₹500 flat discount PRIVILEGE500 -> Discount = ₹500, Total = ₹1,999.00
$p4 = $pricingEngine->calculatePricing(2499.00, 'PRIVILEGE500');
assert($p4['discount_amount'] === 500.00);
assert($p4['total_amount'] === 1999.00);

// Cart ₹1,800 with PRIVILEGE500 (requires ₹2,200 min) -> Coupon invalid for min amount
$p5 = $pricingEngine->calculatePricing(1800.00, 'PRIVILEGE500');
assert($p5['discount_amount'] === 0.00);
assert($p5['coupon']['valid'] === false);
echo " [OK] Pricing Engine: ₹2,000 Free shipping threshold, ₹150 fee, percentage & fixed discounts verified\n";

// 4. Test Pincode API (POST /api/pincode/check)
$router = new Router();
$router->post('/api/pincode/check', [PincodeController::class, 'check']);
$router->post('/api/coupons/validate', [CouponController::class, 'validate']);

// Seeded PIN 560034
$reqPin1 = new Request('POST', '/api/pincode/check', [], ['pincode' => '560034'], '{"pincode":"560034"}', ['content-type' => 'application/json']);
$resPin1 = $router->dispatch($reqPin1);
assert($resPin1->getStatusCode() === 200);
$pinData1 = $resPin1->getPayload()['data'];
assert($pinData1['city'] === 'Bengaluru');
assert($pinData1['estimated_days'] === 2);
assert($pinData1['cod_available'] === true);

// Unseeded valid PIN 682001 (Kochi)
$reqPin2 = new Request('POST', '/api/pincode/check', [], ['pincode' => '682001'], '{"pincode":"682001"}', ['content-type' => 'application/json']);
$resPin2 = $router->dispatch($reqPin2);
assert($resPin2->getStatusCode() === 200);
assert($resPin2->getPayload()['data']['serviceable'] === true);
assert($resPin2->getPayload()['data']['estimated_days'] === 4);

// Malformed PIN
$reqPinBad = new Request('POST', '/api/pincode/check', [], ['pincode' => 'ABC560'], '{"pincode":"ABC560"}', ['content-type' => 'application/json']);
$resPinBad = $router->dispatch($reqPinBad);
assert($resPinBad->getStatusCode() === 422);
echo " [OK] Pincode API: Priority metro lookup, fallback national estimate, and 422 validation verified\n";

// 5. Test Coupon API (POST /api/coupons/validate)
// Valid WELCOME10
$reqCoup1 = new Request('POST', '/api/coupons/validate', [], ['code' => 'WELCOME10', 'subtotal' => 2499.00], '{"code":"WELCOME10","subtotal":2499}', ['content-type' => 'application/json']);
$resCoup1 = $router->dispatch($reqCoup1);
assert($resCoup1->getStatusCode() === 200);
assert($resCoup1->getPayload()['data']['pricing']['discount_amount'] === 249.90);

// Expired/Inactive coupon EXPIRED20
$reqCoup2 = new Request('POST', '/api/coupons/validate', [], ['code' => 'EXPIRED20', 'subtotal' => 2499.00], '{"code":"EXPIRED20","subtotal":2499}', ['content-type' => 'application/json']);
$resCoup2 = $router->dispatch($reqCoup2);
assert($resCoup2->getStatusCode() === 422);
assert($resCoup2->getPayload()['success'] === false);

echo " [OK] Coupon API: Valid coupon discount calculations and inactive coupon rejection verified\n";

echo "\nALL PHASE 6 TESTS PASSED PERFECTLY!\n";
