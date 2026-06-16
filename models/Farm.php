<?php
declare(strict_types=1);

class Farm
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::connection();
    }

    public function all(): array
    {
        return $this->db->query('SELECT * FROM farms ORDER BY id DESC')->fetchAll();
    }

    public function find(int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM farms WHERE id = ?');
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    public function create(array $data): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO farms (farm_name, location, owner_name, user_id) VALUES (?, ?, ?, ?)'
        );
        $stmt->execute([$data['farm_name'], $data['location'] ?? null, $data['owner_name'] ?? null, $data['user_id'] ?? null]);
        return (int) $this->db->lastInsertId();
    }

    public function update(int $id, array $data): bool
    {
        $stmt = $this->db->prepare(
            'UPDATE farms SET farm_name = ?, location = ?, owner_name = ? WHERE id = ?'
        );
        return $stmt->execute([$data['farm_name'], $data['location'] ?? null, $data['owner_name'] ?? null, $id]);
    }

    public function delete(int $id): bool
    {
        $stmt = $this->db->prepare('DELETE FROM farms WHERE id = ?');
        return $stmt->execute([$id]);
    }
}
