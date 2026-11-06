<?php

declare(strict_types=1);

namespace DesignLila\Auth;

use DesignLila\Config\Env;
use DesignLila\Database\Connection;
use DesignLila\Support\HttpException;
use DesignLila\Support\Input;
use PDOException;

final class AdminAuth
{
    /** @return array<string, mixed> */
    public static function setupFirstAdmin(array $input): array
    {
        $configuredToken = Env::get('APP_SETUP_TOKEN');
        $providedToken = $input['setup_token'] ?? null;

        if (
            !is_string($configuredToken)
            || strlen($configuredToken) < 32
            || !is_string($providedToken)
            || !hash_equals($configuredToken, $providedToken)
        ) {
            throw new HttpException('Initial administrator setup is unavailable.', 404);
        }

        $name = Input::string($input, 'name', 150);
        $email = Input::email(Input::string($input, 'email', 190));
        $password = Input::string($input, 'password', 200, true, false);

        if (strlen($password) < 12) {
            throw new HttpException('Password must contain at least 12 characters.', 422);
        }

        $pdo = Connection::get();
        $pdo->beginTransaction();

        try {
            $role = $pdo->prepare("SELECT id FROM roles WHERE nombre = 'Superadministrador' FOR UPDATE");
            $role->execute();
            $roleId = $role->fetchColumn();

            if ($roleId === false) {
                throw new HttpException('The Superadministrador role is not configured.', 409);
            }

            if ((int) $pdo->query('SELECT COUNT(*) FROM usuarios')->fetchColumn() > 0) {
                throw new HttpException('Initial administrator setup has already been completed.', 409);
            }

            $create = $pdo->prepare(
                'INSERT INTO usuarios (nombre, email, password_hash, estado) VALUES (:nombre, :email, :password_hash, 1)'
            );
            $create->execute([
                'nombre' => $name,
                'email' => $email,
                'password_hash' => password_hash($password, PASSWORD_DEFAULT),
            ]);
            $userId = (int) $pdo->lastInsertId();

            $assign = $pdo->prepare('INSERT INTO usuario_roles (usuario_id, rol_id) VALUES (:usuario_id, :rol_id)');
            $assign->execute(['usuario_id' => $userId, 'rol_id' => $roleId]);
            $pdo->commit();

            return ['id' => $userId, 'name' => $name, 'email' => $email, 'roles' => ['Superadministrador']];
        } catch (\Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            if ($exception instanceof PDOException && (string) $exception->getCode() === '23000') {
                throw new HttpException('An account with this email already exists.', 409, [], $exception);
            }

            throw $exception;
        }
    }

    /** @return array<string, mixed> */
    public static function login(array $input): array
    {
        $email = Input::email(Input::string($input, 'email', 190));
        $password = Input::string($input, 'password', 200, true, false);
        $statement = Connection::get()->prepare(
            'SELECT id, nombre, email, password_hash, estado FROM usuarios WHERE email = :email LIMIT 1'
        );
        $statement->execute(['email' => $email]);
        $user = $statement->fetch();

        if (
            !is_array($user)
            || (int) $user['estado'] !== 1
            || !password_verify($password, $user['password_hash'])
        ) {
            throw new HttpException('Invalid email or password.', 401);
        }

        Session::regenerate();
        $_SESSION['user_id'] = (int) $user['id'];
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

        $update = Connection::get()->prepare('UPDATE usuarios SET ultimo_acceso = CURRENT_TIMESTAMP WHERE id = :id');
        $update->execute(['id' => $user['id']]);

        return self::publicUser((int) $user['id']);
    }

    /** @return array<string, mixed> */
    public static function requirePermission(string $permission): array
    {
        Session::start();
        $userId = $_SESSION['user_id'] ?? null;

        if (!is_int($userId) || $userId < 1) {
            throw new HttpException('Authentication required.', 401);
        }

        $query = Connection::get()->prepare(
            'SELECT u.id, u.nombre, u.email
             FROM usuarios u
             JOIN usuario_roles ur ON ur.usuario_id = u.id
             JOIN rol_permisos rp ON rp.rol_id = ur.rol_id
             JOIN permisos p ON p.id = rp.permiso_id
             WHERE u.id = :user_id AND u.estado = 1 AND p.codigo = :permission
             LIMIT 1'
        );
        $query->execute(['user_id' => $userId, 'permission' => $permission]);
        $user = $query->fetch();

        if (!is_array($user)) {
            $active = Connection::get()->prepare('SELECT estado FROM usuarios WHERE id = :id');
            $active->execute(['id' => $userId]);

            if ((int) $active->fetchColumn() !== 1) {
                Session::destroy();
                throw new HttpException('Authentication required.', 401);
            }

            throw new HttpException('Permission denied.', 403);
        }

        return $user;
    }

    /** @return array<string, mixed> */
    public static function currentUser(): array
    {
        Session::start();
        $userId = $_SESSION['user_id'] ?? null;

        if (!is_int($userId) || $userId < 1) {
            throw new HttpException('Authentication required.', 401);
        }

        return self::publicUser($userId);
    }

    /** @return array<string, mixed> */
    private static function publicUser(int $userId): array
    {
        $query = Connection::get()->prepare(
            'SELECT u.id, u.nombre AS name, u.email,
                    COALESCE(GROUP_CONCAT(DISTINCT r.nombre ORDER BY r.nombre SEPARATOR \',\'), \'\') AS roles,
                    COALESCE(GROUP_CONCAT(DISTINCT p.codigo ORDER BY p.codigo SEPARATOR \',\'), \'\') AS permissions
             FROM usuarios u
             LEFT JOIN usuario_roles ur ON ur.usuario_id = u.id
             LEFT JOIN roles r ON r.id = ur.rol_id
             LEFT JOIN rol_permisos rp ON rp.rol_id = r.id
             LEFT JOIN permisos p ON p.id = rp.permiso_id
             WHERE u.id = :id AND u.estado = 1
             GROUP BY u.id, u.nombre, u.email'
        );
        $query->execute(['id' => $userId]);
        $user = $query->fetch();

        if (!is_array($user)) {
            Session::destroy();
            throw new HttpException('Authentication required.', 401);
        }

        $user['roles'] = $user['roles'] === '' ? [] : explode(',', $user['roles']);
        $user['permissions'] = $user['permissions'] === '' ? [] : explode(',', $user['permissions']);

        return $user;
    }
}
