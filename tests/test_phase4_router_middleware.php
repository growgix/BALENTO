<?php

declare(strict_types=1);

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
use App\Services\AuthService;

echo "=== TESTING PHASE 4: ROUTER & MIDDLEWARE PIPELINE ===\n";

Env::load(__DIR__ . '/../.env.example');
Config::init(__DIR__ . '/../config');

// 1. Test Router Basic & Parameterized Dispatch
$router = new Router();
$router->get('/api/test', function (Request $req) {
    return Response::success(['msg' => 'hello']);
});
$router->get('/api/products/{slug}', function (Request $req) {
    return Response::success(['slug' => $req->param('slug')]);
});

$req1 = new Request('GET', '/api/test');
$res1 = $router->dispatch($req1);
assert($res1->getStatusCode() === 200);
assert($res1->getPayload()['data']['msg'] === 'hello');
echo " [OK] Router: Basic GET route matching verified\n";

$req2 = new Request('GET', '/api/products/verona-tote');
$res2 = $router->dispatch($req2);
assert($res2->getStatusCode() === 200);
assert($res2->getPayload()['data']['slug'] === 'verona-tote');
echo " [OK] Router: Parameterized route extraction ({slug}) verified\n";

// 2. Test 404 and 405 Statuses
$req404 = new Request('GET', '/api/non-existent');
$res404 = $router->dispatch($req404);
assert($res404->getStatusCode() === 404);

$req405 = new Request('POST', '/api/test');
$res405 = $router->dispatch($req405);
assert($res405->getStatusCode() === 405);
echo " [OK] Router: 404 Not Found & 405 Method Not Allowed verified\n";

// 3. Test CORS Middleware & OPTIONS Preflight
$routerWithCors = new Router();
$routerWithCors->use(new CorsMiddleware());
$routerWithCors->post('/api/action', function (Request $req) {
    return Response::success(['action' => 'done']);
});

$preflightReq = new Request('OPTIONS', '/api/action', [], [], '', [
    'origin' => 'http://localhost:3000',
    'access-control-request-method' => 'POST'
]);
$preflightRes = $routerWithCors->dispatch($preflightReq);
assert($preflightRes->getStatusCode() === 204);
$headers = $preflightRes->getHeaders();
assert(isset($headers['Access-Control-Allow-Origin']));
assert($headers['Access-Control-Allow-Origin'] === 'http://localhost:3000');
echo " [OK] CORS: Preflight OPTIONS request interception verified\n";

// 4. Test JsonBodyMiddleware Malformed Payload Handling
$routerWithJson = new Router();
$routerWithJson->use(new JsonBodyMiddleware());
$routerWithJson->post('/api/submit', function (Request $req) {
    return Response::success($req->body());
});

$badJsonReq = new Request('POST', '/api/submit', [], [], '{invalid_json:', [
    'content-type' => 'application/json'
]);
$badJsonRes = $routerWithJson->dispatch($badJsonReq);
assert($badJsonRes->getStatusCode() === 400);
assert($badJsonRes->getPayload()['success'] === false);
echo " [OK] Middleware: Malformed JSON payload rejection verified\n";

// 5. Test Rate Limiting Middleware
$rateLimiter = new RateLimitMiddleware(2, 60); // 2 attempts max
$routerWithRate = new Router();
$routerWithRate->get('/api/limited', function (Request $req) {
    return Response::success(['count' => 1]);
}, [$rateLimiter]);

$reqRate = new Request('GET', '/api/limited', [], [], '', ['x-forwarded-for' => '203.0.113.195']);
$resRate1 = $routerWithRate->dispatch($reqRate);
$resRate2 = $routerWithRate->dispatch($reqRate);
$resRate3 = $routerWithRate->dispatch($reqRate);
assert($resRate1->getStatusCode() === 200);
assert($resRate2->getStatusCode() === 200);
assert($resRate3->getStatusCode() === 429);
echo " [OK] Middleware: Rate Limiting & 429 status code verified\n";

// 6. Test AuthService Token Issuance & AuthMiddleware Guard
$adminPayload = ['id' => 1, 'username' => 'admin', 'email' => 'admin@balento.com', 'role' => 'admin'];
$token = AuthService::generateToken($adminPayload);
$verifiedClaims = AuthService::verifyToken($token);
assert($verifiedClaims !== null);
assert($verifiedClaims['username'] === 'admin');

$routerWithAuth = new Router();
$routerWithAuth->get('/api/admin/secret', function (Request $req) {
    return Response::success(['secret' => 'top_secret_data']);
}, [new AuthMiddleware(['admin'])]);

// Unauthorized request without token
$unauthReq = new Request('GET', '/api/admin/secret');
$unauthRes = $routerWithAuth->dispatch($unauthReq);
assert($unauthRes->getStatusCode() === 401);

// Authorized request with Bearer token
$authReq = new Request('GET', '/api/admin/secret', [], [], '', [
    'authorization' => "Bearer {$token}"
]);
$authRes = $routerWithAuth->dispatch($authReq);
assert($authRes->getStatusCode() === 200);
assert($authRes->getPayload()['data']['secret'] === 'top_secret_data');
echo " [OK] Auth: Token generation, cryptographic verification & AuthMiddleware protection verified\n";

echo "\nALL PHASE 4 TESTS PASSED PERFECTLY!\n";
