<?php
declare(strict_types=1);

class Zone
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::connection();
    }

    public function allForFarm(int $farmId): array
    {
        $stmt = $this->db->prepare('SELECT * FROM zones WHERE farm_id = ? ORDER BY zone_number ASC');
        $stmt->execute([$farmId]);
        return $stmt->fetchAll();
    }

    public function find(int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM zones WHERE id = ?');
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    public function create(array $data): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO zones (farm_id, zone_number, zone_name, moisture_threshold, crop_type) VALUES (?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $data['farm_id'],
            $data['zone_number'],
            $data['zone_name'],
            $data['moisture_threshold'] ?? 30.0,
            $data['crop_type'] ?? null,
        ]);
        return (int) $this->db->lastInsertId();
    }

    public function update(int $id, array $data): bool
    {
        $stmt = $this->db->prepare(
            'UPDATE zones SET zone_name = ?, moisture_threshold = ?, crop_type = ?, is_active = ? WHERE id = ?'
        );
        return $stmt->execute([
            $data['zone_name'],
            $data['moisture_threshold'],
            $data['crop_type'] ?? null,
            $data['is_active'] ?? 1,
            $id,
        ]);
    }

    public function delete(int $id): bool
    {
        $stmt = $this->db->prepare('DELETE FROM zones WHERE id = ?');
        return $stmt->execute([$id]);
    }

    public function countForFarm(int $farmId): int
    {
        $stmt = $this->db->prepare('SELECT COUNT(*) AS c FROM zones WHERE farm_id = ?');
        $stmt->execute([$farmId]);
        return (int) $stmt->fetch()['c'];
    }
}
