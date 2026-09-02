<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Config;
use App\Core\Request;
use App\Core\Response;

/**
 * Robust CORS Middleware.
 */
final class CorsMiddleware implements MiddlewareInterface
{
    public function process(Request $request, callable $next): Response
    {
        $origin = $request->header('origin');
        $allowedOrigins = Config::get('cors.allowed_origins', []);
        $allowedMethods = implode(', ', Config::get('cors.allowed_methods', ['GET', 'POST', 'PUT', 'DELETE', 'OPTIONS']));
        $allowedHeaders = implode(', ', Config::get('cors.allowed_headers', ['Content-Type', 'Authorization', 'X-Requested-With', 'X-Idempotency-Key']));
        $exposedHeaders = implode(', ', Config::get('cors.exposed_headers', ['X-Idempotency-Key']));
        $maxAge = (string) Config::get('cors.max_age', 86400);
        $supportsCredentials = Config::get('cors.supports_credentials', true) ? 'true' : 'false';

        $corsHeaders = [];

        if ($origin !== null) {
            // Check if origin is allowed or if in local/dev environment
            $isAllowed = in_array($origin, $allowedOrigins, true) || 
                         Config::get('app.env') === 'development' ||
                         in_array('*', $allowedOrigins, true);

            if ($isAllowed) {
                $corsHeaders['Access-Control-Allow-Origin'] = $origin;
                $corsHeaders['Access-Control-Allow-Methods'] = $allowedMethods;
                $corsHeaders['Access-Control-Allow-Headers'] = $allowedHeaders;
                $corsHeaders['Access-Control-Expose-Headers'] = $exposedHeaders;
                $corsHeaders['Access-Control-Allow-Credentials'] = $supportsCredentials;
                $corsHeaders['Access-Control-Max-Age'] = $maxAge;
            }
        }

        // Handle preflight OPTIONS request
        if ($request->getMethod() === 'OPTIONS') {
            return new Response(204, [], $corsHeaders);
        }

        /** @var Response $response */
        $response = $next($request);

        foreach ($corsHeaders as $header => $value) {
            $response = $response->withHeader($header, $value);
        }

        return $response;
    }
}
