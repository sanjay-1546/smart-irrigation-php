<?php
declare(strict_types=1);

require_once __DIR__ . '/../../bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    Response::error('Method not allowed', 405);
}

AuthMiddleware::requireRole(['admin', 'farmer', 'technician']);

$input = request_body();
$validator = Validator::make($input, [
    'farm_id' => 'required|integer',
    'water_source' => 'required|in:borewell,open_well',
    'status' => 'required|in:ON,OFF',
]);
if ($validator->fails()) {
    Response::error('Validation failed', 422, $validator->errors());
}

$pump = new Pump();
$pump->setStatus((int) $input['farm_id'], $input['water_source'], $input['status'], 'manual');

$history = new IrrigationHistory();
if ($input['status'] === 'ON') {
    $history->start((int) $input['farm_id'], null, $input['water_source'], 'manual');
} else {
    $history->stop((int) $input['farm_id'], null, $input['water_source']);
}

// Mirror the manual pump command to every device registered on this farm so
// the NodeMCU picks it up on its next 10s poll.
$db = Database::connection();
$stmt = $db->prepare('SELECT device_id FROM devices WHERE farm_id = ? AND is_active = 1');
$stmt->execute([(int) $input['farm_id']]);
$field = $input['water_source'] === 'borewell' ? 'bore_pump' : 'well_pump';

$command = new Command();
foreach ($stmt->fetchAll() as $row) {
    $command->setManual($row['device_id'], [$field => $input['status']]);
}

Response::success(null, 'Pump status updated');
