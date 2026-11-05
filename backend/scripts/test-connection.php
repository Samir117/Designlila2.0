<?php

declare(strict_types=1);

require dirname(__DIR__) . '/src/Config/Env.php';
require dirname(__DIR__) . '/src/Database/Connection.php';

use DesignLila\Config\Env;
use DesignLila\Database\Connection;

Env::load(dirname(__DIR__) . '/.env');

try {
    $connection = Connection::get();
    $result = $connection->query('SELECT 1 AS connection_test')->fetch();

    if (($result['connection_test'] ?? null) !== 1 && ($result['connection_test'] ?? null) !== '1') {
        throw new RuntimeException('The database test returned an unexpected result.');
    }

    echo "CONNECTION_OK\n";
    echo 'DRIVER=' . $connection->getAttribute(\PDO::ATTR_DRIVER_NAME) . "\n";
    echo "TEST=SELECT 1 OK\n";
} catch (Throwable $exception) {
    fwrite(STDERR, 'CONNECTION_ERROR: ' . $exception->getMessage() . PHP_EOL);
    exit(1);
}

