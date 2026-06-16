<?php
declare(strict_types=1);

require_once __DIR__ . '/../../bootstrap.php';

AuthMiddleware::authenticate();
$alert = new Alert();
$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $farmId = (int) ($_GET['farm_id'] ?? 0);
    if (!$farmId) {
        Response::error('farm_id is required', 422);
    }
    $unresolvedOnly = filter_var($_GET['unresolved'] ?? false, FILTER_VALIDATE_BOOLEAN);
    Response::success($alert->allForFarm($farmId, $unresolvedOnly));
} elseif ($method === 'PUT') {
    AuthMiddleware::requireRole(['admin', 'farmer', 'technician']);
    $input = request_body();
    $id = (int) ($input['id'] ?? 0);
    if (!$id) {
        Response::error('id is required', 422);
    }
    $alert->resolve($id);
    Response::success(null, 'Alert resolved');
} else {
    Response::error('Method not allowed', 405);
}
