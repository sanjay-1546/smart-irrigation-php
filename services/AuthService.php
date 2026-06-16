<?php
declare(strict_types=1);

class AuthService
{
    private PDO $db;
    private array $config;

    public function __construct()
    {
        $this->db = Database::connection();
        $this->config = require_once __DIR__ . '/../config/config.php';
    }

    public function attempt(string $email, string $password): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM users WHERE email = ? AND is_active = 1 LIMIT 1');
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if (!$user || !password_verify($password, $user['password_hash'])) {
            return null;
        }

        unset($user['password_hash']);
        return $user;
    }

    public function issueToken(array $user): string
    {
        $now = time();
        $payload = [
            'iss' => $this->config['jwt']['issuer'],
            'sub' => (int) $user['id'],
            'role' => $user['role'],
            'email' => $user['email'],
            'iat' => $now,
            'exp' => $now + $this->config['jwt']['ttl'],
        ];

        if ($this->config['jwt']['secret'] === '') {
            throw new RuntimeException('JWT_SECRET is not configured');
        }

        return JWT::encode($payload, $this->config['jwt']['secret']);
    }

    public function createUser(string $name, string $email, string $password, string $role): int
    {
        $hash = password_hash($password, PASSWORD_BCRYPT);
        $stmt = $this->db->prepare(
            'INSERT INTO users (name, email, password_hash, role) VALUES (?, ?, ?, ?)'
        );
        $stmt->execute([$name, $email, $hash, $role]);
        return (int) $this->db->lastInsertId();
    }

    public function emailExists(string $email): bool
    {
        $stmt = $this->db->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
        $stmt->execute([$email]);
        return (bool) $stmt->fetch();
    }
}
