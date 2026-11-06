<?php

declare(strict_types=1);

namespace DesignLila\Auth;

use DesignLila\Config\Env;
use DesignLila\Http\Request;
use DesignLila\Support\HttpException;

final class Session
{
    public static function start(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }

        ini_set('session.use_strict_mode', '1');
        ini_set('session.use_only_cookies', '1');
        ini_set('session.use_trans_sid', '0');

        session_name('designlila_admin');
        session_set_cookie_params([
            'lifetime' => 0,
            'path' => '/',
            'secure' => Env::boolean('SESSION_COOKIE_SECURE', true),
            'httponly' => true,
            'samesite' => 'Strict',
        ]);

        if (!session_start()) {
            throw new \RuntimeException('Unable to start the administrative session.');
        }
    }

    public static function csrfToken(): string
    {
        self::start();

        if (!isset($_SESSION['csrf_token']) || !is_string($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }

        return $_SESSION['csrf_token'];
    }

    public static function verifyCsrf(): void
    {
        self::start();
        $provided = Request::header('X-CSRF-Token');
        $stored = $_SESSION['csrf_token'] ?? null;

        if (!is_string($provided) || !is_string($stored) || !hash_equals($stored, $provided)) {
            throw new HttpException('Invalid CSRF token.', 403);
        }
    }

    public static function regenerate(): void
    {
        self::start();

        if (!session_regenerate_id(true)) {
            throw new \RuntimeException('Unable to renew the administrative session.');
        }
    }

    public static function destroy(): void
    {
        self::start();
        $_SESSION = [];

        if (ini_get('session.use_cookies')) {
            $parameters = session_get_cookie_params();
            setcookie(session_name(), '', [
                'expires' => time() - 42000,
                'path' => $parameters['path'],
                'domain' => $parameters['domain'],
                'secure' => $parameters['secure'],
                'httponly' => $parameters['httponly'],
                'samesite' => $parameters['samesite'] ?? 'Strict',
            ]);
        }

        session_destroy();
    }
}
