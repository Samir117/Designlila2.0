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

        $data = json_decode($content, false, 512, JSON_THROW_ON_ERROR);

        if (!$data instanceof \stdClass) {
            throw new \InvalidArgumentException('The JSON body must be an object.');
        }

        return self::normalizeJsonObject($data);
    }

    /** @return array<string, mixed> */
    public static function input(): array
    {
        if (str_starts_with(strtolower($_SERVER['CONTENT_TYPE'] ?? ''), 'multipart/form-data')) {
            return $_POST;
        }

        return self::json();
    }

    /** @return array<string, mixed>|null */
    public static function file(string $key): ?array
    {
        $file = $_FILES[$key] ?? null;

        return is_array($file) ? $file : null;
    }

    public static function header(string $name): ?string
    {
        $key = 'HTTP_' . strtoupper(str_replace('-', '_', $name));
        $value = $_SERVER[$key] ?? null;

        return is_string($value) ? $value : null;
    }

    /** @return array<string, string> */
    public static function query(): array
    {
        $query = [];

        foreach ($_GET as $key => $value) {
            if (is_string($key) && (is_string($value) || is_numeric($value))) {
                $query[$key] = (string) $value;
            }
        }

        return $query;
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

    /** @return array<string, mixed> */
    private static function normalizeJsonObject(\stdClass $value): array
    {
        $normalized = [];
        foreach ($value as $key => $item) {
            $normalized[$key] = self::normalizeJsonValue($item);
        }

        return $normalized;
    }

    private static function normalizeJsonValue(mixed $value): mixed
    {
        if ($value instanceof \stdClass) {
            return self::normalizeJsonObject($value);
        }

        if (is_array($value)) {
            return array_map(self::normalizeJsonValue(...), $value);
        }

        return $value;
    }
}
