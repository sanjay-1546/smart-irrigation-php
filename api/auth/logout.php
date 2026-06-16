<?php
declare(strict_types=1);

require_once __DIR__ . '/../../bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    Response::error('Method not allowed', 405);
}

// JWTs are stateless; logout is handled client-side by discarding the
// token. This endpoint exists for symmetry and to validate the token first.
AuthMiddleware::authenticate();

Response::success(null, 'Logged out successfully');
