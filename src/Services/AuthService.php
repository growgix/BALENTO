<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Config;
use RuntimeException;

/**
 * High-Security Authentication Service.
 * Implements HMAC-SHA256 Token issuance/verification and secure password hashing.
 */
final class AuthService
{
    public static function hashPassword(string $password): string
    {
        return password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
    }

    public static function verifyPassword(string $password, string $hash): bool
    {
        return password_verify($password, $hash);
    }

    /**
     * Generate signed access token for admin claims.
     */
    public static function generateToken(array $adminData): string
    {
        $secret = (string) Config::get('auth.secret_key');
        if (strlen($secret) < 16) {
            throw new RuntimeException('AUTH_SECRET_KEY is insecure or missing.');
        }

        $ttl = (int) Config::get('auth.token_ttl', 86400);
        $issuedAt = time();
        $expiresAt = $issuedAt + $ttl;

        $header = [
            'typ' => 'JWT',
            'alg' => 'HS256',
        ];

        $payload = [
            'sub' => $adminData['id'],
            'username' => $adminData['username'],
            'email' => $adminData['email'],
            'role' => $adminData['role'] ?? 'admin',
            'iat' => $issuedAt,
            'exp' => $expiresAt,
        ];

        $base64Header = self::base64UrlEncode(json_encode($header));
        $base64Payload = self::base64UrlEncode(json_encode($payload));
        $signature = hash_hmac('sha256', "{$base64Header}.{$base64Payload}", $secret, true);
        $base64Signature = self::base64UrlEncode($signature);

        return "{$base64Header}.{$base64Payload}.{$base64Signature}";
    }

    /**
     * Validate token and extract claims payload. Returns null if invalid or expired.
     */
    public static function verifyToken(string $token): ?array
    {
        $parts = explode('.', $token);
        if (count($parts) !== 3) {
            return null;
        }

        [$base64Header, $base64Payload, $base64Signature] = $parts;
        $secret = (string) Config::get('auth.secret_key');

        $expectedSignature = self::base64UrlEncode(
            hash_hmac('sha256', "{$base64Header}.{$base64Payload}", $secret, true)
        );

        if (!hash_equals($expectedSignature, $base64Signature)) {
            return null;
        }

        $payloadJson = self::base64UrlDecode($base64Payload);
        if ($payloadJson === false) {
            return null;
        }

        $payload = json_decode($payloadJson, true);
        if (!is_array($payload) || !isset($payload['exp']) || $payload['exp'] < time()) {
            return null;
        }

        return $payload;
    }

    private static function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    private static function base64UrlDecode(string $data): string|false
    {
        return base64_decode(strtr($data, '-_', '+/'));
    }
}
