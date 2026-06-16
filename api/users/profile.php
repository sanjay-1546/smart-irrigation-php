<?php
declare(strict_types=1);

require_once __DIR__ . '/../../bootstrap.php';

/**
 * Self-service profile view/update for the currently authenticated user
 * (any role). Cannot change role or active status - that's admin-only via
 * /api/users/index.php.
 */

$claims = AuthMiddleware::authenticate();
$userId = (int) $claims['sub'];
$user = new User();
$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $row = $user->find($userId);
    $row ? Response::success($row) : Response::notFound('User not found');
} elseif ($method === 'PUT') {
    $input = Validator::sanitizeArray(request_body(), ['name', 'email']);
    $validator = Validator::make($input, [
        'name' => 'required|string',
        'email' => 'required|email',
    ]);
    if ($validator->fails()) {
        Response::error('Validation failed', 422, $validator->errors());
    }

    if ($user->emailExistsForOtherUser($input['email'], $userId)) {
        Response::error('Email already in use by another account', 409);
    }

    $current = $user->find($userId);
    $user->update($userId, [
        'name' => $input['name'],
        'email' => $input['email'],
        'role' => $current['role'],
        'is_active' => $current['is_active'],
    ]);
    Response::success(null, 'Profile updated');
} else {
    Response::error('Method not allowed', 405);
}
