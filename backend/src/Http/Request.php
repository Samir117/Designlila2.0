<?php

declare(strict_types=1);

namespace DesignLila\Http;

final class Request
{
    /** @return array<string, mixed> */
    public static function json(): array
    {
        $content = file_get_contents('php://input');

        if ($content === false || trim($content) === '') {
            return [];
        }

        $data = json_decode($content, true, 512, JSON_THROW_ON_ERROR);

        if (!is_array($data)) {
            throw new \InvalidArgumentException('The JSON body must be an object.');
        }

        return $data;
    }

    public static function method(): string
    {
        return strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
    }

    public static function path(): string
    {
        $path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);

        return rtrim($path ?: '/', '/') ?: '/';
    }
}

