<?php

declare(strict_types=1);

use DesignLila\Config\Env;
use DesignLila\Database\Connection;
use DesignLila\Http\ApiRoutes;
use DesignLila\Http\JsonResponse;
use DesignLila\Http\Request;
use DesignLila\Http\Router;
use DesignLila\Support\HttpException;

$autoload = dirname(__DIR__) . '/vendor/autoload.php';

if (is_file($autoload)) {
    require $autoload;
} else {
    spl_autoload_register(static function (string $class): void {
        $prefix = 'DesignLila\\';

        if (!str_starts_with($class, $prefix)) {
            return;
        }

        $file = dirname(__DIR__) . '/src/' . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';

        if (is_file($file)) {
            require $file;
        }
    });
}

try {
    Env::load(dirname(__DIR__) . '/.env');

    $router = new Router();
    $router->get('/api/v1/health', static function (): never {
        JsonResponse::success([
            'application' => 'Design Lila API',
            'status' => 'ok',
            'php_version' => PHP_VERSION,
        ]);
    });
    $router->get('/api/v1/health/database', static function (): never {
        try {
            $result = Connection::get()->query('SELECT 1')->fetchColumn();

            if ($result !== 1 && $result !== '1') {
                throw new RuntimeException('Unexpected database health check result.');
            }

            JsonResponse::success([
                'database' => 'connected',
                'test' => 'SELECT 1',
            ]);
        } catch (Throwable $exception) {
            error_log('[database health check] ' . $exception);
            JsonResponse::error('Database connection failed.', 503);
        }
    });
    ApiRoutes::register($router);
    $router->dispatch(Request::method(), Request::path());
} catch (HttpException $exception) {
    JsonResponse::error($exception->getMessage(), $exception->status(), $exception->details());
} catch (JsonException|InvalidArgumentException $exception) {
    JsonResponse::error($exception->getMessage(), 422);
} catch (PDOException $exception) {
    error_log((string) $exception);
    if ((string) $exception->getCode() === '23000') {
        JsonResponse::error('The requested change conflicts with existing data.', 409);
    }
    JsonResponse::error('Database operation failed.', 500);
} catch (Throwable $exception) {
    error_log((string) $exception);
    JsonResponse::error('Internal server error.', 500);
}
