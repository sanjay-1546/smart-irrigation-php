<?php
declare(strict_types=1);

require_once __DIR__ . '/../../bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    Response::error('Method not allowed', 405);
}

$claims = AuthMiddleware::authenticate();
$userId = (int) $claims['sub'];

RateLimitMiddleware::enforce('change_password:' . $userId);

$input = request_body();
$validator = Validator::make($input, [
    'current_password' => 'required|string',
    'new_password' => 'required|string',
]);
if ($validator->fails()) {
    Response::error('Validation failed', 422, $validator->errors());
}

if (strlen($input['new_password']) < 8) {
    Response::error('New password must be at least 8 characters', 422);
}

$user = new User();
if (!$user->verifyPassword($userId, $input['current_password'])) {
    Response::unauthorized('Current password is incorrect');
}

$user->updatePassword($userId, $input['new_password']);

Response::success(null, 'Password changed successfully');
