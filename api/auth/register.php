<?php
declare(strict_types=1);

require_once __DIR__ . '/../../bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    Response::error('Method not allowed', 405);
}

// Only an existing admin can create new accounts.
AuthMiddleware::requireRole(['admin']);
RateLimitMiddleware::enforce('auth_register');

$input = Validator::sanitizeArray(request_body(), ['name', 'email', 'role']);
$validator = Validator::make($input, [
    'name' => 'required|string',
    'email' => 'required|email',
    'password' => 'required|string',
    'role' => 'required|in:admin,farmer,technician',
]);

if ($validator->fails()) {
    Response::error('Validation failed', 422, $validator->errors());
}

$auth = new AuthService();
if ($auth->emailExists($input['email'])) {
    Response::error('Email already registered', 409);
}

if (strlen($input['password']) < 8) {
    Response::error('Password must be at least 8 characters', 422);
}

$id = $auth->createUser($input['name'], $input['email'], $input['password'], $input['role']);

Response::success(['id' => $id], 'User created successfully', 201);
