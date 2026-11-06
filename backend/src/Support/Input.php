<?php

declare(strict_types=1);

namespace DesignLila\Support;

final class Input
{
    /** @param array<string, mixed> $data */
    public static function string(
        array $data,
        string $key,
        int $maxLength,
        bool $required = true,
        bool $trim = true,
    ): ?string {
        $value = $data[$key] ?? null;

        if ($value === null && !$required) {
            return null;
        }

        if (!is_string($value)) {
            throw new HttpException("Field {$key} must be a string.", 422);
        }

        $value = $trim ? trim($value) : $value;

        if (($required && $value === '') || mb_strlen($value) > $maxLength) {
            throw new HttpException("Field {$key} is empty or too long.", 422);
        }

        return $value;
    }

    /** @param array<string, mixed> $data */
    public static function id(array $data, string $key): int
    {
        $value = filter_var($data[$key] ?? null, FILTER_VALIDATE_INT);

        if ($value === false || $value < 1) {
            throw new HttpException("Field {$key} must be a positive integer.", 422);
        }

        return $value;
    }

    /** @param array<string, mixed> $data */
    public static function integer(array $data, string $key, int $min, int $max): int
    {
        $value = filter_var($data[$key] ?? null, FILTER_VALIDATE_INT);

        if ($value === false || $value < $min || $value > $max) {
            throw new HttpException("Field {$key} is outside the allowed range.", 422);
        }

        return $value;
    }

    /** @param array<string, mixed> $data */
    public static function boolean(array $data, string $key, bool $default = false): bool
    {
        if (!array_key_exists($key, $data)) {
            return $default;
        }

        $value = filter_var($data[$key], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);

        if ($value === null) {
            throw new HttpException("Field {$key} must be a boolean.", 422);
        }

        return $value;
    }

    /** @param array<string, mixed> $data */
    public static function moneyCents(array $data, string $key, bool $required = true): ?int
    {
        $value = $data[$key] ?? null;

        if ($value === null && !$required) {
            return null;
        }

        if (!is_string($value) && !is_int($value)) {
            throw new HttpException("Field {$key} must be a decimal amount.", 422);
        }

        $value = (string) $value;

        if (!preg_match('/^(0|[1-9][0-9]{0,9})(?:\.([0-9]{1,2}))?$/D', $value, $matches)) {
            throw new HttpException("Field {$key} must be a non-negative amount with up to two decimals.", 422);
        }

        $fraction = str_pad($matches[2] ?? '', 2, '0');

        return ((int) $matches[1] * 100) + (int) $fraction;
    }

    public static function centsFromDatabase(string|int $value): int
    {
        $value = (string) $value;

        if (!preg_match('/^(0|[1-9][0-9]{0,9})(?:\.([0-9]{1,2}))?$/D', $value, $matches)) {
            throw new \RuntimeException('A stored monetary amount is invalid.');
        }

        return ((int) $matches[1] * 100) + (int) str_pad($matches[2] ?? '', 2, '0');
    }

    public static function moneyFromCents(int $cents): string
    {
        if ($cents < 0) {
            throw new \InvalidArgumentException('Money cannot be negative.');
        }

        return intdiv($cents, 100) . '.' . str_pad((string) ($cents % 100), 2, '0', STR_PAD_LEFT);
    }

    public static function email(string $email): string
    {
        $email = mb_strtolower(trim($email));

        if (mb_strlen($email) > 190 || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            throw new HttpException('A valid email address is required.', 422);
        }

        return $email;
    }
}
