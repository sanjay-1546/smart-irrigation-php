<?php
declare(strict_types=1);

require_once __DIR__ . '/../../bootstrap.php';

AuthMiddleware::authenticate();
$pump = new Pump();
$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {
    case 'GET':
        $farmId = (int) ($_GET['farm_id'] ?? 0);
        if (!$farmId) {
            Response::error('farm_id is required', 422);
        }
        Response::success($pump->allForFarm($farmId));
        break;

    case 'POST':
        // Register/configure a water source for a farm (idempotent upsert).
        AuthMiddleware::requireRole(['admin', 'farmer', 'technician']);
        $input = Validator::sanitizeArray(request_body(), ['pump_name']);
        $validator = Validator::make($input, [
            'farm_id' => 'required|integer',
            'water_source' => 'required|in:borewell,open_well',
        ]);
        if ($validator->fails()) {
            Response::error('Validation failed', 422, $validator->errors());
        }
        $id = $pump->upsert((int) $input['farm_id'], $input['water_source'], $input['pump_name'] ?? null);
        Response::success(['id' => $id], 'Pump configured', 201);
        break;

    default:
        Response::error('Method not allowed', 405);
}
