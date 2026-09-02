<?php

declare(strict_types=1);

use App\Core\Env;

return [
    'name' => Env::get('APP_NAME', 'BALENTO API'),
    'env' => Env::get('APP_ENV', 'production'),
    'debug' => Env::getBool('APP_DEBUG', false),
    'url' => Env::get('APP_URL', 'http://localhost:8000'),
    'timezone' => Env::get('APP_TIMEZONE', 'Asia/Kolkata'),
    'locale' => 'en_IN',
];
