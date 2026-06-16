<?php
declare(strict_types=1);

/**
 * Loads environment configuration. Reads .env (repo root) if present,
 * otherwise falls back to real environment variables (useful for cPanel
 * envvars).
 */

if (!function_exists('env')) {
function env(string $key, $default = null)
{
    static $loaded = false;
    static $vars = [];

    if (!$loaded) {
        $path = __DIR__ . '/../.env';
        if (is_file($path)) {
            foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
                $line = trim($line);
                if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
                    continue;
                }
                [$k, $v] = explode('=', $line, 2);
                $vars[trim($k)] = trim($v, " \t\n\r\0\x0B\"'");
            }
        }
        $loaded = true;
    }

    if (array_key_exists($key, $vars)) {
        return $vars[$key];
    }

    $envVal = getenv($key);
    return $envVal !== false ? $envVal : $default;
}
}

return [
    'app' => [
        'name' => env('APP_NAME', 'Smart Farm Irrigation Backend'),
        'env' => env('APP_ENV', 'production'),
        'debug' => filter_var(env('APP_DEBUG', 'false'), FILTER_VALIDATE_BOOLEAN),
        'timezone' => env('APP_TIMEZONE', 'Asia/Kolkata'),
        'base_url' => env('APP_BASE_URL', ''),
    ],
    'db' => [
        'host' => env('DB_HOST', '127.0.0.1'),
        'port' => env('DB_PORT', '3306'),
        'name' => env('DB_NAME', 'smart_irrigation'),
        'user' => env('DB_USER', 'root'),
        'pass' => env('DB_PASS', ''),
        'charset' => 'utf8mb4',
    ],
    'jwt' => [
        'secret' => env('JWT_SECRET', ''),
        'issuer' => env('JWT_ISSUER', 'smart-farm-irrigation'),
        'ttl' => (int) env('JWT_TTL', 3600),
    ],
    'weather' => [
        'api_key' => env('OPENWEATHER_API_KEY', ''),
        'base_url' => 'https://api.openweathermap.org/data/2.5/weather',
    ],
    'rate_limit' => [
        'max_requests' => (int) env('RATE_LIMIT_MAX', 60),
        'window_seconds' => (int) env('RATE_LIMIT_WINDOW', 60),
    ],
    'uploads_dir' => __DIR__ . '/../uploads',
    'logs_dir' => __DIR__ . '/../logs',
];
