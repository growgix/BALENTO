<?php

declare(strict_types=1);

namespace App\Core;

use App\Middleware\MiddlewareInterface;
use App\Helpers\Logger;
use Throwable;

/**
 * High-Performance API Router with Global & Route-Specific Middleware Pipelines.
 */
final class Router
{
    private array $routes = [];
    private array $globalMiddlewares = [];
    private array $groupStack = [];

    public function use(MiddlewareInterface $middleware): self
    {
        $this->globalMiddlewares[] = $middleware;
        return $this;
    }

    public function get(string $path, callable|array $handler, array $middlewares = []): self
    {
        return $this->addRoute('GET', $path, $handler, $middlewares);
    }

    public function post(string $path, callable|array $handler, array $middlewares = []): self
    {
        return $this->addRoute('POST', $path, $handler, $middlewares);
    }

    public function put(string $path, callable|array $handler, array $middlewares = []): self
    {
        return $this->addRoute('PUT', $path, $handler, $middlewares);
    }

    public function delete(string $path, callable|array $handler, array $middlewares = []): self
    {
        return $this->addRoute('DELETE', $path, $handler, $middlewares);
    }

    public function options(string $path, callable|array $handler, array $middlewares = []): self
    {
        return $this->addRoute('OPTIONS', $path, $handler, $middlewares);
    }

    public function group(array $attributes, callable $callback): void
    {
        $this->groupStack[] = $attributes;
        $callback($this);
        array_pop($this->groupStack);
    }

    private function addRoute(string $method, string $path, callable|array $handler, array $middlewares = []): self
    {
        $prefix = '';
        $groupMiddlewares = [];

        foreach ($this->groupStack as $group) {
            if (isset($group['prefix'])) {
                $prefix .= '/' . trim($group['prefix'], '/');
            }
            if (isset($group['middleware'])) {
                $m = is_array($group['middleware']) ? $group['middleware'] : [$group['middleware']];
                $groupMiddlewares = array_merge($groupMiddlewares, $m);
            }
        }

        $fullPath = '/' . trim($prefix . '/' . trim($path, '/'), '/');
        if ($fullPath === '') {
            $fullPath = '/';
        }

        $allMiddlewares = array_merge($groupMiddlewares, $middlewares);

        // Compile regex pattern for parameterized segments e.g. {id} or {slug}
        $pattern = preg_replace('/\{([a-zA-Z0-9_]+)\}/', '(?P<$1>[^/]+)', $fullPath);
        $regex = '#^' . $pattern . '$#';

        $this->routes[] = [
            'method' => $method,
            'path' => $fullPath,
            'regex' => $regex,
            'handler' => $handler,
            'middlewares' => $allMiddlewares,
        ];

        return $this;
    }

    /**
     * Dispatch the incoming Request through the global middleware pipeline and route handler.
     */
    public function dispatch(Request $request): Response
    {
        try {
            $routeMatcher = function (Request $req): Response {
                $method = $req->getMethod();
                $path = $req->getPath();

                $matchedRoute = null;
                $matchedParams = [];
                $methodNotAllowed = false;

                foreach ($this->routes as $route) {
                    if (preg_match($route['regex'], $path, $matches)) {
                        if ($route['method'] === $method) {
                            $matchedRoute = $route;
                            foreach ($matches as $key => $val) {
                                if (is_string($key)) {
                                    $matchedParams[$key] = $val;
                                }
                            }
                            break;
                        } else {
                            $methodNotAllowed = true;
                        }
                    }
                }

                if ($matchedRoute === null) {
                    if ($methodNotAllowed) {
                        return Response::error('Method not allowed for requested endpoint.', [], 405);
                    }
                    return Response::notFound("Endpoint not found: {$method} {$path}");
                }

                $req->setRouteParams($matchedParams);

                $coreHandler = function (Request $r) use ($matchedRoute): Response {
                    $handler = $matchedRoute['handler'];

                    if (is_array($handler) && count($handler) === 2) {
                        [$class, $methodName] = $handler;
                        if (is_string($class)) {
                            $instance = new $class();
                        } else {
                            $instance = $class;
                        }
                        $result = $instance->$methodName($r);
                    } elseif (is_callable($handler)) {
                        $result = $handler($r);
                    } else {
                        return Response::serverError('Invalid route handler definition.');
                    }

                    if ($result instanceof Response) {
                        return $result;
                    }

                    if (is_array($result)) {
                        return Response::success($result);
                    }

                    return Response::success([], (string) $result);
                };

                // Execute route-specific middlewares
                $routePipeline = array_reduce(
                    array_reverse($matchedRoute['middlewares']),
                    function ($next, $middleware) {
                        return function (Request $r) use ($middleware, $next) {
                            if ($middleware instanceof MiddlewareInterface) {
                                return $middleware->process($r, $next);
                            }
                            if (is_callable($middleware)) {
                                return $middleware($r, $next);
                            }
                            return $next($r);
                        };
                    },
                    $coreHandler
                );

                return $routePipeline($req);
            };

            // Wrap global middleware pipeline around route matcher
            $globalRunner = array_reduce(
                array_reverse($this->globalMiddlewares),
                function ($next, $middleware) {
                    return function (Request $req) use ($middleware, $next) {
                        if ($middleware instanceof MiddlewareInterface) {
                            return $middleware->process($req, $next);
                        }
                        if (is_callable($middleware)) {
                            return $middleware($req, $next);
                        }
                        return $next($req);
                    };
                },
                $routeMatcher
            );

            return $globalRunner($request);
        } catch (Throwable $e) {
            Logger::error("Unhandled exception in API request [{$request->getMethod()} {$request->getPath()}]: " . $e->getMessage(), [
                'exception' => get_class($e),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);

            if (Config::get('app.debug', false)) {
                return Response::serverError('Server Error: ' . $e->getMessage(), [
                    'exception' => get_class($e),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                ]);
            }

            return Response::serverError('An unexpected error occurred. Please try again later.');
        }
    }
}
