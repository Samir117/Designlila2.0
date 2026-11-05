<?php

declare(strict_types=1);

use DesignLila\Config\Env;
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

Env::load(dirname(__DIR__) . '/.env');

$router = new Router();
$router->get('/api/v1/health', static function (): never {
    JsonResponse::success([
        'application' => 'Design Lila API',
        'status' => 'ok',
        'php_version' => PHP_VERSION,
    ]);
});

try {
    $router->dispatch(Request::method(), Request::path());
} catch (HttpException $exception) {
    JsonResponse::error($exception->getMessage(), $exception->status(), $exception->details());
} catch (JsonException|InvalidArgumentException $exception) {
    JsonResponse::error($exception->getMessage(), 422);
} catch (Throwable $exception) {
    error_log((string) $exception);
    $message = Env::boolean('APP_DEBUG') ? $exception->getMessage() : 'Internal server error.';
    JsonResponse::error($message, 500);
}

