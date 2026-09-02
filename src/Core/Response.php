<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Standardized API Response emitter.
 */
final class Response
{
    private int $statusCode;
    private array $headers;
    private array $payload;

    public function __construct(int $statusCode = 200, array $payload = [], array $headers = [])
    {
        $this->statusCode = $statusCode;
        $this->payload = $payload;
        $this->headers = array_merge([
            'Content-Type' => 'application/json; charset=utf-8',
            'X-Content-Type-Options' => 'nosniff',
            'X-Frame-Options' => 'DENY',
        ], $headers);
    }

    public static function json(
        bool $success,
        string $message,
        mixed $data = [],
        array $errors = [],
        int $statusCode = 200,
        array $headers = []
    ): self {
        $payload = [
            'success' => $success,
            'message' => $message,
            'data' => is_object($data) || is_array($data) ? $data : (object) [],
            'errors' => $errors,
        ];

        return new self($statusCode, $payload, $headers);
    }

    public static function success(mixed $data = [], string $message = 'Operation successful', int $statusCode = 200, array $headers = []): self
    {
        return self::json(true, $message, $data, [], $statusCode, $headers);
    }

    public static function created(mixed $data = [], string $message = 'Resource created successfully', array $headers = []): self
    {
        return self::json(true, $message, $data, [], 201, $headers);
    }

    public static function error(string $message, array $errors = [], int $statusCode = 400, array $headers = []): self
    {
        return self::json(false, $message, [], $errors, $statusCode, $headers);
    }

    public static function notFound(string $message = 'Resource not found', array $headers = []): self
    {
        return self::error($message, [], 404, $headers);
    }

    public static function unauthorized(string $message = 'Authentication required', array $headers = []): self
    {
        return self::error($message, [], 401, $headers);
    }

    public static function forbidden(string $message = 'Access forbidden', array $headers = []): self
    {
        return self::error($message, [], 403, $headers);
    }

    public static function unprocessable(array $errors, string $message = 'Validation failed', array $headers = []): self
    {
        return self::error($message, $errors, 422, $headers);
    }

    public static function conflict(string $message = 'Conflict detected', array $headers = []): self
    {
        return self::error($message, [], 409, $headers);
    }

    public static function tooManyRequests(string $message = 'Too many requests. Please slow down.', array $headers = []): self
    {
        return self::error($message, [], 429, $headers);
    }

    public static function serverError(string $message = 'Internal server error', array $headers = []): self
    {
        return self::error($message, [], 500, $headers);
    }

    public function withHeader(string $name, string $value): self
    {
        $this->headers[$name] = $value;
        return $this;
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    public function getPayload(): array
    {
        return $this->payload;
    }

    public function getHeaders(): array
    {
        return $this->headers;
    }

    public function send(): void
    {
        if (!headers_sent()) {
            http_response_code($this->statusCode);
            foreach ($this->headers as $name => $value) {
                header("{$name}: {$value}");
            }
        }

        echo json_encode($this->payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }
}
