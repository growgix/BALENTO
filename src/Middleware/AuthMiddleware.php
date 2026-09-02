<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Config;
use App\Core\Request;
use App\Core\Response;
use App\Services\AuthService;

/**
 * Authentication and Role-Based Authorization Guard.
 */
final class AuthMiddleware implements MiddlewareInterface
{
    private array $allowedRoles;

    public function __construct(array $allowedRoles = ['admin'])
    {
        $this->allowedRoles = $allowedRoles;
    }

    public function process(Request $request, callable $next): Response
    {
        $token = $request->getBearerToken();

        // Fallback to cookie if Authorization header is absent
        if ($token === null) {
            $cookieName = (string) Config::get('auth.cookie_name', 'balento_admin_session');
            $token = $_COOKIE[$cookieName] ?? null;
        }

        if (!$token) {
            return Response::unauthorized('Access denied. Authentication token required.');
        }

        $claims = AuthService::verifyToken($token);
        if (!$claims) {
            return Response::unauthorized('Invalid or expired authentication token.');
        }

        $userRole = $claims['role'] ?? '';
        if (!empty($this->allowedRoles) && !in_array($userRole, $this->allowedRoles, true)) {
            return Response::forbidden('Access forbidden. Insufficient permissions for this resource.');
        }

        // Attach authenticated user payload to request
        $request->setRouteParams(array_merge($request->query(), ['_auth_user' => $claims]));

        return $next($request);
    }
}
