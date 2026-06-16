<?php
declare(strict_types=1);

/**
 * Authenticates NodeMCU device requests via a per-device API key (X-API-Key
 * header), rather than a user JWT. Devices are provisioned with a key when
 * registered through /api/devices.
 */
class DeviceAuthMiddleware
{
    public static function authenticate(): array
    {
        $deviceId = $_SERVER['HTTP_X_DEVICE_ID'] ?? ($_GET['device_id'] ?? '');
        $apiKey = $_SERVER['HTTP_X_API_KEY'] ?? '';

        if ($deviceId === '' || $apiKey === '') {
            Response::unauthorized('Missing device credentials');
        }

        $db = Database::connection();
        $stmt = $db->prepare('SELECT * FROM devices WHERE device_id = ? AND is_active = 1 LIMIT 1');
        $stmt->execute([$deviceId]);
        $device = $stmt->fetch();

        if (!$device || !hash_equals($device['api_key'], $apiKey)) {
            Response::unauthorized('Invalid device credentials');
        }

        return $device;
    }
}
