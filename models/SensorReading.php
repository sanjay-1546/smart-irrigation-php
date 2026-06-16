<?php
declare(strict_types=1);

class SensorReading
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::connection();
    }

    public function store(string $deviceId, ?int $farmId, array $data): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO sensor_readings
                (device_id, farm_id, moisture_zone1, moisture_zone2, moisture_zone3, moisture_zone4,
                 temperature, humidity, water_level, flow_rate)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $deviceId,
            $farmId,
            $data['moisture_zone1'] ?? null,
            $data['moisture_zone2'] ?? null,
            $data['moisture_zone3'] ?? null,
            $data['moisture_zone4'] ?? null,
            $data['temperature'] ?? null,
            $data['humidity'] ?? null,
            $data['water_level'] ?? null,
            $data['flow_rate'] ?? null,
        ]);
        return (int) $this->db->lastInsertId();
    }

    public function latestForDevice(string $deviceId): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT * FROM sensor_readings WHERE device_id = ? ORDER BY created_at DESC LIMIT 1'
        );
        $stmt->execute([$deviceId]);
        return $stmt->fetch() ?: null;
    }

    public function rangeForFarm(int $farmId, string $from, string $to): array
    {
        $stmt = $this->db->prepare(
            'SELECT * FROM sensor_readings WHERE farm_id = ? AND created_at BETWEEN ? AND ? ORDER BY created_at ASC'
        );
        $stmt->execute([$farmId, $from, $to]);
        return $stmt->fetchAll();
    }
}
