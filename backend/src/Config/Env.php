<?php

declare(strict_types=1);

namespace DesignLila\Config;

final class Env
{
    /** @var array<string, string> */
    private static array $values = [];

    public static function load(string $path): void
    {
        if (!is_file($path)) {
            return;
        }

        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($lines === false) {
            throw new \RuntimeException('Unable to read the environment file.');
        }

        foreach ($lines as $line) {
            $line = trim($line);

            if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
                continue;
            }

            [$key, $value] = explode('=', $line, 2);
            $key = trim($key);
            $value = trim($value);

            if ($key === '') {
                continue;
            }

            if (strlen($value) >= 2 && $value[0] === '"' && $value[-1] === '"') {
                $value = substr($value, 1, -1);
            }

            self::$values[$key] = $value;
        }
    }

    public static function get(string $key, ?string $default = null): ?string
    {
        $value = self::$values[$key] ?? $_ENV[$key] ?? getenv($key);

        return is_string($value) ? $value : $default;
    }

    public static function required(string $key): string
    {
        $value = self::get($key);

        if ($value === null || $value === '') {
            throw new \RuntimeException("Missing environment variable: {$key}");
        }

        return $value;
    }

    public static function boolean(string $key, bool $default = false): bool
    {
        return filter_var(self::get($key, $default ? 'true' : 'false'), FILTER_VALIDATE_BOOLEAN);
    }
}
