<?php

declare(strict_types=1);

namespace DesignLila\Database;

use DesignLila\Config\Env;
use PDO;

final class Connection
{
    private static ?PDO $instance = null;

    public static function get(): PDO
    {
        if (self::$instance instanceof PDO) {
            return self::$instance;
        }

        $dsn = sprintf(
            'mysql:host=%s;port=%s;dbname=%s;charset=%s',
            Env::required('DB_HOST'),
            Env::get('DB_PORT', '3306'),
            Env::required('DB_NAME'),
            Env::get('DB_CHARSET', 'utf8mb4'),
        );

        self::$instance = new PDO($dsn, Env::required('DB_USER'), Env::required('DB_PASSWORD'), [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);

        return self::$instance;
    }
}

