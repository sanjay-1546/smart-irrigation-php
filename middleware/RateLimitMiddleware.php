<?php
declare(strict_types=1);

class RateLimitMiddleware
{
    public static function enforce(string $routeName): void
    {
        $key = client_ip() . ':' . $routeName;
        $limiter = new RateLimiter();
        if ($limiter->tooManyRequests($key)) {
            Response::tooManyRequests('Rate limit exceeded. Please slow down.');
        }
    }
}
