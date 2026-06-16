<?php
declare(strict_types=1);

class Pump
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::connection();
    }

    public function allForFarm(int $farmId): array
    {
        $stmt = $this->db->prepare('SELECT * FROM pumps WHERE farm_id = ?');
        $stmt->execute([$farmId]);
        return $stmt->fetchAll();
    }

    public function find(int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM pumps WHERE id = ?');
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    public function upsert(int $farmId, string $waterSource, ?string $pumpName): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO pumps (farm_id, water_source, pump_name) VALUES (?, ?, ?)
             ON DUPLICATE KEY UPDATE pump_name = VALUES(pump_name)'
        );
        $stmt->execute([$farmId, $waterSource, $pumpName]);

        $find = $this->db->prepare('SELECT id FROM pumps WHERE farm_id = ? AND water_source = ?');
        $find->execute([$farmId, $waterSource]);
        return (int) $find->fetch()['id'];
    }

    public function setStatus(int $farmId, string $waterSource, string $status, string $triggeredBy = 'manual'): bool
    {
        $stmt = $this->db->prepare(
            'UPDATE pumps SET status = ? WHERE farm_id = ? AND water_source = ?'
        );
        $ok = $stmt->execute([$status, $farmId, $waterSource]);

        $log = $this->db->prepare(
            'INSERT INTO pump_runtime_log (farm_id, water_source, action, triggered_by) VALUES (?, ?, ?, ?)'
        );
        $log->execute([$farmId, $waterSource, $status, $triggeredBy]);

        return $ok;
    }
}
