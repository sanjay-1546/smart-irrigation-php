<?php
declare(strict_types=1);

require_once __DIR__ . '/../../bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    Response::error('Method not allowed', 405);
}

RateLimitMiddleware::enforce('auth_login');

$input = request_body();
$validator = Validator::make($input, [
    'email' => 'required|email',
    'password' => 'required|string',
]);

if ($validator->fails()) {
    Response::error('Validation failed', 422, $validator->errors());
}

$auth = new AuthService();
$user = $auth->attempt($input['email'], $input['password']);

if (!$user) {
    Logger::warning('Failed login attempt for ' . $input['email'] . ' from ' . client_ip());
    Response::unauthorized('Invalid email or password');
}

$token = $auth->issueToken($user);

Response::success([
    'token' => $token,
    'token_type' => 'Bearer',
    'expires_in' => app_config()['jwt']['ttl'],
    'user' => $user,
], 'Login successful');
