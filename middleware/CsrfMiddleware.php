<?php
declare(strict_types=1);

/**
 * CSRF protection for browser-originated, cookie/session-based requests
 * (the web dashboard). Not applicable to JWT bearer-token calls (mobile app,
 * NodeMCU) since those aren't subject to ambient browser credential reuse.
 *
 * Dashboard flow: GET /api/auth/csrf_token.php to obtain a token (stored in
 * PHP session), then send it back as X-CSRF-Token on state-changing
 * requests that rely on cookies.
 */
class CsrfMiddleware
{
    public static function issueToken(): string
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }

    public static function verify(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }
        $sent = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
        $expected = $_SESSION['csrf_token'] ?? '';

        if ($expected === '' || $sent === '' || !hash_equals($expected, $sent)) {
            Response::forbidden('Invalid or missing CSRF token');
        }
    }
}
