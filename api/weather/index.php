<?php
declare(strict_types=1);

require_once __DIR__ . '/../../bootstrap.php';

AuthMiddleware::authenticate();
$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $farmId = (int) ($_GET['farm_id'] ?? 0);
    if (!$farmId) {
        Response::error('farm_id is required', 422);
    }
    $weather = (new WeatherService())->latestForFarm($farmId);
    $weather ? Response::success($weather) : Response::notFound('No weather data yet for this farm');
} elseif ($method === 'POST') {
    // Manually trigger a refresh (the cron tick also does this periodically).
    AuthMiddleware::requireRole(['admin', 'farmer']);
    $input = request_body();
    $validator = Validator::make($input, [
        'farm_id' => 'required|integer',
        'location' => 'required|string',
    ]);
    if ($validator->fails()) {
        Response::error('Validation failed', 422, $validator->errors());
    }
    $result = (new WeatherService())->fetchAndStore((int) $input['farm_id'], $input['location']);
    $result ? Response::success($result, 'Weather updated') : Response::serverError('Failed to fetch weather data');
} else {
    Response::error('Method not allowed', 405);
}
