<?php
declare(strict_types=1);

class Schedule
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::connection();
    }

    public function allForFarm(int $farmId): array
    {
        $stmt = $this->db->prepare('SELECT * FROM schedules WHERE farm_id = ? ORDER BY start_time ASC');
        $stmt->execute([$farmId]);
        return $stmt->fetchAll();
    }

    public function find(int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM schedules WHERE id = ?');
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    public function create(array $data): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO schedules (farm_id, zone_id, water_source, start_time, end_time, days_of_week)
             VALUES (?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $data['farm_id'],
            $data['zone_id'],
            $data['water_source'],
            $data['start_time'],
            $data['end_time'],
            $data['days_of_week'] ?? 'MON,TUE,WED,THU,FRI,SAT,SUN',
        ]);
        return (int) $this->db->lastInsertId();
    }

    public function update(int $id, array $data): bool
    {
        $stmt = $this->db->prepare(
            'UPDATE schedules SET start_time = ?, end_time = ?, days_of_week = ?, is_active = ? WHERE id = ?'
        );
        return $stmt->execute([
            $data['start_time'],
            $data['end_time'],
            $data['days_of_week'] ?? 'MON,TUE,WED,THU,FRI,SAT,SUN',
            $data['is_active'] ?? 1,
            $id,
        ]);
    }

    public function delete(int $id): bool
    {
        $stmt = $this->db->prepare('DELETE FROM schedules WHERE id = ?');
        return $stmt->execute([$id]);
    }

    /** Active schedules whose window covers the current time, for the cron tick. */
    public function activeNow(): array
    {
        $day = strtoupper(date('D'));
        $time = date('H:i:s');
        $stmt = $this->db->prepare(
            "SELECT * FROM schedules WHERE is_active = 1 AND start_time <= ? AND end_time >= ?
             AND FIND_IN_SET(?, REPLACE(days_of_week, ' ', '')) > 0"
        );
        $stmt->execute([$time, $time, $day]);
        return $stmt->fetchAll();
    }
}
