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
use App\Controllers\NewsletterController;
use App\Controllers\LookbookController;

echo "=== TESTING PHASE 9: NEWSLETTER & LOOKBOOK APIS ===\n";

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
");

// Insert seed data
$pdo->exec("
    INSERT INTO categories (id, name, slug) VALUES (1, 'Totes', 'tote');
    INSERT INTO products (id, category_id, name, slug, price) VALUES (1, 1, 'Verona Tote', 'verona-tote', 2499.00);

    INSERT INTO lookbook_items (id, city_key, city_title, person_name, person_title, product_id, image_url, quote, sort_order, is_active) VALUES
    (1, 'bengaluru', 'Bengaluru • Koramangala', 'Sneha Reddy', 'Founder & Designer', 1, 'https://images.unsplash.com/lookbook-blr', 'The Verona fits my laptop beautifully.', 1, 1),
    (2, 'inactive-city', 'Inactive • City', 'Test Person', 'Tester', 1, 'https://images.unsplash.com/inactive', 'Quote', 2, 0);
");

Database::setConnection('mysql', $pdo);

$router = new Router();
$router->post('/api/newsletter/subscribe', [NewsletterController::class, 'subscribe']);
$router->get('/api/lookbook', [LookbookController::class, 'index']);

// 2. Test Newsletter Subscription
$reqSub = new Request('POST', '/api/newsletter/subscribe', [], ['email' => 'Sneha.Reddy@Example.COM'], '{"email":"Sneha.Reddy@Example.COM"}', ['content-type' => 'application/json']);
$resSub = $router->dispatch($reqSub);

assert($resSub->getStatusCode() === 200);
assert(str_contains($resSub->getPayload()['message'], 'Welcome to the Balento Inner Circle'));

// Verify database normalization (lowercase email)
$savedEmail = $pdo->query("SELECT email FROM newsletter_subscribers WHERE id = 1")->fetchColumn();
assert($savedEmail === 'sneha.reddy@example.com');

// Test Duplicate Email (Idempotent subscription)
$resSubDup = $router->dispatch($reqSub);
assert($resSubDup->getStatusCode() === 200);
$count = (int) $pdo->query("SELECT COUNT(*) FROM newsletter_subscribers")->fetchColumn();
assert($count === 1); // No duplicate rows created

// Test Invalid Email Rejection
$reqSubBad = new Request('POST', '/api/newsletter/subscribe', [], ['email' => 'invalid-email-format'], '{"email":"invalid-email-format"}', ['content-type' => 'application/json']);
$resSubBad = $router->dispatch($reqSubBad);
assert($resSubBad->getStatusCode() === 422);
assert($resSubBad->getPayload()['success'] === false);

echo " [OK] Newsletter: Email normalization, deduplication, and invalid format rejection verified\n";

// 3. Test Lookbook API
$reqLookbook = new Request('GET', '/api/lookbook');
$resLookbook = $router->dispatch($reqLookbook);

assert($resLookbook->getStatusCode() === 200);
$lookbookData = $resLookbook->getPayload()['data'];

// Only active item should be returned (1 out of 2)
assert(count($lookbookData) === 1);
assert($lookbookData[0]['city_key'] === 'bengaluru');
assert($lookbookData[0]['bag_id'] === 'verona-tote');
assert($lookbookData[0]['product']['name'] === 'Verona Tote');
assert($lookbookData[0]['product']['price'] === 2499.00);

echo " [OK] Lookbook API: Curated street style items and active product tag association verified\n";

echo "\nALL PHASE 9 NEWSLETTER & LOOKBOOK TESTS PASSED PERFECTLY!\n";
