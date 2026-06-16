<?php
declare(strict_types=1);

/**
 * Validates the Bearer JWT on protected endpoints and exposes the decoded
 * user claims. Call AuthMiddleware::user() to get the current principal, or
 * AuthMiddleware::requireRole(['admin']) to enforce role-based access.
 */
class AuthMiddleware
{
    private static ?array $user = null;

    public static function authenticate(): array
    {
        if (self::$user !== null) {
            return self::$user;
        }

        $config = require __DIR__ . '/../config/config.php';
        $header = $_SERVER['HTTP_AUTHORIZATION'] ?? '';

        if (!preg_match('/Bearer\s+(\S+)/', $header, $matches)) {
            Response::unauthorized('Missing or malformed Authorization header');
        }

        try {
            $payload = JWT::decode($matches[1], $config['jwt']['secret']);
        } catch (RuntimeException $e) {
            Response::unauthorized('Invalid or expired token');
        }

        self::$user = $payload;
        return $payload;
    }

    public static function requireRole(array $roles): array
    {
        $user = self::authenticate();
        if (!in_array($user['role'], $roles, true)) {
            Response::forbidden('You do not have permission to perform this action');
        }
        return $user;
    }
}
