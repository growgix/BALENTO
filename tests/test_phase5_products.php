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
use App\Controllers\ProductController;

echo "=== TESTING PHASE 5: PRODUCT APIS ===\n";

Env::load(__DIR__ . '/../.env.example');
Config::init(__DIR__ . '/../config');

// 1. Setup SQLite in-memory test database with schema and seed data
$pdo = new PDO('sqlite::memory:', null, null, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
]);

// Build SQLite test schema
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

    CREATE TABLE product_features (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        product_id INTEGER NOT NULL,
        feature_text TEXT NOT NULL,
        sort_order INTEGER DEFAULT 0,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
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
");

// Insert seed data
$pdo->exec("
    INSERT INTO categories (id, name, slug, description, sort_order) VALUES
    (1, 'Totes', 'tote', 'Spacious architectural totes', 1),
    (2, 'Shoulder Bags', 'shoulder', 'Sculptural crescent shoulder silhouettes', 2),
    (3, 'Crossbody Bags', 'crossbody', 'Hands-free compact daily essentials', 3),
    (4, 'Hobo Bags', 'hobo', 'Relaxed slouch silhouettes', 4),
    (5, 'Structured Bags', 'structured', 'Architectural top-handle bags', 5);

    INSERT INTO products (id, category_id, name, slug, tag, price, description, dimensions, weight, is_active, sort_order) VALUES
    (1, 1, 'Verona Tote', 'verona-tote', 'Best Seller', 2499.00, 'Spacious architectural tote with laptop sleeve', '38x30x14cm', '680g', 1, 1),
    (2, 2, 'Elara Shoulder', 'elara-shoulder', 'Trending', 2299.00, 'Sculptural crescent shoulder bag', '28x18x8cm', '420g', 1, 2),
    (3, 3, 'Cora Crossbody', 'cora-crossbody', 'Essential', 2099.00, 'Clean hands-free daily essential', '22x16x6cm', '360g', 1, 3),
    (4, 4, 'Alba Hobo', 'alba-hobo', 'Editor''s Pick', 2399.00, 'Relaxed slouch silhouette in nappa leather', '34x26x12cm', '510g', 1, 4),
    (5, 5, 'Mira Structured', 'mira-structured', 'New', 2499.00, 'Architectural top-handle bag', '26x20x10cm', '580g', 1, 5);

    INSERT INTO product_features (product_id, feature_text, sort_order) VALUES
    (1, '14\" Dedicated Laptop Sleeve', 1),
    (1, 'Concealed Magnetic Closure', 2),
    (2, 'Ergonomic Wide Shoulder Strap', 1);

    INSERT INTO product_variants (id, product_id, sku, color_name, color_hex, stock_quantity, is_active) VALUES
    (1, 1, 'BAL-VER-BLK', 'Black', '#1c1b1b', 45, 1),
    (2, 1, 'BAL-VER-COG', 'Cognac', '#8B5A2B', 38, 1),
    (3, 1, 'BAL-VER-COF', 'Coffee Brown', '#4A3B32', 25, 1),
    (4, 2, 'BAL-ELA-BLK', 'Black', '#1c1b1b', 30, 1),
    (5, 3, 'BAL-COR-COG', 'Cognac', '#8B5A2B', 35, 1);

    INSERT INTO product_images (product_id, variant_id, image_url, alt_text, image_type, sort_order) VALUES
    (1, 1, 'https://images.unsplash.com/photo-verona-front', 'Verona Tote Front', 'primary', 1),
    (1, 2, 'https://images.unsplash.com/photo-verona-hover', 'Verona Tote Hover', 'hover', 2),
    (2, 4, 'https://images.unsplash.com/photo-elara-front', 'Elara Shoulder Front', 'primary', 1);
");

// Bind test database to Database manager
Database::setConnection('mysql', $pdo);

// Initialize Router & Controller
$router = new Router();
$router->get('/api/products', [ProductController::class, 'index']);
$router->get('/api/products/{slug_or_id}', [ProductController::class, 'show']);

// 2. Test GET /api/products (Full Catalog)
$reqList = new Request('GET', '/api/products');
$resList = $router->dispatch($reqList);
assert($resList->getStatusCode() === 200);
$payload = $resList->getPayload();
assert(count($payload['data']['products']) === 5);
assert($payload['data']['pagination']['total'] === 5);
echo " [OK] Product API: Full catalog listing (5 products) verified\n";

// 3. Test GET /api/products?category=tote
$reqCat = new Request('GET', '/api/products', ['category' => 'tote']);
$resCat = $router->dispatch($reqCat);
assert($resCat->getStatusCode() === 200);
$catProducts = $resCat->getPayload()['data']['products'];
assert(count($catProducts) === 1);
assert($catProducts[0]['slug'] === 'verona-tote');
echo " [OK] Product API: Category filtering (?category=tote) verified\n";

// 4. Test GET /api/products?search=Laptop
$reqSearch = new Request('GET', '/api/products', ['search' => 'laptop']);
$resSearch = $router->dispatch($reqSearch);
assert($resSearch->getStatusCode() === 200);
$searchProducts = $resSearch->getPayload()['data']['products'];
assert(count($searchProducts) === 1);
assert($searchProducts[0]['name'] === 'Verona Tote');
echo " [OK] Product API: Keyword search (?search=laptop) verified\n";

// 5. Test GET /api/products?sort=price_desc
$reqSort = new Request('GET', '/api/products', ['sort' => 'price_desc']);
$resSort = $router->dispatch($reqSort);
assert($resSort->getStatusCode() === 200);
$sortProducts = $resSort->getPayload()['data']['products'];
assert($sortProducts[0]['price'] >= $sortProducts[1]['price']);
echo " [OK] Product API: Price sorting (?sort=price_desc) verified\n";

// 6. Test GET /api/products?page=2&limit=2 (Pagination)
$reqPage = new Request('GET', '/api/products', ['page' => 2, 'limit' => 2]);
$resPage = $router->dispatch($reqPage);
assert($resPage->getStatusCode() === 200);
$pageData = $resPage->getPayload()['data'];
assert(count($pageData['products']) === 2);
assert($pageData['pagination']['page'] === 2);
assert($pageData['pagination']['limit'] === 2);
assert($pageData['pagination']['total_pages'] === 3);
echo " [OK] Product API: SQL Pagination metadata verified\n";

// 7. Test GET /api/products/{slug} (Product Detail by Slug)
$reqDetailSlug = new Request('GET', '/api/products/verona-tote');
$resDetailSlug = $router->dispatch($reqDetailSlug);
assert($resDetailSlug->getStatusCode() === 200);
$detail = $resDetailSlug->getPayload()['data'];
assert($detail['slug'] === 'verona-tote');
assert(count($detail['features']) === 2);
assert(count($detail['colors']) === 3);
assert($detail['images']['primary'] === 'https://images.unsplash.com/photo-verona-front');
assert($detail['is_in_stock'] === true);
echo " [OK] Product API: Product details by slug verified\n";

// 8. Test GET /api/products/{id} (Product Detail by Numeric ID)
$reqDetailId = new Request('GET', '/api/products/1');
$resDetailId = $router->dispatch($reqDetailId);
assert($resDetailId->getStatusCode() === 200);
assert($resDetailId->getPayload()['data']['name'] === 'Verona Tote');
echo " [OK] Product API: Product details by numeric ID verified\n";

// 9. Test GET /api/products/{invalid} (404 Handling)
$req404 = new Request('GET', '/api/products/non-existent-silhouette');
$res404 = $router->dispatch($req404);
assert($res404->getStatusCode() === 404);
assert($res404->getPayload()['success'] === false);
echo " [OK] Product API: 404 Not Found handling verified\n";

echo "\nALL PHASE 5 PRODUCT API TESTS PASSED PERFECTLY!\n";
