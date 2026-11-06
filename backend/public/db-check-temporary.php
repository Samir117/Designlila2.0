<?php

declare(strict_types=1);

require dirname(__DIR__) . '/src/Config/Env.php';
require dirname(__DIR__) . '/src/Database/Connection.php';

use DesignLila\Config\Env;
use DesignLila\Database\Connection;

Env::load(dirname(__DIR__) . '/.env');

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Robots-Tag: noindex, nofollow');

$respond = static function (int $status, array $body): never {
    http_response_code($status);
    echo json_encode($body, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    exit;
};

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    header('Allow: POST');
    $respond(405, ['success' => false, 'error' => 'Method not allowed.']);
}

$payload = json_decode(file_get_contents('php://input') ?: '', true);
$configuredToken = Env::get('DB_TEST_TOKEN');
$providedToken = is_array($payload) ? ($payload['token'] ?? null) : null;

if (
    !is_string($configuredToken)
    || strlen($configuredToken) < 32
    || !is_string($providedToken)
    || !hash_equals($configuredToken, $providedToken)
) {
    $respond(404, ['success' => false, 'error' => 'Not found.']);
}

try {
    $connection = Connection::get();
    $result = $connection->query('SELECT 1')->fetchColumn();

    if ($result !== 1 && $result !== '1') {
        throw new RuntimeException('Unexpected database test result.');
    }

    $respond(200, ['success' => true, 'data' => ['database' => 'connected', 'test' => 'SELECT 1']]);
} catch (Throwable $exception) {
    error_log('[temporary database check] ' . $exception->getMessage());
    $respond(503, ['success' => false, 'error' => 'Database connection failed.']);
}
