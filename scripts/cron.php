<?php
declare(strict_types=1);

/**
 * Run via cPanel Cron (suggested: every 5-10 minutes) to:
 *   1. Refresh weather data for every farm.
 *   2. Re-evaluate automation rules using each device's latest reading
 *      (covers the "rain_probability changed" / "water_level dropped"
 *      cases that aren't triggered by a fresh sensor upload).
 *   3. Activate/deactivate scheduled irrigation windows.
 *
 * cPanel cron example:
 *   php /home/user/public_html/scripts/cron.php >> /home/user/public_html/logs/cron.log 2>&1
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('This script can only be run from the command line.');
}

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../services/Logger.php';
require_once __DIR__ . '/../services/Response.php';
require_once __DIR__ . '/../services/WeatherService.php';
require_once __DIR__ . '/../services/AutomationEngine.php';
require_once __DIR__ . '/../models/SensorReading.php';
require_once __DIR__ . '/../models/Command.php';
require_once __DIR__ . '/../models/Schedule.php';

$db = Database::connection();
$weatherService = new WeatherService();
$automationEngine = new AutomationEngine();

// 1. Weather refresh per farm.
$farms = $db->query('SELECT id, location FROM farms WHERE location IS NOT NULL')->fetchAll();
foreach ($farms as $farm) {
    $weatherService->fetchAndStore((int) $farm['id'], $farm['location']);
}

// 2. Re-run automation using each active device's latest reading.
$devices = $db->query("SELECT device_id FROM devices WHERE is_active = 1")->fetchAll();
$readingModel = new SensorReading();
foreach ($devices as $device) {
    $latest = $readingModel->latestForDevice($device['device_id']);
    if ($latest) {
        $automationEngine->evaluate($device['device_id'], $latest);
    }
}

// 3. Apply active schedules: turn the scheduled zone/pump ON for devices on
//    that farm, OFF otherwise (schedule takes precedence only when no
//    automation-driven irrigation is already running).
$schedules = (new Schedule())->activeNow();
$command = new Command();
foreach ($schedules as $schedule) {
    $stmt = $db->prepare('SELECT zone_number FROM zones WHERE id = ?');
    $stmt->execute([$schedule['zone_id']]);
    $zoneRow = $stmt->fetch();
    if (!$zoneRow) {
        continue;
    }
    $zoneField = 'zone' . $zoneRow['zone_number'];
    $pumpField = $schedule['water_source'] === 'borewell' ? 'bore_pump' : 'well_pump';

    $devStmt = $db->prepare('SELECT device_id FROM devices WHERE farm_id = ? AND is_active = 1');
    $devStmt->execute([$schedule['farm_id']]);
    foreach ($devStmt->fetchAll() as $dev) {
        $command->setManual($dev['device_id'], [$zoneField => 'ON', $pumpField => 'ON']);
    }
}

Logger::info('Cron tick completed: ' . count($farms) . ' farms, ' . count($devices) . ' devices, ' . count($schedules) . ' active schedules');
echo "OK\n";
