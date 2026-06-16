<?php
declare(strict_types=1);

require_once __DIR__ . '/../../bootstrap.php';

AuthMiddleware::authenticate();
$device = new Device();
$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {
    case 'GET':
        $id = $_GET['id'] ?? null;
        if ($id !== null) {
            $row = $device->find((int) $id);
            $row ? Response::success($row) : Response::notFound('Device not found');
        }
        Response::success($device->all());
        break;

    case 'POST':
        AuthMiddleware::requireRole(['admin', 'technician']);
        $input = Validator::sanitizeArray(request_body(), ['device_id', 'farm_name', 'firmware_version']);
        $validator = Validator::make($input, [
            'device_id' => 'required|string',
            'farm_id' => 'integer',
            'firmware_version' => 'string',
        ]);
        if ($validator->fails()) {
            Response::error('Validation failed', 422, $validator->errors());
        }
        if ($device->findByDeviceId($input['device_id'])) {
            Response::error('Device already registered', 409);
        }
        $result = $device->create($input);
        // api_key is only ever returned once, at registration time.
        Response::success($result, 'Device registered. Store the api_key securely - it will not be shown again.', 201);
        break;

    case 'DELETE':
        AuthMiddleware::requireRole(['admin']);
        $id = (int) ($_GET['id'] ?? 0);
        if (!$id || !$device->find($id)) {
            Response::notFound('Device not found');
        }
        $device->delete($id);
        Response::success(null, 'Device deleted');
        break;

    default:
        Response::error('Method not allowed', 405);
}
