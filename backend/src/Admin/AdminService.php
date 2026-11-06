<?php

declare(strict_types=1);

namespace DesignLila\Admin;

use DesignLila\Database\Connection;
use DesignLila\Support\HttpException;
use DesignLila\Support\Input;
use PDO;
use PDOException;

final class AdminService
{
    private const PUBLIC_SETTINGS = [
        'nombre_tienda',
        'moneda',
        'pais',
        'impuesto_porcentaje',
        'email_contacto',
        'telefono_contacto',
        'whatsapp',
        'direccion_tienda',
        'politica_envios',
        'politica_devoluciones',
        'costo_envio_base',
    ];

    /** @return array<string, string|null> */
    public static function publicSettings(): array
    {
        $placeholders = implode(',', array_fill(0, count(self::PUBLIC_SETTINGS), '?'));
        $statement = Connection::get()->prepare(
            "SELECT clave, valor FROM configuracion WHERE clave IN ({$placeholders}) ORDER BY clave"
        );
        $statement->execute(self::PUBLIC_SETTINGS);
        $settings = [];
        foreach ($statement->fetchAll() as $row) {
            $settings[$row['clave']] = $row['valor'];
        }

        return $settings;
    }

    /** @return list<array<string, mixed>> */
    public static function settings(): array
    {
        return Connection::get()->query(
            'SELECT clave AS `key`, valor AS value, descripcion AS description, updated_at
             FROM configuracion ORDER BY clave'
        )->fetchAll();
    }

    public static function saveSetting(array $input): void
    {
        $key = Input::string($input, 'key', 100);
        if (!in_array($key, self::PUBLIC_SETTINGS, true)) {
            throw new HttpException('This setting cannot be modified through the API.', 422);
        }
        $value = Input::string($input, 'value', 65535, false, false);
        if ($key === 'costo_envio_base') {
            $cents = Input::moneyCents(['value' => $value]);
            $value = Input::moneyFromCents($cents);
        } elseif ($key === 'impuesto_porcentaje' && $value !== null
            && !preg_match('/^(0|[1-9][0-9]{0,2})(?:\.[0-9]{1,2})?$/D', $value)) {
            throw new HttpException('Tax percentage must be between 0 and 999.99.', 422);
        }

        $statement = Connection::get()->prepare(
            'INSERT INTO configuracion (clave, valor, descripcion)
             VALUES (:key, :value, :description)
             ON DUPLICATE KEY UPDATE valor = VALUES(valor), descripcion = VALUES(descripcion)'
        );
        $statement->execute([
            'key' => $key,
            'value' => $value,
            'description' => Input::string($input, 'description', 255, false),
        ]);
    }

    /** @return array<string, mixed> */
    public static function metrics(): array
    {
        $pdo = Connection::get();
        $orders = $pdo->query(
            "SELECT COUNT(*) AS total,
                    SUM(estado = 'pendiente_pago') AS pending_payment,
                    SUM(estado = 'pago_confirmado') AS paid,
                    SUM(estado = 'en_preparacion') AS preparing,
                    SUM(estado = 'listo_para_envio') AS ready_to_ship,
                    SUM(estado = 'enviado') AS shipped,
                    SUM(estado = 'entregado') AS delivered,
                    SUM(estado = 'cancelado') AS cancelled
             FROM pedidos"
        )->fetch();
        $payments = $pdo->query(
            "SELECT COALESCE(SUM(monto), 0) AS approved_total
             FROM pagos WHERE estado = 'aprobado'"
        )->fetch();
        $customers = (int) $pdo->query('SELECT COUNT(*) FROM clientes')->fetchColumn();
        $products = (int) $pdo->query('SELECT COUNT(*) FROM productos WHERE estado = 1')->fetchColumn();

        return [
            'orders' => array_map(static fn ($value): int => (int) ($value ?? 0), $orders ?: []),
            'approved_payments_total' => (string) ($payments['approved_total'] ?? '0.00'),
            'customers' => $customers,
            'active_products' => $products,
        ];
    }

