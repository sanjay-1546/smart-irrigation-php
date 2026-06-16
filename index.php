<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
echo json_encode([
    'name' => 'Smart Farm Irrigation Backend',
    'status' => 'running',
    'docs' => 'api/docs/index.php',
]);
