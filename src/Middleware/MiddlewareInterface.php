<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Request;
use App\Core\Response;

/**
 * Standard Middleware contract.
 */
interface MiddlewareInterface
{
    /**
     * Process an incoming request and return a response.
     *
     * @param Request $request
     * @param callable $next fn(Request $request): Response
     * @return Response
     */
    public function process(Request $request, callable $next): Response;
}
