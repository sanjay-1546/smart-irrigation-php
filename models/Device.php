<?php
declare(strict_types=1);

class Device
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::connection();
    }

    public function all(): array
    {
        return $this->db->query('SELECT id, device_id, farm_id, farm_name, firmware_version, last_seen, is_active, created_at FROM devices ORDER BY id DESC')->fetchAll();
    }

    public function find(int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM devices WHERE id = ?');
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    public function findByDeviceId(string $deviceId): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM devices WHERE device_id = ?');
        $stmt->execute([$deviceId]);
        return $stmt->fetch() ?: null;
    }

    public function create(array $data): array
    {
        $apiKey = bin2hex(random_bytes(32));
        $stmt = $this->db->prepare(
            'INSERT INTO devices (device_id, farm_id, farm_name, firmware_version, api_key) VALUES (?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $data['device_id'],
            $data['farm_id'] ?? null,
            $data['farm_name'] ?? null,
            $data['firmware_version'] ?? null,
            $apiKey,
        ]);
        return ['id' => (int) $this->db->lastInsertId(), 'api_key' => $apiKey];
    }

    public function updateLastSeen(string $deviceId, ?string $firmwareVersion = null): void
    {
        $stmt = $this->db->prepare(
            'UPDATE devices SET last_seen = NOW(), firmware_version = COALESCE(?, firmware_version) WHERE device_id = ?'
        );
        $stmt->execute([$firmwareVersion, $deviceId]);
    }

    public function delete(int $id): bool
    {
        $stmt = $this->db->prepare('DELETE FROM devices WHERE id = ?');
        return $stmt->execute([$id]);
    }
}
