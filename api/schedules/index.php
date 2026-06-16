<?php
declare(strict_types=1);

require_once __DIR__ . '/../../bootstrap.php';

AuthMiddleware::authenticate();
$schedule = new Schedule();
$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {
    case 'GET':
        $farmId = (int) ($_GET['farm_id'] ?? 0);
        if (!$farmId) {
            Response::error('farm_id is required', 422);
        }
        Response::success($schedule->allForFarm($farmId));
        break;

    case 'POST':
        AuthMiddleware::requireRole(['admin', 'farmer']);
        $input = request_body();
        $validator = Validator::make($input, [
            'farm_id' => 'required|integer',
            'zone_id' => 'required|integer',
            'water_source' => 'required|in:borewell,open_well',
            'start_time' => 'required|string',
            'end_time' => 'required|string',
        ]);
        if ($validator->fails()) {
            Response::error('Validation failed', 422, $validator->errors());
        }
        $id = $schedule->create($input);
        Response::success(['id' => $id], 'Schedule created', 201);
        break;

    case 'PUT':
        AuthMiddleware::requireRole(['admin', 'farmer']);
        $input = request_body();
        $id = (int) ($input['id'] ?? 0);
        if (!$id || !$schedule->find($id)) {
            Response::notFound('Schedule not found');
        }
        $validator = Validator::make($input, [
            'start_time' => 'required|string',
            'end_time' => 'required|string',
        ]);
        if ($validator->fails()) {
            Response::error('Validation failed', 422, $validator->errors());
        }
        $schedule->update($id, $input);
        Response::success(null, 'Schedule updated');
        break;

    case 'DELETE':
        AuthMiddleware::requireRole(['admin', 'farmer']);
        $id = (int) ($_GET['id'] ?? 0);
        if (!$id || !$schedule->find($id)) {
            Response::notFound('Schedule not found');
        }
        $schedule->delete($id);
        Response::success(null, 'Schedule deleted');
        break;

    default:
        Response::error('Method not allowed', 405);
}
