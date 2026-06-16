<?php
declare(strict_types=1);

class Logger
{
    private static function write(string $level, string $message): void
    {
        $config = require __DIR__ . '/../config/config.php';
        $dir = $config['logs_dir'];
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }
        $file = $dir . '/' . date('Y-m-d') . '.log';
        $line = sprintf('[%s] %s: %s%s', date('Y-m-d H:i:s'), strtoupper($level), $message, PHP_EOL);
        @file_put_contents($file, $line, FILE_APPEND | LOCK_EX);
    }

    public static function info(string $message): void
    {
        self::write('info', $message);
    }

    public static function error(string $message): void
    {
        self::write('error', $message);
    }

    public static function warning(string $message): void
    {
        self::write('warning', $message);
    }
}
