<?php
declare(strict_types=1);

class Command
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::connection();
    }

    public function forDevice(string $deviceId): array
    {
        $stmt = $this->db->prepare('SELECT * FROM commands WHERE device_id = ?');
        $stmt->execute([$deviceId]);
        $row = $stmt->fetch();

        return $row ?: [
            'device_id' => $deviceId,
            'bore_pump' => 'OFF',
            'well_pump' => 'OFF',
            'zone1' => 'OFF',
            'zone2' => 'OFF',
            'zone3' => 'OFF',
            'zone4' => 'OFF',
        ];
    }

    public function setManual(string $deviceId, array $fields): void
    {
        $allowed = ['bore_pump', 'well_pump', 'zone1', 'zone2', 'zone3', 'zone4'];
        $columns = [];
        $values = [];
        foreach ($allowed as $field) {
            if (isset($fields[$field])) {
                $columns[] = "$field = ?";
                $values[] = $fields[$field];
            }
        }
        if (empty($columns)) {
            return;
        }

        $existing = $this->forDevice($deviceId);
        $merged = array_merge($existing, $fields);

        $stmt = $this->db->prepare(
            'INSERT INTO commands (device_id, bore_pump, well_pump, zone1, zone2, zone3, zone4)
             VALUES (?, ?, ?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE ' . implode(', ', $columns)
        );
        $stmt->execute(array_merge([
            $deviceId,
            $merged['bore_pump'],
            $merged['well_pump'],
            $merged['zone1'],
            $merged['zone2'],
            $merged['zone3'],
            $merged['zone4'],
        ], $values));
    }
}
