<?php

declare(strict_types=1);

use App\Core\Env;

return [
    'secret_key' => Env::get('AUTH_SECRET_KEY', 'balento_insecure_development_secret_key_change_me_in_prod'),
    'token_ttl' => (int) Env::get('AUTH_TOKEN_TTL_SECONDS', '86400'),
    'cookie_name' => Env::get('AUTH_COOKIE_NAME', 'balento_admin_session'),
    'secure_cookie' => Env::getBool('AUTH_SECURE_COOKIE', false),
    'hash_algo' => PASSWORD_BCRYPT,
    'hash_options' => ['cost' => 12],
    'roles' => ['admin', 'manager', 'staff'],
];