    /** @return array<string, mixed> */
    public static function customers(array $query): array
    {
        [$page, $perPage] = self::pagination($query);
        $search = isset($query['search']) ? mb_substr($query['search'], 0, 100) : '';
        $where = $search === '' ? '' : ' WHERE (c.nombre LIKE :search_name OR c.apellidos LIKE :search_last_name OR c.email LIKE :search_email OR c.telefono LIKE :search_phone)';
        $pattern = '%' . $search . '%';
        $parameters = $search === '' ? [] : [
            'search_name' => $pattern, 'search_last_name' => $pattern,
            'search_email' => $pattern, 'search_phone' => $pattern,
        ];
        $pdo = Connection::get();
        $count = $pdo->prepare("SELECT COUNT(*) FROM clientes c{$where}");
        $count->execute($parameters);
        $total = (int) $count->fetchColumn();
        $statement = $pdo->prepare(
            "SELECT c.id, c.nombre, c.apellidos, c.email, c.telefono, c.documento,
                    c.created_at, COUNT(DISTINCT p.id) AS pedidos_count,
                    COALESCE(SUM(CASE WHEN p.estado NOT IN ('cancelado', 'reembolsado') THEN p.total ELSE 0 END), 0) AS pedidos_total
             FROM clientes c LEFT JOIN pedidos p ON p.cliente_id = c.id{$where}
             GROUP BY c.id ORDER BY c.created_at DESC, c.id DESC
             LIMIT {$perPage} OFFSET " . (($page - 1) * $perPage)
        );
        $statement->execute($parameters);

        return [
            'items' => $statement->fetchAll(),
            'pagination' => ['page' => $page, 'per_page' => $perPage, 'total' => $total],
        ];
    }

    /** @return array<string, mixed> */
    public static function inventory(array $query): array
    {
        [$page, $perPage] = self::pagination($query);
        $search = isset($query['search']) ? mb_substr($query['search'], 0, 100) : '';
        $where = $search === '' ? '' : ' WHERE sku LIKE :search_sku OR nombre LIKE :search_name OR categoria LIKE :search_category';
        $pattern = '%' . $search . '%';
        $parameters = $search === '' ? [] : [
            'search_sku' => $pattern, 'search_name' => $pattern, 'search_category' => $pattern,
        ];
        $pdo = Connection::get();
        $count = $pdo->prepare("SELECT COUNT(*) FROM vw_inventario_productos{$where}");
        $count->execute($parameters);
        $total = (int) $count->fetchColumn();
        $statement = $pdo->prepare(
            "SELECT id, sku, nombre, stock, stock_minimo, controlar_stock, categoria, estado_inventario, variante_id
             FROM vw_inventario_productos{$where} ORDER BY nombre, variante_id
             LIMIT {$perPage} OFFSET " . (($page - 1) * $perPage)
        );
        $statement->execute($parameters);

        return [
            'items' => $statement->fetchAll(),
            'pagination' => ['page' => $page, 'per_page' => $perPage, 'total' => $total],
        ];
    }

