<?php
declare(strict_types=1);

/**
 * Evaluates automation rules against the latest sensor reading for a device
 * and updates the `commands` table accordingly. Invoked right after a sensor
 * upload (real-time reaction) and may also be invoked from a cron tick to
 * react to stale rain_probability / water_level changes.
 *
 * Rules (from PROJECT_PROMPT.md):
 *   IF moisture < threshold        THEN irrigate that zone
 *   IF rain_probability > 70       THEN skip irrigation
 *   IF water_level < 20%           THEN stop all pumps
 *   IF flow_rate = 0 (while pump ON) THEN generate NO_FLOW alert
 */
class AutomationEngine
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::connection();
    }

    public function evaluate(string $deviceId, array $reading): void
    {
        $device = $this->getDevice($deviceId);
        if (!$device || !$device['farm_id']) {
            return;
        }
        $farmId = (int) $device['farm_id'];

        $previous = (new Command())->forDevice($deviceId);
        $zones = $this->getZones($farmId);
        $rainProbability = $this->getLatestRainProbability($farmId);
        $skipIrrigation = $rainProbability !== null && $rainProbability > 70;

        $waterLevel = $reading['water_level'] ?? null;
        $stopAllPumps = $waterLevel !== null && $waterLevel < 20;

        $desired = [
            'bore_pump' => 'OFF',
            'well_pump' => 'OFF',
            'zone1' => 'OFF',
            'zone2' => 'OFF',
            'zone3' => 'OFF',
            'zone4' => 'OFF',
        ];

        $anyZoneNeedsWater = false;

        foreach ($zones as $zone) {
            $zoneKey = 'zone' . $zone['zone_number'];
            $moistureField = 'moisture_zone' . $zone['zone_number'];
            $moisture = $reading[$moistureField] ?? null;

            if ($moisture !== null && (float) $moisture < (float) $zone['moisture_threshold']) {
                if (!$skipIrrigation && !$stopAllPumps) {
                    $desired[$zoneKey] = 'ON';
                    $anyZoneNeedsWater = true;
                } else {
                    $this->raiseAlert($farmId, $deviceId, 'DRY_SOIL', "Zone {$zone['zone_number']} is dry but irrigation is being skipped");
                }
            }
        }

        if ($anyZoneNeedsWater && !$stopAllPumps) {
            $pumps = $this->getPumps($farmId);
            foreach ($pumps as $pump) {
                if ($pump['water_source'] === 'borewell') {
                    $desired['bore_pump'] = 'ON';
                } elseif ($pump['water_source'] === 'open_well') {
                    $desired['well_pump'] = 'ON';
                }
            }
        }

        if ($stopAllPumps) {
            $desired['bore_pump'] = 'OFF';
            $desired['well_pump'] = 'OFF';
            $this->raiseAlert($farmId, $deviceId, 'LOW_WATER', 'Water level below 20% - all pumps stopped');
        }

        $flowRate = $reading['flow_rate'] ?? null;
        $pumpIsOn = $desired['bore_pump'] === 'ON' || $desired['well_pump'] === 'ON';
        if ($flowRate !== null && (float) $flowRate === 0.0 && $pumpIsOn) {
            $this->raiseAlert($farmId, $deviceId, 'NO_FLOW', 'Pump is on but flow rate is zero');
        }

        $this->recordIrrigationTransitions($farmId, $zones, $previous, $desired, $reading);
        $this->writeCommands($deviceId, $desired);
    }

    private function recordIrrigationTransitions(int $farmId, array $zones, array $previous, array $desired, array $reading): void
    {
        $history = new IrrigationHistory();
        $flowRate = isset($reading['flow_rate']) ? (float) $reading['flow_rate'] : null;

        foreach ($zones as $zone) {
            $zoneKey = 'zone' . $zone['zone_number'];
            $waterSource = $this->primaryWaterSourceForFarm($farmId);
            $wasOn = ($previous[$zoneKey] ?? 'OFF') === 'ON';
            $isOn = $desired[$zoneKey] === 'ON';

            if (!$wasOn && $isOn) {
                $history->start($farmId, (int) $zone['id'], $waterSource, 'automation');
            } elseif ($wasOn && !$isOn) {
                $history->stop($farmId, (int) $zone['id'], $waterSource, $flowRate);
            }
        }
    }

    private function primaryWaterSourceForFarm(int $farmId): string
    {
        $pumps = $this->getPumps($farmId);
        foreach ($pumps as $pump) {
            return $pump['water_source'];
        }
        return 'borewell';
    }

    private function getDevice(string $deviceId): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM devices WHERE device_id = ? LIMIT 1');
        $stmt->execute([$deviceId]);
        return $stmt->fetch() ?: null;
    }

    private function getZones(int $farmId): array
    {
        $stmt = $this->db->prepare('SELECT * FROM zones WHERE farm_id = ? AND is_active = 1');
        $stmt->execute([$farmId]);
        return $stmt->fetchAll();
    }

    private function getPumps(int $farmId): array
    {
        $stmt = $this->db->prepare('SELECT * FROM pumps WHERE farm_id = ?');
        $stmt->execute([$farmId]);
        return $stmt->fetchAll();
    }

    private function getLatestRainProbability(int $farmId): ?float
    {
        $stmt = $this->db->prepare(
            'SELECT rain_probability FROM weather_data WHERE farm_id = ? ORDER BY fetched_at DESC LIMIT 1'
        );
        $stmt->execute([$farmId]);
        $row = $stmt->fetch();
        return $row ? (float) $row['rain_probability'] : null;
    }

    private function writeCommands(string $deviceId, array $desired): void
    {
        $stmt = $this->db->prepare(
            'INSERT INTO commands (device_id, bore_pump, well_pump, zone1, zone2, zone3, zone4)
             VALUES (?, ?, ?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE
                bore_pump = VALUES(bore_pump), well_pump = VALUES(well_pump),
                zone1 = VALUES(zone1), zone2 = VALUES(zone2),
                zone3 = VALUES(zone3), zone4 = VALUES(zone4)'
        );
        $stmt->execute([
            $deviceId,
            $desired['bore_pump'],
            $desired['well_pump'],
            $desired['zone1'],
            $desired['zone2'],
            $desired['zone3'],
            $desired['zone4'],
        ]);
    }

    private function raiseAlert(int $farmId, string $deviceId, string $type, string $message): void
    {
        // Avoid duplicate spam: skip if an unresolved alert of the same type
        // for this farm was already raised in the last hour.
        $check = $this->db->prepare(
            "SELECT id FROM alerts WHERE farm_id = ? AND alert_type = ? AND is_resolved = 0
             AND created_at > (NOW() - INTERVAL 1 HOUR) LIMIT 1"
        );
        $check->execute([$farmId, $type]);
        if ($check->fetch()) {
            return;
        }

        $stmt = $this->db->prepare(
            'INSERT INTO alerts (farm_id, device_id, alert_type, message) VALUES (?, ?, ?, ?)'
        );
        $stmt->execute([$farmId, $deviceId, $type, $message]);
    }
}
