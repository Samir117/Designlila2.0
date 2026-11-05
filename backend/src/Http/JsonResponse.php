<?php

declare(strict_types=1);

namespace DesignLila\Http;

final class JsonResponse
{
    /** @param array<string, mixed> $data */
    public static function send(array $data, int $status = 200): never
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');

        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        exit;
    }

    /** @param mixed $data */
    public static function success(mixed $data = null, int $status = 200): never
    {
        self::send(['success' => true, 'data' => $data], $status);
    }

    /** @param array<string, mixed> $details */
    public static function error(string $message, int $status = 400, array $details = []): never
    {
        $payload = ['success' => false, 'error' => ['message' => $message]];

        if ($details !== []) {
            $payload['error']['details'] = $details;
        }

        self::send($payload, $status);
    }
}

