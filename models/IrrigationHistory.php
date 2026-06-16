<?php
declare(strict_types=1);

class IrrigationHistory
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::connection();
    }

    public function start(int $farmId, ?int $zoneId, string $waterSource, string $triggeredBy): void
    {
        // Avoid duplicate open sessions for the same farm/zone/source.
        if ($this->hasOpenSession($farmId, $zoneId, $waterSource)) {
            return;
        }
        $stmt = $this->db->prepare(
            'INSERT INTO irrigation_history (farm_id, zone_id, water_source, triggered_by) VALUES (?, ?, ?, ?)'
        );
        $stmt->execute([$farmId, $zoneId, $waterSource, $triggeredBy]);
    }

    public function stop(int $farmId, ?int $zoneId, string $waterSource, ?float $avgFlowRate = null): void
    {
        $stmt = $this->db->prepare(
            'SELECT id, started_at FROM irrigation_history
             WHERE farm_id = ? AND water_source = ? AND ended_at IS NULL
             AND (zone_id = ? OR (zone_id IS NULL AND ? IS NULL))
             ORDER BY started_at DESC LIMIT 1'
        );
        $stmt->execute([$farmId, $waterSource, $zoneId, $zoneId]);
        $row = $stmt->fetch();
        if (!$row) {
            return;
        }

        $waterConsumed = null;
        if ($avgFlowRate !== null) {
            $durationMinutes = (time() - strtotime($row['started_at'])) / 60;
            $waterConsumed = round($avgFlowRate * $durationMinutes, 2);
        }

        $upd = $this->db->prepare(
            'UPDATE irrigation_history SET ended_at = NOW(), water_consumed = ? WHERE id = ?'
        );
        $upd->execute([$waterConsumed, $row['id']]);
    }

    private function hasOpenSession(int $farmId, ?int $zoneId, string $waterSource): bool
    {
        $stmt = $this->db->prepare(
            'SELECT id FROM irrigation_history
             WHERE farm_id = ? AND water_source = ? AND ended_at IS NULL
             AND (zone_id = ? OR (zone_id IS NULL AND ? IS NULL)) LIMIT 1'
        );
        $stmt->execute([$farmId, $waterSource, $zoneId, $zoneId]);
        return (bool) $stmt->fetch();
    }
}
