<?php
declare(strict_types=1);

require_once __DIR__ . '/../../bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    Response::error('Method not allowed', 405);
}

AuthMiddleware::authenticate();

$farmId = (int) ($_GET['farm_id'] ?? 0);
$type = $_GET['type'] ?? 'daily'; // daily | weekly | monthly
$report = $_GET['report'] ?? 'irrigation_history'; // irrigation_history | water_consumption | pump_runtime

if (!$farmId) {
    Response::error('farm_id is required', 422);
}

$db = Database::connection();

$intervals = ['daily' => '1 DAY', 'weekly' => '7 DAY', 'monthly' => '30 DAY'];
if (!isset($intervals[$type])) {
    Response::error('type must be one of: daily, weekly, monthly', 422);
}
$interval = $intervals[$type];

switch ($report) {
    case 'irrigation_history':
        $stmt = $db->prepare(
            "SELECT * FROM irrigation_history WHERE farm_id = ?
             AND started_at >= (NOW() - INTERVAL $interval) ORDER BY started_at DESC"
        );
        $stmt->execute([$farmId]);
        Response::success($stmt->fetchAll());
        break;

    case 'water_consumption':
        $stmt = $db->prepare(
            "SELECT DATE(started_at) AS day, water_source, SUM(water_consumed) AS total_liters
             FROM irrigation_history WHERE farm_id = ?
             AND started_at >= (NOW() - INTERVAL $interval)
             GROUP BY DATE(started_at), water_source ORDER BY day DESC"
        );
        $stmt->execute([$farmId]);
        Response::success($stmt->fetchAll());
        break;

    case 'pump_runtime':
        $stmt = $db->prepare(
            "SELECT water_source,
                    SUM(action = 'ON') AS on_events,
                    MIN(created_at) AS first_event,
                    MAX(created_at) AS last_event
             FROM pump_runtime_log WHERE farm_id = ?
             AND created_at >= (NOW() - INTERVAL $interval)
             GROUP BY water_source"
        );
        $stmt->execute([$farmId]);
        Response::success($stmt->fetchAll());
        break;

    default:
        Response::error('report must be one of: irrigation_history, water_consumption, pump_runtime', 422);
}
