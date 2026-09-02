<?php

declare(strict_types=1);

use App\Core\Env;

$allowedOrigins = array_filter(
    array_map('trim', explode(',', Env::get('CORS_ALLOWED_ORIGINS', 'http://localhost:3000,http://127.0.0.1:3000,http://localhost:8000,http://127.0.0.1:8000')))
);

return [
    'allowed_origins' => $allowedOrigins,
    'allowed_methods' => ['GET', 'POST', 'PUT', 'DELETE', 'OPTIONS', 'PATCH'],
    'allowed_headers' => ['Content-Type', 'Authorization', 'X-Requested-With', 'X-Idempotency-Key', 'Accept', 'Origin'],
    'exposed_headers' => ['X-Idempotency-Key'],
    'max_age' => 86400, // 24 hours preflight cache
    'supports_credentials' => true,
];
