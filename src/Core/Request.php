<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Encapsulation of HTTP Request.
 */
final class Request
{
    private string $method;
    private string $path;
    private array $queryParams;
    private array $body;
    private string $rawBody;
    private array $headers;
    private array $routeParams = [];

    public function __construct(
        string $method,
        string $path,
        array $queryParams = [],
        array $body = [],
        string $rawBody = '',
        array $headers = []
    ) {
        $this->method = strtoupper($method);
        $this->path = '/' . trim($path, '/');
        $this->queryParams = $queryParams;
        $this->body = $body;
        $this->rawBody = $rawBody;
        $this->headers = $headers;
    }

    public static function createFromGlobals(): self
    {
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        $uri = $_SERVER['REQUEST_URI'] ?? '/';
        
        $path = parse_url($uri, PHP_URL_PATH) ?? '/';
        
        // Strip base folder if running in subdirectory
        $scriptName = $_SERVER['SCRIPT_NAME'] ?? '';
        $baseFolder = dirname($scriptName);
        if ($baseFolder !== '/' && $baseFolder !== '\\' && str_starts_with($path, $baseFolder)) {
            $path = substr($path, strlen($baseFolder));
        }

        $headers = self::extractHeaders();
        $rawBody = (string) file_get_contents('php://input');
        
        $body = [];
        $contentType = $headers['content-type'] ?? '';
        
        if (str_contains($contentType, 'application/json') && trim($rawBody) !== '') {
            $decoded = json_decode($rawBody, true);
            if (is_array($decoded)) {
                $body = $decoded;
            }
        } elseif (!empty($_POST)) {
            $body = $_POST;
        }

        return new self($method, $path, $_GET, $body, $rawBody, $headers);
    }

    public function getMethod(): string
    {
        return $this->method;
    }

    public function getPath(): string
    {
        return $this->path;
    }

    public function query(?string $key = null, mixed $default = null): mixed
    {
        if ($key === null) {
            return $this->queryParams;
        }
        return $this->queryParams[$key] ?? $default;
    }

    public function body(?string $key = null, mixed $default = null): mixed
    {
        if ($key === null) {
            return $this->body;
        }
        return $this->body[$key] ?? $default;
    }

    public function getRawBody(): string
    {
        return $this->rawBody;
    }

    public function header(string $name, ?string $default = null): ?string
    {
        $normalized = strtolower($name);
        return $this->headers[$normalized] ?? $default;
    }

    public function getHeaders(): array
    {
        return $this->headers;
    }

    public function getBearerToken(): ?string
    {
        $auth = $this->header('authorization');
        if ($auth && preg_match('/Bearer\s+(\S+)/i', $auth, $matches)) {
            return $matches[1];
        }
        return null;
    }

    public function getIdempotencyKey(): ?string
    {
        return $this->header('x-idempotency-key');
    }

    public function setRouteParams(array $params): void
    {
        $this->routeParams = $params;
    }

    public function param(string $key, mixed $default = null): mixed
    {
        return $this->routeParams[$key] ?? $default;
    }

    public function getClientIp(): string
    {
        $forwarded = $this->header('x-forwarded-for');
        if ($forwarded) {
            $parts = explode(',', $forwarded);
            return trim($parts[0]);
        }
        return $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
    }

    private static function extractHeaders(): array
    {
        $headers = [];
        foreach ($_SERVER as $key => $value) {
            if (str_starts_with($key, 'HTTP_')) {
                $name = strtolower(str_replace('_', '-', substr($key, 5)));
                $headers[$name] = (string) $value;
            } elseif (in_array($key, ['CONTENT_TYPE', 'CONTENT_LENGTH', 'CONTENT_MD5'], true)) {
                $name = strtolower(str_replace('_', '-', $key));
                $headers[$name] = (string) $value;
            }
        }
        return $headers;
    }
}
