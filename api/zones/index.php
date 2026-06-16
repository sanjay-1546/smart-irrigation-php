<?php
declare(strict_types=1);

require_once __DIR__ . '/../../bootstrap.php';

AuthMiddleware::authenticate();
$zone = new Zone();
$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {
    case 'GET':
        $farmId = (int) ($_GET['farm_id'] ?? 0);
        if (!$farmId) {
            Response::error('farm_id is required', 422);
        }
        Response::success($zone->allForFarm($farmId));
        break;

    case 'POST':
        AuthMiddleware::requireRole(['admin', 'farmer']);
        $input = Validator::sanitizeArray(request_body(), ['zone_name', 'crop_type']);
        $validator = Validator::make($input, [
            'farm_id' => 'required|integer',
            'zone_number' => 'required|integer|min:1|max:4',
            'zone_name' => 'required|string',
            'moisture_threshold' => 'numeric',
        ]);
        if ($validator->fails()) {
            Response::error('Validation failed', 422, $validator->errors());
        }
        if ($zone->countForFarm((int) $input['farm_id']) >= 4) {
            $existing = array_filter($zone->allForFarm((int) $input['farm_id']), fn($z) => (int) $z['zone_number'] === (int) $input['zone_number']);
            if (empty($existing)) {
                Response::error('A farm supports a maximum of 4 irrigation zones', 422);
            }
        }
        $id = $zone->create($input);
        Response::success(['id' => $id], 'Zone created', 201);
        break;

    case 'PUT':
        AuthMiddleware::requireRole(['admin', 'farmer']);
        $input = Validator::sanitizeArray(request_body(), ['zone_name', 'crop_type']);
        $id = (int) ($input['id'] ?? 0);
        if (!$id || !$zone->find($id)) {
            Response::notFound('Zone not found');
        }
        $validator = Validator::make($input, [
            'zone_name' => 'required|string',
            'moisture_threshold' => 'numeric',
        ]);
        if ($validator->fails()) {
            Response::error('Validation failed', 422, $validator->errors());
        }
        $zone->update($id, $input);
        Response::success(null, 'Zone updated');
        break;

    case 'DELETE':
        AuthMiddleware::requireRole(['admin', 'farmer']);
        $id = (int) ($_GET['id'] ?? 0);
        if (!$id || !$zone->find($id)) {
            Response::notFound('Zone not found');
        }
        $zone->delete($id);
        Response::success(null, 'Zone deleted');
        break;

    default:
        Response::error('Method not allowed', 405);
}
