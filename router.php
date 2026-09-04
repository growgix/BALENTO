<?php

declare(strict_types=1);

/**
 * BALENTO E-Commerce Router Script for PHP Built-in Server.
 * Usage: php -S localhost:8000 router.php
 */

$rawUri = $_SERVER['REQUEST_URI'] ?? '/';
$path = urldecode(parse_url($rawUri, PHP_URL_PATH) ?? '/');

// 1. Route API requests to public/index.php front controller
if (str_starts_with($path, '/api')) {
    require __DIR__ . '/public/index.php';
    return true;
}

// 2. Direct static file handling
$filePath = __DIR__ . $path;
if ($path !== '/' && file_exists($filePath) && !is_dir($filePath)) {
    return false; // Let PHP built-in webserver serve the static file directly
}

// 3. Admin Dashboard routing
if ($path === '/admin' || $path === '/admin/' || str_starts_with($path, '/admin/')) {
    if (is_dir($filePath) || $path === '/admin' || $path === '/admin/') {
        require __DIR__ . '/admin/index.php';
        return true;
    }
}

// 4. Storefront Root / index.html
if ($path === '/' || $path === '/index.html') {
    require __DIR__ . '/index.html';
    return true;
}

// 5. Code / Editorial demo page
if ($path === '/code' || $path === '/code.html') {
    if (file_exists(__DIR__ . '/code.html')) {
        require __DIR__ . '/code.html';
        return true;
    }
}

return false;
