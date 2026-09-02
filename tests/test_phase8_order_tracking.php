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

echo "=== TESTING PHASE 8: ORDER TRACKING SYSTEM ===\n";

Env::load(__DIR__ . '/../.env.example');
Config::init(__DIR__ . '/../config');

// 1. Setup SQLite in-memory test database
$pdo = new PDO('sqlite::memory:', null, null, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
]);

$pdo->exec("
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
        payment_status TEXT DEFAULT 'paid',
        order_status TEXT DEFAULT 'processing',
        is_gift INTEGER DEFAULT 0,
        gift_note TEXT,
        idempotency_key TEXT,
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

// Insert sample order
$pdo->exec("
    INSERT INTO orders (
        id, order_number, customer_name, customer_email, customer_phone,
        shipping_address, city, state, pincode, subtotal, discount_amount,
        shipping_fee, total_amount, payment_method, payment_status, order_status, is_gift
    ) VALUES (
        1, 'BAL-2026-9281', 'Priya Mehta', 'priya.mehta@example.com', '9876543210',
        'Embassy Residency, Koramangala', 'Bengaluru', 'Karnataka', '560034',
        2499.00, 249.90, 0.00, 2249.10, 'upi', 'paid', 'processing', 1
    );

    INSERT INTO order_items (
        order_id, product_name, color_name, sku, unit_price, quantity, total_price, monogram_initials, monogram_foil
    ) VALUES (
        1, 'Verona Tote', 'Cognac', 'BAL-VER-COG', 2499.00, 1, 2499.00, 'PM', 'gold'
    );
");

Database::setConnection('mysql', $pdo);

$router = new Router();
$router->get('/api/orders/track/{order_number}', [OrderController::class, 'track']);

// 2. Test Existing Order Tracking
$reqTrack = new Request('GET', '/api/orders/track/BAL-2026-9281');
$resTrack = $router->dispatch($reqTrack);

assert($resTrack->getStatusCode() === 200);
$trackData = $resTrack->getPayload()['data'];

assert($trackData['order_number'] === 'BAL-2026-9281');
assert($trackData['order_status'] === 'processing');
assert($trackData['total_amount'] === 2249.10);
assert($trackData['is_gift'] === true);

// Verify privacy masking
assert($trackData['customer_email_masked'] === 'p*********a@example.com');
assert($trackData['customer_phone_masked'] === '+91 ******3210');
assert(str_contains($trackData['delivery_destination'], 'Bengaluru, Karnataka - 560034'));

// Verify items & monogram
assert(count($trackData['items']) === 1);
assert($trackData['items'][0]['product_name'] === 'Verona Tote');
assert($trackData['items'][0]['monogram']['initials'] === 'PM');
assert($trackData['items'][0]['monogram']['foil'] === 'gold');

// Verify timeline steps
assert(count($trackData['timeline']) === 4);
assert($trackData['timeline'][0]['completed'] === true); // placed is completed
assert($trackData['timeline'][1]['completed'] === true); // processing is completed
assert($trackData['timeline'][1]['current'] === true);   // processing is current
assert($trackData['timeline'][2]['completed'] === false); // shipped is false

echo " [OK] Order Tracking: Full timeline, historical items, monetary breakdown, and privacy data masking verified\n";

// 3. Test Non-Existent Order Tracking
$reqNonExistent = new Request('GET', '/api/orders/track/BAL-9999-0000');
$resNonExistent = $router->dispatch($reqNonExistent);

assert($resNonExistent->getStatusCode() === 404);
assert($resNonExistent->getPayload()['success'] === false);
echo " [OK] Order Tracking: 404 Not Found error handling verified\n";

echo "\nALL PHASE 8 ORDER TRACKING TESTS PASSED PERFECTLY!\n";