    public static function adjustInventory(array $input, int $userId): array
    {
        $productId = Input::id($input, 'product_id');
        $variantId = isset($input['variant_id']) && $input['variant_id'] !== ''
            ? Input::id($input, 'variant_id')
            : null;
        $delta = filter_var($input['delta'] ?? null, FILTER_VALIDATE_INT);
        if ($delta === false || $delta === 0 || abs($delta) > 1000000) {
            throw new HttpException('Inventory delta must be a non-zero integer within the allowed limit.', 422);
        }
        $notes = Input::string($input, 'notes', 65535, false);
        $pdo = Connection::get();
        $pdo->beginTransaction();
        try {
            $productQuery = $pdo->prepare(
                'SELECT id, controlar_stock, modo_inventario, stock FROM productos WHERE id = :id FOR UPDATE'
            );
            $productQuery->execute(['id' => $productId]);
            $product = $productQuery->fetch();
            if (!is_array($product)) {
                throw new HttpException('Product not found.', 404);
            }
            if ((int) $product['controlar_stock'] !== 1) {
                throw new HttpException('Stock control is disabled for this product.', 409);
            }

            if ($variantId === null) {
                if ($product['modo_inventario'] !== 'simple') {
                    throw new HttpException('Select a variant for this product.', 422);
                }
                $oldStock = (int) $product['stock'];
                $table = 'productos';
                $rowId = $productId;
            } else {
                if ($product['modo_inventario'] !== 'variantes') {
                    throw new HttpException('Simple products cannot receive variant inventory.', 422);
                }
                $variantQuery = $pdo->prepare(
                    'SELECT id, stock FROM producto_variantes
                     WHERE id = :variant_id AND producto_id = :product_id FOR UPDATE'
                );
                $variantQuery->execute(['variant_id' => $variantId, 'product_id' => $productId]);
                $variant = $variantQuery->fetch();
                if (!is_array($variant)) {
                    throw new HttpException('Variant not found for this product.', 404);
                }
                $oldStock = (int) $variant['stock'];
                $table = 'producto_variantes';
                $rowId = $variantId;
            }

            $newStock = $oldStock + $delta;
            if ($newStock < 0 || $newStock > 4294967295) {
                throw new HttpException('Inventory adjustment would exceed the valid stock range.', 409);
            }
            $pdo->prepare("UPDATE {$table} SET stock = :stock WHERE id = :id")
                ->execute(['stock' => $newStock, 'id' => $rowId]);
            $movement = $pdo->prepare(
                'INSERT INTO movimientos_inventario
                 (producto_id, variante_id, usuario_id, tipo, cantidad, stock_anterior, stock_nuevo, observaciones)
                 VALUES (:product_id, :variant_id, :user_id, \'ajuste\', :quantity, :old_stock, :new_stock, :notes)'
            );
            $movement->execute([
                'product_id' => $productId,
                'variant_id' => $variantId,
                'user_id' => $userId,
                'quantity' => abs($delta),
                'old_stock' => $oldStock,
                'new_stock' => $newStock,
                'notes' => $notes,
            ]);
            $movementId = (int) $pdo->lastInsertId();
            $pdo->commit();

            return [
                'movement_id' => $movementId,
                'product_id' => $productId,
                'variant_id' => $variantId,
                'stock_before' => $oldStock,
                'stock_after' => $newStock,
            ];
        } catch (\Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $exception;
        }
    }

    /** @return array<string, mixed> */
    public static function movements(array $query): array
    {
        [$page, $perPage] = self::pagination($query);
        $conditions = [];
        $parameters = [];
        if (isset($query['product_id'])) {
            $productId = filter_var($query['product_id'], FILTER_VALIDATE_INT);
            if ($productId === false || $productId < 1) {
                throw new HttpException('Invalid product_id filter.', 422);
            }
            $conditions[] = 'mi.producto_id = :product_id';
            $parameters['product_id'] = $productId;
        }
        if (isset($query['order_id'])) {
            $orderId = filter_var($query['order_id'], FILTER_VALIDATE_INT);
            if ($orderId === false || $orderId < 1) {
                throw new HttpException('Invalid order_id filter.', 422);
            }
            $conditions[] = 'mi.pedido_id = :order_id';
            $parameters['order_id'] = $orderId;
        }
        $where = $conditions === [] ? '' : ' WHERE ' . implode(' AND ', $conditions);
        $pdo = Connection::get();
        $count = $pdo->prepare("SELECT COUNT(*) FROM movimientos_inventario mi{$where}");
        $count->execute($parameters);
        $total = (int) $count->fetchColumn();
        $statement = $pdo->prepare(
            "SELECT mi.id, mi.producto_id, mi.variante_id, mi.usuario_id, mi.tipo, mi.cantidad,
                    mi.stock_anterior, mi.stock_nuevo, mi.referencia, mi.observaciones, mi.created_at,
                    mi.pedido_id, p.nombre AS producto, pv.nombre AS variante
             FROM movimientos_inventario mi
             JOIN productos p ON p.id = mi.producto_id
             LEFT JOIN producto_variantes pv ON pv.id = mi.variante_id{$where}
             ORDER BY mi.created_at DESC, mi.id DESC
             LIMIT {$perPage} OFFSET " . (($page - 1) * $perPage)
        );
        $statement->execute($parameters);

        return [
            'items' => $statement->fetchAll(),
            'pagination' => ['page' => $page, 'per_page' => $perPage, 'total' => $total],
        ];
    }

    /** @return array<string, mixed> */
    public static function shipments(array $query): array
    {
        [$page, $perPage] = self::pagination($query);
        $where = '';
        $parameters = [];
        if (isset($query['status']) && $query['status'] !== '') {
            $where = ' WHERE e.estado = :status';
            $parameters['status'] = mb_substr($query['status'], 0, 40);
        }
        $pdo = Connection::get();
        $count = $pdo->prepare("SELECT COUNT(*) FROM envios e{$where}");
        $count->execute($parameters);
        $total = (int) $count->fetchColumn();
        $statement = $pdo->prepare(
            "SELECT e.id, e.pedido_id, p.referencia AS order_reference, p.nombre_cliente,
                    e.transportadora, e.numero_guia, e.estado, e.fecha_preparacion, e.fecha_listo,
                    e.fecha_despacho, e.fecha_entrega, e.observaciones, e.updated_at
             FROM envios e JOIN pedidos p ON p.id = e.pedido_id{$where}
             ORDER BY e.updated_at DESC, e.id DESC
             LIMIT {$perPage} OFFSET " . (($page - 1) * $perPage)
        );
        $statement->execute($parameters);

        return [
            'items' => $statement->fetchAll(),
            'pagination' => ['page' => $page, 'per_page' => $perPage, 'total' => $total],
        ];
    }

    /** @return list<array<string, mixed>> */
    public static function roles(): array
    {
        return Connection::get()->query('SELECT id, nombre AS name, descripcion AS description FROM roles ORDER BY id')->fetchAll();
    }

    /** @return array<string, mixed> */
    public static function users(): array
    {
        return Connection::get()->query(
            'SELECT u.id, u.nombre AS name, u.email, u.estado AS active, u.ultimo_acceso AS last_login,
                    GROUP_CONCAT(DISTINCT r.nombre ORDER BY r.nombre SEPARATOR \',\') AS roles
             FROM usuarios u
             LEFT JOIN usuario_roles ur ON ur.usuario_id = u.id
             LEFT JOIN roles r ON r.id = ur.rol_id
             GROUP BY u.id ORDER BY u.nombre'
        )->fetchAll();
    }

    /** @return array<string, mixed> */
    public static function saveUser(array $input, int $actorId, ?int $userId = null): array
    {
        if ($userId !== null && $userId === $actorId) {
            throw new HttpException('Use the account security flow to modify your own account.', 409);
        }
        $name = Input::string($input, 'name', 150);
        $email = Input::email(Input::string($input, 'email', 190));
        $roles = $input['role_ids'] ?? null;
        if (!is_array($roles) || !array_is_list($roles) || $roles === [] || count($roles) > 20) {
            throw new HttpException('At least one valid role is required.', 422);
        }
        $roleIds = [];
        foreach ($roles as $roleId) {
            $parsed = filter_var($roleId, FILTER_VALIDATE_INT);
            if ($parsed === false || $parsed < 1) {
                throw new HttpException('Role identifiers must be positive integers.', 422);
            }
            $roleIds[] = (int) $parsed;
        }
        $roleIds = array_values(array_unique($roleIds));
        $password = $input['password'] ?? null;
        if ($userId === null || (is_string($password) && $password !== '')) {
            if (!is_string($password) || strlen($password) < 12 || strlen($password) > 200) {
                throw new HttpException('Password must contain 12 to 200 characters.', 422);
            }
        }

        $pdo = Connection::get();
        $pdo->beginTransaction();
        try {
            $roleCheck = $pdo->prepare(
                'SELECT COUNT(*) FROM roles WHERE id IN (' . implode(',', array_fill(0, count($roleIds), '?')) . ')'
            );
            $roleCheck->execute($roleIds);
            if ((int) $roleCheck->fetchColumn() !== count($roleIds)) {
                throw new HttpException('One or more selected roles do not exist.', 422);
            }

            if ($userId === null) {
                $statement = $pdo->prepare(
                    'INSERT INTO usuarios (nombre, email, password_hash, estado)
                     VALUES (:name, :email, :password_hash, 1)'
                );
                $statement->execute([
                    'name' => $name, 'email' => $email, 'password_hash' => password_hash($password, PASSWORD_DEFAULT),
                ]);
                $userId = (int) $pdo->lastInsertId();
            } else {
                $active = Input::boolean($input, 'active', true);
                $sql = 'UPDATE usuarios SET nombre = :name, email = :email, estado = :active';
                $params = ['name' => $name, 'email' => $email, 'active' => (int) $active, 'id' => $userId];
                if (is_string($password) && $password !== '') {
                    $sql .= ', password_hash = :password_hash';
                    $params['password_hash'] = password_hash($password, PASSWORD_DEFAULT);
                }
                $sql .= ' WHERE id = :id';
                $statement = $pdo->prepare($sql);
                $statement->execute($params);
                if ($statement->rowCount() === 0) {
                    $exists = $pdo->prepare('SELECT 1 FROM usuarios WHERE id = :id');
                    $exists->execute(['id' => $userId]);
                    if ($exists->fetchColumn() === false) {
                        throw new HttpException('User not found.', 404);
                    }
                }
                $pdo->prepare('DELETE FROM usuario_roles WHERE usuario_id = :id')->execute(['id' => $userId]);
            }

            $assign = $pdo->prepare('INSERT INTO usuario_roles (usuario_id, rol_id) VALUES (:user_id, :role_id)');
            foreach ($roleIds as $roleId) {
                $assign->execute(['user_id' => $userId, 'role_id' => $roleId]);
            }
            $pdo->commit();

            $query = $pdo->prepare(
                'SELECT u.id, u.nombre AS name, u.email, u.estado AS active,
                        GROUP_CONCAT(DISTINCT r.nombre ORDER BY r.nombre SEPARATOR \',\') AS roles
                 FROM usuarios u LEFT JOIN usuario_roles ur ON ur.usuario_id = u.id
                 LEFT JOIN roles r ON r.id = ur.rol_id WHERE u.id = :id GROUP BY u.id'
            );
            $query->execute(['id' => $userId]);

            return $query->fetch();
        } catch (\Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            if ($exception instanceof PDOException && (string) $exception->getCode() === '23000') {
                throw new HttpException('The email is already in use.', 409, [], $exception);
            }
            throw $exception;
        }
    }

    /** @return array{0:int,1:int} */
    private static function pagination(array $query): array
    {
        $page = filter_var($query['page'] ?? 1, FILTER_VALIDATE_INT);
        $perPage = filter_var($query['per_page'] ?? 25, FILTER_VALIDATE_INT);
        if ($page === false || $page < 1 || $page > 100000
            || $perPage === false || $perPage < 1 || $perPage > 100) {
            throw new HttpException('Invalid pagination values.', 422);
        }

        return [(int) $page, (int) $perPage];
    }
}
