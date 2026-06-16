<?php
declare(strict_types=1);

/**
 * CLI helper to create the first admin user with a properly generated
 * bcrypt hash (avoids ever committing a real password hash to the SQL dump).
 *
 * Usage: php scripts/create_admin.php "Admin Name" admin@example.com
 * It will prompt for a password (hidden if the terminal supports it).
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('This script can only be run from the command line.');
}

require_once __DIR__ . '/../config/autoload.php';

$name = $argv[1] ?? null;
$email = $argv[2] ?? null;

if (!$name || !$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    fwrite(STDERR, "Usage: php create_admin.php \"Full Name\" email@example.com\n");
    exit(1);
}

fwrite(STDOUT, "Password: ");
system('stty -echo');
$password = trim((string) fgets(STDIN));
system('stty echo');
fwrite(STDOUT, "\n");

if (strlen($password) < 8) {
    fwrite(STDERR, "Password must be at least 8 characters.\n");
    exit(1);
}

$db = Database::connection();
$stmt = $db->prepare('SELECT id FROM users WHERE email = ?');
$stmt->execute([$email]);
if ($stmt->fetch()) {
    fwrite(STDERR, "A user with that email already exists.\n");
    exit(1);
}

$hash = password_hash($password, PASSWORD_BCRYPT);
$ins = $db->prepare('INSERT INTO users (name, email, password_hash, role) VALUES (?, ?, ?, ?)');
$ins->execute([$name, $email, $hash, 'admin']);

fwrite(STDOUT, "Admin user created with id " . $db->lastInsertId() . "\n");
