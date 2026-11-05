<?php

declare(strict_types=1);

namespace DesignLila\Http;

use DesignLila\Support\HttpException;

final class Router
{
    /** @var array<string, array<string, callable>> */
    private array $routes = [];

    public function get(string $path, callable $handler): void
    {
        $this->add('GET', $path, $handler);
    }

    public function add(string $method, string $path, callable $handler): void
    {
        $this->routes[strtoupper($method)][$path] = $handler;
    }

    public function dispatch(string $method, string $path): mixed
    {
        $handler = $this->routes[$method][$path] ?? null;

        if ($handler === null) {
            throw new HttpException('Route not found.', 404);
        }

        return $handler();
    }
}

