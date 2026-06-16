<?php
declare(strict_types=1);

/**
 * Shared class autoloader for both HTTP entry points (via bootstrap.php)
 * and CLI scripts (cron.php, create_admin.php). Centralizing this avoids
 * hand-maintained require_once lists in CLI scripts going stale every time
 * a class gains a new dependency.
 */

require_once __DIR__ . '/config.php';

if (!function_exists('register_app_autoloader')) {
    function register_app_autoloader(): void
    {
        static $registered = false;
        if ($registered) {
            return;
        }

        spl_autoload_register(function (string $class) {
            $dirs = ['config', 'services', 'middleware', 'models'];
            foreach ($dirs as $dir) {
                $path = __DIR__ . "/../$dir/$class.php";
                if (is_file($path)) {
                    require_once $path;
                    return;
                }
            }
        });

        $registered = true;
    }
}

register_app_autoloader();
