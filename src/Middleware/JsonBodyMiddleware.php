<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Request;
use App\Core\Response;

/**
 * JSON Request Validation Middleware.
 */
final class JsonBodyMiddleware implements MiddlewareInterface
{
    private const MAX_BODY_BYTES = 2097152; // 2MB limit

    public function process(Request $request, callable $next): Response
    {
        $method = $request->getMethod();
        
        if (in_array($method, ['POST', 'PUT', 'PATCH'], true)) {
            $rawBody = $request->getRawBody();

            if (strlen($rawBody) > self::MAX_BODY_BYTES) {
                return Response::error('Request payload exceeds maximum allowed size (2MB).', [], 413);
            }

            $contentType = $request->header('content-type', '');
            if (str_contains($contentType, 'application/json') && trim($rawBody) !== '') {
                json_decode($rawBody);
                if (json_last_error() !== JSON_ERROR_NONE) {
                    return Response::error('Malformed JSON syntax in request body.', [
                        'json' => json_last_error_msg(),
                    ], 400);
                }
            }
        }

        return $next($request);
    }
}
