<?php
declare(strict_types=1);

require_once __DIR__ . '/../../bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    Response::error('Method not allowed', 405);
}

$claims = AuthMiddleware::authenticate();
$user = (new User())->find((int) $claims['sub']);

if (!$user) {
    Response::notFound('User not found');
}

Response::success($user);
