<?php
declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    Response::error('Method not allowed', 405);
}

$device = DeviceAuthMiddleware::authenticate();
RateLimitMiddleware::enforce('upload_sensor:' . $device['device_id']);

$input = request_body();
$validator = Validator::make($input, [
    'moisture_zone1' => 'numeric',
    'moisture_zone2' => 'numeric',
    'moisture_zone3' => 'numeric',
    'moisture_zone4' => 'numeric',
    'temperature' => 'numeric',
    'humidity' => 'numeric',
    'water_level' => 'numeric',
    'flow_rate' => 'numeric',
]);
if ($validator->fails()) {
    Response::error('Validation failed', 422, $validator->errors());
}

$reading = new SensorReading();
$id = $reading->store($device['device_id'], $device['farm_id'] ? (int) $device['farm_id'] : null, $input);

(new Device())->updateLastSeen($device['device_id'], $input['firmware_version'] ?? null);

// React immediately so the next 10s command poll reflects fresh conditions.
$engine = new AutomationEngine();
$engine->evaluate($device['device_id'], $input);

Response::success(['reading_id' => $id], 'Sensor data recorded', 201);
