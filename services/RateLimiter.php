<?php
declare(strict_types=1);

/**
 * Simple fixed-window rate limiter backed by the rate_limits table.
 * Bucket key is typically "ip:route" or "device:device_id".
 */
class RateLimiter
{
    private PDO $db;
    private int $maxRequests;
    private int $windowSeconds;

    public function __construct()
    {
        $config = require_once __DIR__ . '/../config/config.php';
        $this->db = Database::connection();
        $this->maxRequests = $config['rate_limit']['max_requests'];
        $this->windowSeconds = $config['rate_limit']['window_seconds'];
    }

    public function tooManyRequests(string $bucketKey): bool
    {
        $stmt = $this->db->prepare('SELECT * FROM rate_limits WHERE bucket_key = ? LIMIT 1');
        $stmt->execute([$bucketKey]);
        $row = $stmt->fetch();

        $now = time();

        if (!$row) {
            $ins = $this->db->prepare(
                'INSERT INTO rate_limits (bucket_key, request_count, window_start) VALUES (?, 1, NOW())'
            );
            $ins->execute([$bucketKey]);
            return false;
        }

        $windowStart = strtotime($row['window_start']);
        if ($now - $windowStart >= $this->windowSeconds) {
            $upd = $this->db->prepare(
                'UPDATE rate_limits SET request_count = 1, window_start = NOW() WHERE bucket_key = ?'
            );
            $upd->execute([$bucketKey]);
            return false;
        }

        if ((int) $row['request_count'] >= $this->maxRequests) {
            return true;
        }

        $upd = $this->db->prepare(
            'UPDATE rate_limits SET request_count = request_count + 1 WHERE bucket_key = ?'
        );
        $upd->execute([$bucketKey]);
        return false;
    }
}
