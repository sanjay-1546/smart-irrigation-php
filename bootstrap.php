<?php
declare(strict_types=1);

/**
 * Shared bootstrap included by every API endpoint. Sets up error display,
 * timezone, autoloading, CORS, and JSON content negotiation.
 */

$config = require __DIR__ . '/config/config.php';

date_default_timezone_set($config['app']['timezone']);

if ($config['app']['debug']) {
    error_reporting(E_ALL);
    ini_set('display_errors', '1');
} else {
    error_reporting(0);
    ini_set('display_errors', '0');
}

spl_autoload_register(function (string $class) {
    $dirs = ['config', 'services', 'middleware', 'models'];
    foreach ($dirs as $dir) {
        $path = __DIR__ . "/$dir/$class.php";
        if (is_file($path)) {
            require_once $path;
            return;
        }
    }
});

set_exception_handler(function (Throwable $e) {
    Logger::error($e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
    Response::serverError('An unexpected error occurred');
});

// CORS: allow the dashboard/mobile app to call the API from any origin.
// Credentials are conveyed via Authorization header (JWT), not cookies, so a
// wildcard origin does not expose session-based attacks.
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-API-Key, X-CSRF-Token');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

function request_body(): array
{
    $raw = file_get_contents('php://input');
    if ($raw === false || $raw === '') {
        return $_POST;
    }
    $decoded = json_decode($raw, true);
    return is_array($decoded) ? $decoded : $_POST;
}

function client_ip(): string
{
    return $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? 'unknown';
}
