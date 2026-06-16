<?php
declare(strict_types=1);

require_once __DIR__ . '/../../bootstrap.php';

/**
 * Admin-only user management: list, view, update (name/email/role/active),
 * delete. Account creation stays in /api/auth/register.php (admin-only,
 * already exists). Self-service profile/password changes live in
 * profile.php and change_password.php.
 */

$claims = AuthMiddleware::requireRole(['admin']);
$user = new User();
$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {
    case 'GET':
        $id = $_GET['id'] ?? null;
        if ($id !== null) {
            $row = $user->find((int) $id);
            $row ? Response::success($row) : Response::notFound('User not found');
        }
        Response::success($user->all());
        break;

    case 'PUT':
        $input = Validator::sanitizeArray(request_body(), ['name', 'email']);
        $id = (int) ($input['id'] ?? 0);
        if (!$id || !$user->find($id)) {
            Response::notFound('User not found');
        }

        $validator = Validator::make($input, [
            'name' => 'required|string',
            'email' => 'required|email',
            'role' => 'required|in:admin,farmer,technician',
        ]);
        if ($validator->fails()) {
            Response::error('Validation failed', 422, $validator->errors());
        }

        if ($user->emailExistsForOtherUser($input['email'], $id)) {
            Response::error('Email already in use by another account', 409);
        }

        // An admin can't demote/deactivate their own only-admin account by
        // accident and lock themselves out silently is acceptable risk here;
        // the harder rule worth enforcing is not letting the last admin be
        // deactivated or demoted.
        if ($input['role'] !== 'admin' || (int) ($input['is_active'] ?? 1) === 0) {
            $db = Database::connection();
            $row = $db->query("SELECT COUNT(*) AS c FROM users WHERE role = 'admin' AND is_active = 1")->fetch();
            $currentlyAdmin = $db->prepare('SELECT role, is_active FROM users WHERE id = ?');
            $currentlyAdmin->execute([$id]);
            $current = $currentlyAdmin->fetch();
            $isLastActiveAdmin = (int) $row['c'] <= 1 && $current['role'] === 'admin' && (int) $current['is_active'] === 1;
            if ($isLastActiveAdmin) {
                Response::error('Cannot demote or deactivate the last active admin account', 422);
            }
        }

        $user->update($id, $input);
        Response::success(null, 'User updated');
        break;

    case 'DELETE':
        $id = (int) ($_GET['id'] ?? 0);
        if (!$id || !$user->find($id)) {
            Response::notFound('User not found');
        }
        if ($id === (int) $claims['sub']) {
            Response::error('You cannot delete your own account', 422);
        }
        $user->delete($id);
        Response::success(null, 'User deleted');
        break;

    default:
        Response::error('Method not allowed', 405);
}
