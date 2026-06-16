<?php
declare(strict_types=1);

class Alert
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::connection();
    }

    public function allForFarm(int $farmId, bool $unresolvedOnly = false): array
    {
        $sql = 'SELECT * FROM alerts WHERE farm_id = ?';
        if ($unresolvedOnly) {
            $sql .= ' AND is_resolved = 0';
        }
        $sql .= ' ORDER BY created_at DESC';
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$farmId]);
        return $stmt->fetchAll();
    }

    public function resolve(int $id): bool
    {
        $stmt = $this->db->prepare('UPDATE alerts SET is_resolved = 1, resolved_at = NOW() WHERE id = ?');
        return $stmt->execute([$id]);
    }
}
