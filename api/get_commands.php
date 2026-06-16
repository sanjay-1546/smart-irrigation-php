<?php
declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    Response::error('Method not allowed', 405);
}

$device = DeviceAuthMiddleware::authenticate();
RateLimitMiddleware::enforce('get_commands:' . $device['device_id']);

(new Device())->updateLastSeen($device['device_id']);

$command = (new Command())->forDevice($device['device_id']);

Response::success([
    'bore_pump' => $command['bore_pump'],
    'well_pump' => $command['well_pump'],
    'zone1' => $command['zone1'],
    'zone2' => $command['zone2'],
    'zone3' => $command['zone3'],
    'zone4' => $command['zone4'],
]);
