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

    public function post(string $path, callable $handler): void
    {
        $this->add('POST', $path, $handler);
    }

    public function patch(string $path, callable $handler): void
    {
        $this->add('PATCH', $path, $handler);
    }

    public function put(string $path, callable $handler): void
    {
        $this->add('PUT', $path, $handler);
    }

    public function delete(string $path, callable $handler): void
    {
        $this->add('DELETE', $path, $handler);
    }

    public function add(string $method, string $path, callable $handler): void
    {
        $this->routes[strtoupper($method)][$path] = $handler;
    }

    public function dispatch(string $method, string $path): mixed
    {
        $allowedMethods = [];

        foreach ($this->routes as $routeMethod => $routes) {
            foreach ($routes as $routePath => $handler) {
                $parameterNames = [];
                $segments = preg_split('/(\{[a-zA-Z][a-zA-Z0-9_]*\})/', $routePath, -1, PREG_SPLIT_DELIM_CAPTURE);
                $pattern = '';

                foreach ($segments as $segment) {
                    if (preg_match('/^\{([a-zA-Z][a-zA-Z0-9_]*)\}$/D', $segment, $parameter)) {
                        $parameterNames[] = $parameter[1];
                        $pattern .= '([^/]+)';
                    } else {
                        $pattern .= preg_quote($segment, '#');
                    }
                }

                if (!preg_match('#^' . $pattern . '$#D', $path, $matches)) {
                    continue;
                }

                $allowedMethods[] = $routeMethod;

                if ($routeMethod !== strtoupper($method)) {
                    continue;
                }

                array_shift($matches);
                $parameters = [];

                foreach ($parameterNames as $index => $name) {
                    $parameters[$name] = rawurldecode($matches[$index]);
                }

                return $parameters === [] ? $handler() : $handler($parameters);
            }
        }

        if ($allowedMethods !== []) {
            throw new HttpException('Method not allowed.', 405, ['allowed' => $allowedMethods]);
        }

        throw new HttpException('Route not found.', 404);
    }
}
