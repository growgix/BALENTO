<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/Core/Autoloader.php';
\App\Core\Autoloader::register();

use App\Core\Env;
use App\Core\Config;
use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use App\Helpers\Logger;

echo "=== TESTING PHASE 3 CORE COMPONENTS ===\n";

// 1. Test Env loader
Env::load(__DIR__ . '/../.env.example');
$appName = Env::get('APP_NAME');
echo " [OK] Env: APP_NAME = $appName\n";

// 2. Test Config
Config::init(__DIR__ . '/../config');
$appEnv = Config::get('app.env');
$mysqlPort = Config::get('database.connections.mysql.port');
echo " [OK] Config: app.env = $appEnv, db port = $mysqlPort\n";

// 3. Test Request encapsulation
$req = new Request(
    'POST',
    '/api/orders/checkout',
    ['source' => 'web'],
    ['items' => [['id' => 1, 'qty' => 2]]],
    '{"items":[{"id":1,"qty":2}]}',
    ['content-type' => 'application/json', 'authorization' => 'Bearer sample_token_123', 'x-idempotency-key' => 'idem_9988']
);
assert($req->getMethod() === 'POST');
assert($req->getPath() === '/api/orders/checkout');
assert($req->query('source') === 'web');
assert($req->body('items')[0]['qty'] === 2);
assert($req->getBearerToken() === 'sample_token_123');
assert($req->getIdempotencyKey() === 'idem_9988');
echo " [OK] Request: Encapsulation and helpers verified\n";

// 4. Test Response format
$res = Response::success(['order_id' => 'BAL-2026-001'], 'Order placed');
assert($res->getStatusCode() === 200);
$payload = $res->getPayload();
assert($payload['success'] === true);
assert($payload['message'] === 'Order placed');
assert($payload['data']['order_id'] === 'BAL-2026-001');

$errRes = Response::unprocessable(['email' => 'Invalid email format'], 'Validation failed');
assert($errRes->getStatusCode() === 422);
assert($errRes->getPayload()['success'] === false);
assert($errRes->getPayload()['errors']['email'] === 'Invalid email format');
echo " [OK] Response: JSON standard payload formatting verified\n";

// 5. Test SQLite PDO in-memory integration & Database class
$testPdo = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
Database::setConnection('test_sqlite', $testPdo);
$conn = Database::getConnection('test_sqlite');
$conn->exec('CREATE TABLE test_table (id INTEGER PRIMARY KEY, name TEXT)');

// Test atomic transaction runner
$result = Database::transaction(function (PDO $pdo) {
    $pdo->exec("INSERT INTO test_table (name) VALUES ('Test Bag')");
    return $pdo->lastInsertId();
}, $conn);

assert((int)$result === 1);
$count = $conn->query("SELECT COUNT(*) FROM test_table")->fetchColumn();
assert((int)$count === 1);
echo " [OK] Database: PDO manager & atomic transaction runner verified\n";

// 6. Test Logger sanitization
Logger::info('Testing logger initialization', ['password' => 'secret123', 'user' => 'admin']);
assert(file_exists(__DIR__ . '/../storage/logs/app.log'));
$logContent = file_get_contents(__DIR__ . '/../storage/logs/app.log');
assert(str_contains($logContent, '***REDACTED***'));
assert(!str_contains($logContent, 'secret123'));
echo " [OK] Logger: Sanitization of sensitive data verified\n";

echo "\nALL PHASE 3 CORE TESTS PASSED SUCCESSFULLY!\n";
