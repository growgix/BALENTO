<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Config;
use App\Core\Env;
use App\Core\Request;
use App\Core\Response;

/**
 * Lightweight File-Backed Rate Limiting Middleware.
 */
final class RateLimitMiddleware implements MiddlewareInterface
{
    private int $maxAttempts;
    private int $decaySeconds;

    public function __construct(int $maxAttempts = 60, int $decaySeconds = 60)
    {
        $this->maxAttempts = $maxAttempts;
        $this->decaySeconds = $decaySeconds;
    }

    public static function forRoute(string $routeType): self
    {
        $limit = match ($routeType) {
            'login' => (int) Env::get('RATE_LIMIT_LOGIN', 5),
            'checkout' => (int) Env::get('RATE_LIMIT_CHECKOUT', 10),
            'pincode' => (int) Env::get('RATE_LIMIT_PINCODE', 60),
            'coupon' => (int) Env::get('RATE_LIMIT_COUPON', 30),
            default => (int) Env::get('RATE_LIMIT_GENERAL', 120),
        };

        return new self($limit, 60);
    }

    public function process(Request $request, callable $next): Response
    {
        $ip = $request->getClientIp();
        $pathKey = md5($request->getPath());
        $cacheKey = "ratelimit_{$pathKey}_" . md5($ip);

        $cacheDir = dirname(__DIR__, 2) . '/storage/cache';
        if (!is_dir($cacheDir)) {
            @mkdir($cacheDir, 0755, true);
        }

        $cacheFile = "{$cacheDir}/{$cacheKey}.json";
        $now = time();
        $data = ['attempts' => 0, 'reset_at' => $now + $this->decaySeconds];

        if (file_exists($cacheFile)) {
            $content = file_get_contents($cacheFile);
            if ($content !== false) {
                $decoded = json_decode($content, true);
                if (is_array($decoded) && isset($decoded['reset_at']) && $decoded['reset_at'] > $now) {
                    $data = $decoded;
                }
            }
        }

        $data['attempts']++;
        @file_put_contents($cacheFile, json_encode($data), LOCK_EX);

        $remaining = max(0, $this->maxAttempts - $data['attempts']);
        $headers = [
            'X-RateLimit-Limit' => (string) $this->maxAttempts,
            'X-RateLimit-Remaining' => (string) $remaining,
            'X-RateLimit-Reset' => (string) $data['reset_at'],
        ];

        if ($data['attempts'] > $this->maxAttempts) {
            $res = Response::tooManyRequests('Too many requests. Please wait a moment before trying again.');
            foreach ($headers as $k => $v) {
                $res = $res->withHeader($k, $v);
            }
            return $res;
        }

        /** @var Response $response */
        $response = $next($request);

        foreach ($headers as $k => $v) {
            $response = $response->withHeader($k, $v);
        }

        return $response;
    }
}
