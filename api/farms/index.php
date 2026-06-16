<?php
declare(strict_types=1);

require_once __DIR__ . '/../../bootstrap.php';

$user = AuthMiddleware::authenticate();
$farm = new Farm();
$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {
    case 'GET':
        $id = $_GET['id'] ?? null;
        if ($id !== null) {
            $row = $farm->find((int) $id);
            $row ? Response::success($row) : Response::notFound('Farm not found');
        }
        Response::success($farm->all());
        break;

    case 'POST':
        AuthMiddleware::requireRole(['admin', 'farmer']);
        $input = Validator::sanitizeArray(request_body(), ['farm_name', 'location', 'owner_name']);
        $validator = Validator::make($input, [
            'farm_name' => 'required|string',
            'location' => 'string',
            'owner_name' => 'string',
        ]);
        if ($validator->fails()) {
            Response::error('Validation failed', 422, $validator->errors());
        }
        $input['user_id'] = (int) $user['sub'];
        $id = $farm->create($input);
        Response::success(['id' => $id], 'Farm created', 201);
        break;

    case 'PUT':
        AuthMiddleware::requireRole(['admin', 'farmer']);
        $input = Validator::sanitizeArray(request_body(), ['farm_name', 'location', 'owner_name']);
        $id = (int) ($input['id'] ?? 0);
        if (!$id || !$farm->find($id)) {
            Response::notFound('Farm not found');
        }
        $validator = Validator::make($input, ['farm_name' => 'required|string']);
        if ($validator->fails()) {
            Response::error('Validation failed', 422, $validator->errors());
        }
        $farm->update($id, $input);
        Response::success(null, 'Farm updated');
        break;

    case 'DELETE':
        AuthMiddleware::requireRole(['admin']);
        $id = (int) ($_GET['id'] ?? 0);
        if (!$id || !$farm->find($id)) {
            Response::notFound('Farm not found');
        }
        $farm->delete($id);
        Response::success(null, 'Farm deleted');
        break;

    default:
        Response::error('Method not allowed', 405);
}
