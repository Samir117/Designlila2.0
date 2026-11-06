<?php

declare(strict_types=1);

namespace DesignLila\Orders;

use DesignLila\Database\Connection;
use DesignLila\Support\HttpException;
use DesignLila\Support\Input;
use PDO;

final class CommerceService
{
    private const ORDER_STATES = [
        'pendiente_pago',
        'pago_confirmado',
        'en_preparacion',
        'listo_para_envio',
        'enviado',
        'entregado',
        'cancelado',
        'reembolsado',
    ];

    /** @return array<string, mixed> */
    public static function createGuestOrder(array $input): array
    {
        $customer = $input['customer'] ?? null;
        $shipping = $input['shipping'] ?? null;
        $items = $input['items'] ?? null;
        if (!is_array($customer) || !is_array($shipping) || !is_array($items) || !array_is_list($items)) {
            throw new HttpException('Customer, shipping and items are required.', 422);
        }
        if ($items === [] || count($items) > 30) {
            throw new HttpException('An order must contain between 1 and 30 item lines.', 422);
        }

        $name = Input::string($customer, 'name', 150);
        $lastName = Input::string($customer, 'last_name', 150, false);
        $email = Input::email(Input::string($customer, 'email', 190));
        $phone = Input::string($customer, 'phone', 30);
        $document = Input::string($customer, 'document', 40, false);
        $recipient = Input::string($shipping, 'recipient', 150);
        $address = Input::string($shipping, 'address', 255);
        $complement = Input::string($shipping, 'complement', 255, false);
        $city = Input::string($shipping, 'city', 100);
        $department = Input::string($shipping, 'department', 100);
        $postalCode = Input::string($shipping, 'postal_code', 20, false);
        $country = Input::string($shipping + ['country' => 'Colombia'], 'country', 100);
        $normalizedItems = self::normalizeItems($items);
        $pdo = Connection::get();
        $pdo->beginTransaction();

        try {
            $shippingFee = self::configuredShippingFee($pdo);
            $clientId = self::findOrCreateCustomer($pdo, $name, $lastName, $email, $phone, $document);
            $reference = 'DL' . strtoupper(bin2hex(random_bytes(10)));
            $subtotalCents = 0;
            $snapshots = [];

            foreach ($normalizedItems as $item) {
                $productQuery = $pdo->prepare(
                    'SELECT id, sku, nombre, precio, precio_oferta, controlar_stock, stock, modo_inventario, estado
                     FROM productos WHERE id = :id FOR UPDATE'
                );
                $productQuery->execute(['id' => $item['product_id']]);
                $product = $productQuery->fetch();
                if (!is_array($product) || (int) $product['estado'] !== 1) {
                    throw new HttpException('A selected product is no longer available.', 409);
                }

                $variant = null;
                $unitCents = self::productPriceCents($product);
                $variantName = null;
                $attributes = null;
                if ($product['modo_inventario'] === 'variantes') {
                    if ($item['variant_id'] === null) {
                        throw new HttpException('A variant must be selected for each variant product.', 422);
                    }
                    $variantQuery = $pdo->prepare(
                        'SELECT id, producto_id, sku, nombre, atributos, precio, stock, estado
                         FROM producto_variantes WHERE id = :id FOR UPDATE'
                    );
                    $variantQuery->execute(['id' => $item['variant_id']]);
                    $variant = $variantQuery->fetch();
                    if (
                        !is_array($variant)
                        || (int) $variant['producto_id'] !== (int) $product['id']
                        || (int) $variant['estado'] !== 1
                    ) {
                        throw new HttpException('A selected product variant is unavailable.', 409);
                    }
                    if ($variant['precio'] !== null) {
                        $unitCents = Input::centsFromDatabase($variant['precio']);
                    }
                    $variantName = $variant['nombre'];
                    $attributes = $variant['atributos'];
                } elseif ($item['variant_id'] !== null) {
                    throw new HttpException('A variant was provided for a simple product.', 422);
                }

                $stock = $variant === null ? (int) $product['stock'] : (int) $variant['stock'];
                if ((int) $product['controlar_stock'] === 1 && $stock < $item['quantity']) {
                    throw new HttpException('There is not enough stock to place this order.', 409);
                }

                $lineCents = $unitCents * $item['quantity'];
                $subtotalCents += $lineCents;
                if ($lineCents > 999999999999 || $subtotalCents > 999999999999) {
                    throw new HttpException('Order total exceeds the supported monetary range.', 422);
                }
                $snapshots[] = [
                    'product_id' => (int) $product['id'],
                    'variant_id' => $variant === null ? null : (int) $variant['id'],
                    'name' => $product['nombre'],
                    'variant_name' => $variantName,
                    'sku' => $variant['sku'] ?? $product['sku'],
                    'quantity' => $item['quantity'],
                    'unit_price' => Input::moneyFromCents($unitCents),
                    'subtotal' => Input::moneyFromCents($lineCents),
                    'attributes' => $attributes,
                ];
            }

            $totalCents = $subtotalCents + $shippingFee;
            if ($totalCents > 999999999999) {
                throw new HttpException('Order total exceeds the supported monetary range.', 422);
            }
            $order = $pdo->prepare(
                'INSERT INTO pedidos
                 (referencia, cliente_id, nombre_cliente, email_cliente, telefono_cliente,
                  nombre_receptor_envio, direccion_envio, complemento_envio,
                  ciudad_envio, departamento_envio, codigo_postal_envio, pais_envio,
                  subtotal, costo_envio, descuento, total, estado)
                 VALUES
                 (:reference, :customer_id, :customer_name, :email, :phone,
                  :recipient, :address, :complement, :city, :department, :postal_code, :country,
                  :subtotal, :shipping_fee, 0, :total, \'pendiente_pago\')'
            );
            $order->execute([
                'reference' => $reference,
                'customer_id' => $clientId,
                'customer_name' => mb_substr(trim($name . ' ' . ($lastName ?? '')), 0, 150),
                'email' => $email,
                'phone' => $phone,
                'recipient' => $recipient,
                'address' => $address,
                'complement' => $complement,
                'city' => $city,
                'department' => $department,
                'postal_code' => $postalCode,
                'country' => $country,
                'subtotal' => Input::moneyFromCents($subtotalCents),
                'shipping_fee' => Input::moneyFromCents($shippingFee),
                'total' => Input::moneyFromCents($totalCents),
            ]);
            $orderId = (int) $pdo->lastInsertId();

            $detail = $pdo->prepare(
                'INSERT INTO detalle_pedido
                 (pedido_id, producto_id, variante_id, nombre_producto, nombre_variante, sku_producto,
                  cantidad, precio_unitario, subtotal, atributos_variante)
                 VALUES (:order_id, :product_id, :variant_id, :name, :variant_name, :sku,
                         :quantity, :unit_price, :subtotal, :attributes)'
            );
            foreach ($snapshots as $snapshot) {
                $detail->execute([
                    'order_id' => $orderId,
                    'product_id' => $snapshot['product_id'],
                    'variant_id' => $snapshot['variant_id'],
                    'name' => $snapshot['name'],
                    'variant_name' => $snapshot['variant_name'],
                    'sku' => $snapshot['sku'],
                    'quantity' => $snapshot['quantity'],
                    'unit_price' => $snapshot['unit_price'],
                    'subtotal' => $snapshot['subtotal'],
                    'attributes' => $snapshot['attributes'],
                ]);
            }

            $history = $pdo->prepare(
                'INSERT INTO pedido_historial_estados (pedido_id, estado_anterior, estado_nuevo, comentario)
                 VALUES (:order_id, NULL, \'pendiente_pago\', \'Pedido creado por checkout\')'
            );
            $history->execute(['order_id' => $orderId]);
            $addressInsert = $pdo->prepare(
                'INSERT INTO direcciones
                 (cliente_id, nombre_receptor, telefono, direccion, complemento, ciudad, departamento, codigo_postal, principal)
                 VALUES (:customer_id, :recipient, :phone, :address, :complement, :city, :department, :postal_code, 0)'
            );
            $addressInsert->execute([
                'customer_id' => $clientId, 'recipient' => $recipient, 'phone' => $phone,
                'address' => $address, 'complement' => $complement, 'city' => $city,
                'department' => $department, 'postal_code' => $postalCode,
            ]);
            $shippingInsert = $pdo->prepare(
                'INSERT INTO envios (pedido_id, estado) VALUES (:order_id, \'pendiente_preparacion\')'
            );
            $shippingInsert->execute(['order_id' => $orderId]);
            $pdo->commit();

            return [
                'reference' => $reference,
                'status' => 'pendiente_pago',
                'subtotal' => Input::moneyFromCents($subtotalCents),
                'shipping_cost' => Input::moneyFromCents($shippingFee),
                'total' => Input::moneyFromCents($totalCents),
                'currency' => self::publicConfig('moneda') ?? 'COP',
                'items' => array_map(static fn (array $item): array => [
                    'name' => $item['name'],
                    'variant_name' => $item['variant_name'],
                    'sku' => $item['sku'],
                    'quantity' => $item['quantity'],
                    'unit_price' => $item['unit_price'],
                    'subtotal' => $item['subtotal'],
                ], $snapshots),
            ];
        } catch (\Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $exception;
        }
    }

    /** @return array<string, mixed> */
    public static function reportPayment(array $input, ?array $file): array
    {
        $reference = Input::string($input, 'order_reference', 40);
        $email = Input::email(Input::string($input, 'email', 190));
        $method = Input::string($input, 'method', 50);
        $transactionReference = Input::string($input, 'transaction_reference', 120, false);
        $pdo = Connection::get();
        $relativePath = self::storeProof($file);
        try {
            $pdo->beginTransaction();
            $orderQuery = $pdo->prepare(
                'SELECT id, total, estado FROM pedidos
                 WHERE referencia = :reference AND LOWER(email_cliente) = :email LIMIT 1 FOR UPDATE'
            );
            $orderQuery->execute(['reference' => $reference, 'email' => $email]);
            $order = $orderQuery->fetch();
            if (!is_array($order) || $order['estado'] !== 'pendiente_pago') {
                throw new HttpException('Order not found or cannot accept a payment report.', 404);
            }
            $pending = $pdo->prepare('SELECT 1 FROM pagos WHERE pedido_id = :id AND estado = \'reportado\' LIMIT 1');
            $pending->execute(['id' => $order['id']]);
            if ($pending->fetchColumn() !== false) {
                throw new HttpException('A payment report is already awaiting review.', 409);
            }

            $statement = $pdo->prepare(
                'INSERT INTO pagos
                 (pedido_id, metodo, referencia_transaccion, comprobante, monto, estado, fecha_pago)
                 VALUES (:order_id, :method, :transaction_reference, :receipt, :amount, \'reportado\', CURRENT_TIMESTAMP)'
            );
            $statement->execute([
                'order_id' => $order['id'],
                'method' => $method,
                'transaction_reference' => $transactionReference,
                'receipt' => $relativePath,
                'amount' => $order['total'],
            ]);
            $paymentId = (int) $pdo->lastInsertId();
            $pdo->commit();

            return ['payment_id' => $paymentId, 'status' => 'reportado'];
        } catch (\Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            @unlink(self::storagePath($relativePath));
            throw $exception;
        }
    }

    /** @return array<string, mixed> */
    public static function listOrders(array $query): array
    {
        $page = self::queryInteger($query['page'] ?? null, 1, 100000);
        $perPage = self::queryInteger($query['per_page'] ?? null, 25, 100);
        $where = [];
        $parameters = [];
        if (isset($query['status']) && $query['status'] !== '') {
            if (!in_array($query['status'], self::ORDER_STATES, true)) {
                throw new HttpException('Invalid order status filter.', 422);
            }
            $where[] = 'estado = :status';
            $parameters['status'] = $query['status'];
        }
        if (isset($query['search']) && $query['search'] !== '') {
            $where[] = '(referencia LIKE :search_reference OR email_cliente LIKE :search_email OR nombre_cliente LIKE :search_name)';
            $search = '%' . mb_substr($query['search'], 0, 100) . '%';
            $parameters['search_reference'] = $search;
            $parameters['search_email'] = $search;
            $parameters['search_name'] = $search;
        }
        $clause = $where === [] ? '' : ' WHERE ' . implode(' AND ', $where);
        $pdo = Connection::get();
        $count = $pdo->prepare("SELECT COUNT(*) FROM pedidos{$clause}");
        $count->execute($parameters);
        $total = (int) $count->fetchColumn();
        $queryStatement = $pdo->prepare(
            "SELECT id, referencia, nombre_cliente, email_cliente, estado, subtotal, costo_envio,
                    descuento, total, created_at
             FROM pedidos{$clause} ORDER BY created_at DESC, id DESC
             LIMIT {$perPage} OFFSET " . (($page - 1) * $perPage)
        );
        $queryStatement->execute($parameters);

        return [
            'items' => $queryStatement->fetchAll(),
            'pagination' => ['page' => $page, 'per_page' => $perPage, 'total' => $total],
        ];
    }

    /** @return array<string, mixed> */
    public static function listPayments(array $query): array
    {
        $page = self::queryInteger($query['page'] ?? null, 1, 100000);
        $perPage = self::queryInteger($query['per_page'] ?? null, 25, 100);
        $conditions = [];
        $parameters = [];
        if (isset($query['status']) && $query['status'] !== '') {
            if (!in_array($query['status'], ['pendiente', 'reportado', 'aprobado', 'rechazado'], true)) {
                throw new HttpException('Invalid payment status filter.', 422);
            }
            $conditions[] = 'pa.estado = :status';
            $parameters['status'] = $query['status'];
        }
        $where = $conditions === [] ? '' : ' WHERE ' . implode(' AND ', $conditions);
        $pdo = Connection::get();
        $count = $pdo->prepare("SELECT COUNT(*) FROM pagos pa{$where}");
        $count->execute($parameters);
        $total = (int) $count->fetchColumn();
        $statement = $pdo->prepare(
            "SELECT pa.id, pa.pedido_id, pe.referencia AS order_reference, pe.nombre_cliente,
                    pa.metodo, pa.referencia_transaccion, pa.monto, pa.estado, pa.observaciones,
                    pa.validado_por, pa.fecha_pago, pa.created_at
             FROM pagos pa JOIN pedidos pe ON pe.id = pa.pedido_id{$where}
             ORDER BY pa.created_at DESC, pa.id DESC
             LIMIT {$perPage} OFFSET " . (($page - 1) * $perPage)
        );
        $statement->execute($parameters);

        return [
            'items' => $statement->fetchAll(),
            'pagination' => ['page' => $page, 'per_page' => $perPage, 'total' => $total],
        ];
    }

    /** @return array<string, mixed> */
    public static function getOrder(int $id): array
    {
        $pdo = Connection::get();
        $orderQuery = $pdo->prepare('SELECT * FROM pedidos WHERE id = :id');
        $orderQuery->execute(['id' => $id]);
        $order = $orderQuery->fetch();
        if (!is_array($order)) {
            throw new HttpException('Order not found.', 404);
        }
        $details = $pdo->prepare(
            'SELECT id, producto_id, variante_id, nombre_producto, nombre_variante, sku_producto,
                    cantidad, precio_unitario, subtotal, atributos_variante
             FROM detalle_pedido WHERE pedido_id = :id ORDER BY id'
        );
        $details->execute(['id' => $id]);
        $payments = $pdo->prepare(
            'SELECT id, metodo, referencia_transaccion, monto, estado, observaciones,
                    validado_por, fecha_pago, created_at
             FROM pagos WHERE pedido_id = :id ORDER BY id DESC'
        );
        $payments->execute(['id' => $id]);
        $history = $pdo->prepare(
            'SELECT id, usuario_id, estado_anterior, estado_nuevo, comentario, created_at
             FROM pedido_historial_estados WHERE pedido_id = :id ORDER BY id'
        );
        $history->execute(['id' => $id]);
        $shipping = $pdo->prepare(
            'SELECT id, transportadora, numero_guia, estado, fecha_preparacion, fecha_listo,
                    fecha_despacho, fecha_entrega, observaciones
             FROM envios WHERE pedido_id = :id LIMIT 1'
        );
        $shipping->execute(['id' => $id]);
        $order['items'] = $details->fetchAll();
        $order['payments'] = $payments->fetchAll();
        $order['history'] = $history->fetchAll();
        $order['shipping'] = $shipping->fetch() ?: null;

        return $order;
    }

    public static function reviewPayment(int $paymentId, int $userId, bool $approve, ?string $comment): void
    {
        $pdo = Connection::get();
        $pdo->beginTransaction();
        try {
            $paymentQuery = $pdo->prepare('SELECT * FROM pagos WHERE id = :id FOR UPDATE');
            $paymentQuery->execute(['id' => $paymentId]);
            $payment = $paymentQuery->fetch();
            if (!is_array($payment) || $payment['estado'] !== 'reportado') {
                throw new HttpException('Payment report not found or already reviewed.', 409);
            }

            $orderQuery = $pdo->prepare('SELECT * FROM pedidos WHERE id = :id FOR UPDATE');
            $orderQuery->execute(['id' => $payment['pedido_id']]);
            $order = $orderQuery->fetch();
            if (!is_array($order) || $order['estado'] !== 'pendiente_pago') {
                throw new HttpException('Order cannot accept payment review.', 409);
            }

            if ($approve) {
                self::decreaseInventoryForOrder($pdo, (int) $order['id'], $userId);
                $newStatus = 'aprobado';
                $orderStatus = 'pago_confirmado';
            } else {
                $newStatus = 'rechazado';
                $orderStatus = 'pendiente_pago';
            }

            $updatePayment = $pdo->prepare(
                'UPDATE pagos SET estado = :status, observaciones = :comment, validado_por = :user_id,
                 updated_at = CURRENT_TIMESTAMP WHERE id = :id'
            );
            $updatePayment->execute([
                'status' => $newStatus,
                'comment' => $comment,
                'user_id' => $userId,
                'id' => $paymentId,
            ]);

            if ($approve) {
                $pdo->prepare('UPDATE pedidos SET estado = :status WHERE id = :id')
                    ->execute(['status' => $orderStatus, 'id' => $order['id']]);
                $pdo->prepare(
                    'INSERT INTO pedido_historial_estados
                     (pedido_id, usuario_id, estado_anterior, estado_nuevo, comentario)
                     VALUES (:order_id, :user_id, :old_status, :new_status, :comment)'
                )->execute([
                    'order_id' => $order['id'], 'user_id' => $userId,
                    'old_status' => 'pendiente_pago', 'new_status' => $orderStatus,
                    'comment' => 'Pago aprobado',
                ]);
            }

            $pdo->commit();
        } catch (\Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $exception;
        }
    }

    public static function changeOrderStatus(int $orderId, int $userId, string $nextStatus, ?string $comment): void
    {
        $allowed = [
            'pago_confirmado' => ['en_preparacion', 'cancelado'],
            'en_preparacion' => ['listo_para_envio', 'cancelado'],
            'listo_para_envio' => ['enviado', 'cancelado'],
            'enviado' => ['entregado'],
        ];
        if (!in_array($nextStatus, self::ORDER_STATES, true)) {
            throw new HttpException('Invalid order status.', 422);
        }
        $pdo = Connection::get();
        $pdo->beginTransaction();
        try {
            $query = $pdo->prepare('SELECT estado FROM pedidos WHERE id = :id FOR UPDATE');
            $query->execute(['id' => $orderId]);
            $current = $query->fetchColumn();
            if (!is_string($current)) {
                throw new HttpException('Order not found.', 404);
            }
            if ($nextStatus === 'cancelado') {
                self::cancelOrderLocked($pdo, $orderId, $userId, $current, $comment);
            } else {
                if (!in_array($nextStatus, $allowed[$current] ?? [], true)) {
                    throw new HttpException('Invalid order status transition.', 409);
                }
                $pdo->prepare('UPDATE pedidos SET estado = :status WHERE id = :id')
                    ->execute(['status' => $nextStatus, 'id' => $orderId]);
                $pdo->prepare(
                    'INSERT INTO pedido_historial_estados
                     (pedido_id, usuario_id, estado_anterior, estado_nuevo, comentario)
                     VALUES (:order_id, :user_id, :old_status, :new_status, :comment)'
                )->execute([
                    'order_id' => $orderId, 'user_id' => $userId, 'old_status' => $current,
                    'new_status' => $nextStatus, 'comment' => $comment,
                ]);
                self::syncShippingState($pdo, $orderId, $nextStatus);
            }
            $pdo->commit();
        } catch (\Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $exception;
        }
    }

    public static function updateShipping(int $orderId, int $userId, array $input): void
    {
        $carrier = Input::string($input, 'carrier', 120, false);
        $tracking = Input::string($input, 'tracking_number', 120, false);
        $observations = Input::string($input, 'observations', 65535, false);
        $requestedStatus = $input['status'] ?? null;
        $map = [
            'en_preparacion' => 'en_preparacion',
            'listo_para_envio' => 'listo_para_envio',
            'enviado' => 'enviado',
            'entregado' => 'entregado',
        ];
        if (!is_string($requestedStatus) || !isset($map[$requestedStatus])) {
            throw new HttpException('Invalid shipping status.', 422);
        }
        $pdo = Connection::get();
        $pdo->beginTransaction();
        try {
            $orderQuery = $pdo->prepare('SELECT estado FROM pedidos WHERE id = :id FOR UPDATE');
            $orderQuery->execute(['id' => $orderId]);
            $current = $orderQuery->fetchColumn();
            if (!is_string($current)) {
                throw new HttpException('Order not found.', 404);
            }
            $allowed = [
                'pago_confirmado' => ['en_preparacion'],
                'en_preparacion' => ['listo_para_envio'],
                'listo_para_envio' => ['enviado'],
                'enviado' => ['entregado'],
            ];
            if (!in_array($requestedStatus, $allowed[$current] ?? [], true)) {
                throw new HttpException('Invalid shipping status transition.', 409);
            }

            $dateColumn = match ($requestedStatus) {
                'en_preparacion' => 'fecha_preparacion',
                'listo_para_envio' => 'fecha_listo',
                'enviado' => 'fecha_despacho',
                'entregado' => 'fecha_entrega',
            };
            $shippingStatus = $requestedStatus === 'enviado' ? 'despachado' : $requestedStatus;
            $shippingQuery = $pdo->prepare('SELECT id FROM envios WHERE pedido_id = :id FOR UPDATE');
            $shippingQuery->execute(['id' => $orderId]);
            if ($shippingQuery->fetchColumn() === false) {
                throw new HttpException('Shipping record not found.', 404);
            }
            $pdo->prepare(
                'UPDATE pedidos SET estado = :status WHERE id = :id'
            )->execute(['status' => $requestedStatus, 'id' => $orderId]);
            $pdo->prepare(
                'INSERT INTO pedido_historial_estados
                 (pedido_id, usuario_id, estado_anterior, estado_nuevo, comentario)
                 VALUES (:order_id, :user_id, :old_status, :new_status, :comment)'
            )->execute([
                'order_id' => $orderId, 'user_id' => $userId, 'old_status' => $current,
                'new_status' => $requestedStatus, 'comment' => $observations,
            ]);
            $update = $pdo->prepare(
                "UPDATE envios SET transportadora = COALESCE(:carrier, transportadora),
                 numero_guia = COALESCE(:tracking, numero_guia),
                 observaciones = COALESCE(:observations, observaciones),
                 estado = :shipping_status, {$dateColumn} = CURRENT_TIMESTAMP
                 WHERE pedido_id = :order_id"
            );
            $update->execute([
                'carrier' => $carrier, 'tracking' => $tracking, 'observations' => $observations,
                'shipping_status' => $shippingStatus, 'order_id' => $orderId,
            ]);
            $pdo->commit();
        } catch (\Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $exception;
        }
    }

    /** @return array<string, mixed> */
    public static function paymentReceipt(int $paymentId): array
    {
        $statement = Connection::get()->prepare('SELECT comprobante FROM pagos WHERE id = :id');
        $statement->execute(['id' => $paymentId]);
        $relativePath = $statement->fetchColumn();
        if (!is_string($relativePath) || $relativePath === '') {
            throw new HttpException('Payment receipt not found.', 404);
        }
        $path = self::storagePath($relativePath);
        if (!is_file($path)) {
            throw new HttpException('Payment receipt file is missing.', 404);
        }
        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($path);
        $allowed = ['application/pdf', 'image/jpeg', 'image/png', 'image/webp'];
        if (!is_string($mime) || !in_array($mime, $allowed, true)) {
            throw new HttpException('Payment receipt file is invalid.', 404);
        }

        return ['path' => $path, 'mime' => $mime];
    }

    private static function decreaseInventoryForOrder(PDO $pdo, int $orderId, int $userId): void
    {
        $details = $pdo->prepare(
            'SELECT producto_id, variante_id, nombre_producto, cantidad
             FROM detalle_pedido WHERE pedido_id = :id ORDER BY producto_id, variante_id'
        );
        $details->execute(['id' => $orderId]);
        $lines = $details->fetchAll();

        foreach ($lines as $line) {
            if ($line['producto_id'] === null) {
                throw new HttpException('A product in this order no longer exists.', 409);
            }
            $product = self::lockProduct($pdo, (int) $line['producto_id']);
            if ((int) $product['controlar_stock'] !== 1) {
                continue;
            }

            if ($line['variante_id'] !== null) {
                $stockQuery = $pdo->prepare(
                    'SELECT id, producto_id, stock FROM producto_variantes WHERE id = :id FOR UPDATE'
                );
                $stockQuery->execute(['id' => $line['variante_id']]);
                $stockRow = $stockQuery->fetch();
                if (!is_array($stockRow) || (int) $stockRow['producto_id'] !== (int) $line['producto_id']) {
                    throw new HttpException('A variant in this order no longer exists.', 409);
                }
                self::applyStockChange(
                    $pdo, (int) $line['producto_id'], (int) $line['variante_id'], $userId, $orderId,
                    'venta_aprobada', (int) $line['cantidad'], (int) $stockRow['stock'], false, $line['nombre_producto'],
                );
            } else {
                if ($product['modo_inventario'] !== 'simple') {
                    throw new HttpException('Inventory mode does not match the order snapshot.', 409);
                }
                self::applyStockChange(
                    $pdo, (int) $line['producto_id'], null, $userId, $orderId,
                    'venta_aprobada', (int) $line['cantidad'], (int) $product['stock'], false, $line['nombre_producto'],
                );
            }
        }
    }

    private static function cancelOrderLocked(PDO $pdo, int $orderId, int $userId, string $current, ?string $comment): void
    {
        if (!in_array($current, ['pendiente_pago', 'pago_confirmado', 'en_preparacion', 'listo_para_envio'], true)) {
            throw new HttpException('This order can no longer be cancelled.', 409);
        }
        if ($current !== 'pendiente_pago') {
            $approved = $pdo->prepare('SELECT 1 FROM pagos WHERE pedido_id = :id AND estado = \'aprobado\' LIMIT 1');
            $approved->execute(['id' => $orderId]);
            if ($approved->fetchColumn() !== false) {
                self::restoreInventoryForOrder($pdo, $orderId, $userId);
            }
        }
        $pdo->prepare('UPDATE pedidos SET estado = \'cancelado\' WHERE id = :id')->execute(['id' => $orderId]);
        $pdo->prepare('UPDATE envios SET estado = \'cancelado\' WHERE pedido_id = :id')->execute(['id' => $orderId]);
        $pdo->prepare(
            'INSERT INTO pedido_historial_estados
             (pedido_id, usuario_id, estado_anterior, estado_nuevo, comentario)
             VALUES (:order_id, :user_id, :old_status, \'cancelado\', :comment)'
        )->execute([
            'order_id' => $orderId, 'user_id' => $userId,
            'old_status' => $current, 'comment' => $comment ?? 'Pedido cancelado',
        ]);
    }

    private static function restoreInventoryForOrder(PDO $pdo, int $orderId, int $userId): void
    {
        $details = $pdo->prepare(
            'SELECT producto_id, variante_id, nombre_producto, cantidad
             FROM detalle_pedido WHERE pedido_id = :id ORDER BY producto_id, variante_id'
        );
        $details->execute(['id' => $orderId]);
        $saleMovement = $pdo->prepare(
            'SELECT cantidad FROM movimientos_inventario
             WHERE pedido_id = :order_id AND producto_id = :product_id
               AND variante_id <=> :variant_id AND tipo = \'venta_aprobada\'
             LIMIT 1 FOR UPDATE'
        );
        foreach ($details->fetchAll() as $line) {
            if ($line['producto_id'] === null) {
                throw new HttpException('A product in this order no longer exists.', 409);
            }
            $saleMovement->execute([
                'order_id' => $orderId,
                'product_id' => $line['producto_id'],
                'variant_id' => $line['variante_id'],
            ]);
            $soldQuantity = $saleMovement->fetchColumn();
            if ($soldQuantity === false) {
                continue;
            }

            $product = self::lockProduct($pdo, (int) $line['producto_id']);
            if ($line['variante_id'] !== null) {
                $query = $pdo->prepare('SELECT producto_id, stock FROM producto_variantes WHERE id = :id FOR UPDATE');
                $query->execute(['id' => $line['variante_id']]);
                $row = $query->fetch();
                if (!is_array($row) || (int) $row['producto_id'] !== (int) $line['producto_id']) {
                    throw new HttpException('A variant in this order no longer exists.', 409);
                }
                $stock = (int) $row['stock'];
            } else {
                $stock = (int) $product['stock'];
            }
            self::applyStockChange(
                $pdo, (int) $line['producto_id'], $line['variante_id'] === null ? null : (int) $line['variante_id'],
                $userId, $orderId, 'devolucion_pedido', (int) $soldQuantity, $stock, true, $line['nombre_producto'],
            );
        }
    }

    private static function applyStockChange(
        PDO $pdo,
        int $productId,
        ?int $variantId,
        int $userId,
        int $orderId,
        string $type,
        int $quantity,
        int $oldStock,
        bool $increase,
        string $productName,
    ): void {
        $newStock = $increase ? $oldStock + $quantity : $oldStock - $quantity;
        if ($newStock < 0) {
            throw new HttpException("Insufficient stock for {$productName}.", 409);
        }
        if ($newStock > 4294967295) {
            throw new HttpException("Inventory change exceeds the valid stock range for {$productName}.", 409);
        }
        if ($variantId === null) {
            $update = $pdo->prepare('UPDATE productos SET stock = :stock WHERE id = :id');
            $update->execute(['stock' => $newStock, 'id' => $productId]);
        } else {
            $update = $pdo->prepare('UPDATE producto_variantes SET stock = :stock WHERE id = :id');
            $update->execute(['stock' => $newStock, 'id' => $variantId]);
        }
        $movement = $pdo->prepare(
            'INSERT INTO movimientos_inventario
             (producto_id, variante_id, usuario_id, tipo, cantidad, stock_anterior, stock_nuevo, referencia, observaciones, pedido_id)
             VALUES (:product_id, :variant_id, :user_id, :type, :quantity, :old_stock, :new_stock, :reference, :notes, :order_id)'
        );
        $movement->execute([
            'product_id' => $productId, 'variant_id' => $variantId, 'user_id' => $userId, 'type' => $type,
            'quantity' => $quantity, 'old_stock' => $oldStock, 'new_stock' => $newStock,
            'reference' => 'PEDIDO-' . $orderId,
            'notes' => $increase ? 'Devolución por cancelación' : 'Descuento por aprobación de pago',
            'order_id' => $orderId,
        ]);
    }

    /** @return array<string, mixed> */
    private static function lockProduct(PDO $pdo, int $productId): array
    {
        $query = $pdo->prepare(
            'SELECT id, controlar_stock, stock, modo_inventario FROM productos WHERE id = :id FOR UPDATE'
        );
        $query->execute(['id' => $productId]);
        $product = $query->fetch();
        if (!is_array($product)) {
            throw new HttpException('A product in this order no longer exists.', 409);
        }

        return $product;
    }

    /** @param list<array<string, mixed>> $items
     *  @return list<array{product_id:int,variant_id:?int,quantity:int}>
     */
    private static function normalizeItems(array $items): array
    {
        $normalized = [];
        foreach ($items as $item) {
            if (!is_array($item)) {
                throw new HttpException('Each item must be an object.', 422);
            }
            $productId = Input::id($item, 'product_id');
            $variantId = null;
            if (isset($item['variant_id']) && $item['variant_id'] !== '') {
                $variantId = Input::id($item, 'variant_id');
            }
            $quantity = Input::integer($item, 'quantity', 1, 99);
            $key = $productId . ':' . ($variantId ?? 0);
            if (isset($normalized[$key])) {
                $normalized[$key]['quantity'] += $quantity;
                if ($normalized[$key]['quantity'] > 99) {
                    throw new HttpException('Combined item quantity exceeds the allowed limit.', 422);
                }
            } else {
                $normalized[$key] = [
                    'product_id' => $productId,
                    'variant_id' => $variantId,
                    'quantity' => $quantity,
                ];
            }
        }
        usort($normalized, static fn (array $a, array $b): int => [$a['product_id'], $a['variant_id'] ?? 0] <=> [$b['product_id'], $b['variant_id'] ?? 0]);

        return array_values($normalized);
    }

    private static function productPriceCents(array $product): int
    {
        $base = Input::centsFromDatabase($product['precio']);
        if ($product['precio_oferta'] !== null) {
            $offer = Input::centsFromDatabase($product['precio_oferta']);
            if ($offer < $base) {
                return $offer;
            }
        }

        return $base;
    }

    private static function configuredShippingFee(PDO $pdo): int
    {
        $statement = $pdo->prepare('SELECT valor FROM configuracion WHERE clave = :key LIMIT 1');
        $statement->execute(['key' => 'costo_envio_base']);
        $value = $statement->fetchColumn();
        if (!is_string($value) || !preg_match('/^(0|[1-9][0-9]{0,9})(?:\.([0-9]{1,2}))?$/D', $value, $matches)) {
            throw new HttpException('Shipping fee is not configured.', 503);
        }

        return ((int) $matches[1] * 100) + (int) str_pad($matches[2] ?? '', 2, '0');
    }

    private static function findOrCreateCustomer(
        PDO $pdo,
        string $name,
        ?string $lastName,
        string $email,
        string $phone,
        ?string $document,
    ): int {
        $find = $pdo->prepare('SELECT id FROM clientes WHERE LOWER(email) = :email ORDER BY id LIMIT 1 FOR UPDATE');
        $find->execute(['email' => $email]);
        $id = $find->fetchColumn();
        if ($id !== false) {
            return (int) $id;
        }

        $insert = $pdo->prepare(
            'INSERT INTO clientes (nombre, apellidos, email, telefono, documento)
             VALUES (:name, :last_name, :email, :phone, :document)'
        );
        $insert->execute([
            'name' => $name, 'last_name' => $lastName, 'email' => $email,
            'phone' => $phone, 'document' => $document,
        ]);

        return (int) $pdo->lastInsertId();
    }

    private static function storeProof(?array $file): string
    {
        if ($file === null || ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            throw new HttpException('A valid payment receipt file is required.', 422);
        }
        if (!isset($file['tmp_name'], $file['size']) || !is_string($file['tmp_name']) || (int) $file['size'] < 1 || (int) $file['size'] > 5 * 1024 * 1024) {
            throw new HttpException('Payment receipt must be no larger than 5 MB.', 422);
        }
        if (!is_uploaded_file($file['tmp_name'])) {
            throw new HttpException('Invalid uploaded payment receipt.', 422);
        }
        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($file['tmp_name']);
        $extensions = [
            'application/pdf' => 'pdf',
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
        ];
        if (!is_string($mime) || !isset($extensions[$mime])) {
            throw new HttpException('Receipt must be a PDF, JPEG, PNG or WebP file.', 422);
        }
        $directory = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'payment-proofs';
        if (!is_dir($directory) && !mkdir($directory, 0700, true) && !is_dir($directory)) {
            throw new \RuntimeException('Unable to create private payment receipt storage.');
        }
        $filename = bin2hex(random_bytes(24)) . '.' . $extensions[$mime];
        if (!move_uploaded_file($file['tmp_name'], $directory . DIRECTORY_SEPARATOR . $filename)) {
            throw new \RuntimeException('Unable to save uploaded payment receipt.');
        }

        return 'storage/payment-proofs/' . $filename;
    }

    private static function storagePath(string $relativePath): string
    {
        if (!preg_match('#^storage/payment-proofs/[a-f0-9]{48}\.(pdf|jpg|png|webp)$#D', $relativePath)) {
            throw new HttpException('Payment receipt not found.', 404);
        }

        return dirname(__DIR__, 2) . DIRECTORY_SEPARATOR
            . str_replace('/', DIRECTORY_SEPARATOR, $relativePath);
    }

    private static function publicConfig(string $key): ?string
    {
        $statement = Connection::get()->prepare('SELECT valor FROM configuracion WHERE clave = :key');
        $statement->execute(['key' => $key]);
        $value = $statement->fetchColumn();

        return is_string($value) ? $value : null;
    }

    private static function syncShippingState(PDO $pdo, int $orderId, string $orderStatus): void
    {
        $values = [
            'en_preparacion' => ['en_preparacion', 'fecha_preparacion'],
            'listo_para_envio' => ['listo_para_envio', 'fecha_listo'],
            'enviado' => ['despachado', 'fecha_despacho'],
            'entregado' => ['entregado', 'fecha_entrega'],
        ];
        if (!isset($values[$orderStatus])) {
            return;
        }
        [$shippingStatus, $dateColumn] = $values[$orderStatus];
        $pdo->prepare(
            "UPDATE envios SET estado = :status, {$dateColumn} = CURRENT_TIMESTAMP WHERE pedido_id = :id"
        )->execute(['status' => $shippingStatus, 'id' => $orderId]);
    }

    private static function queryInteger(?string $value, int $default, int $max): int
    {
        if ($value === null || $value === '') {
            return $default;
        }
        $number = filter_var($value, FILTER_VALIDATE_INT);
        if ($number === false || $number < 1 || $number > $max) {
            throw new HttpException('Invalid pagination value.', 422);
        }

        return $number;
    }
}
