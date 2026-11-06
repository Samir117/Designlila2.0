<?php

declare(strict_types=1);

namespace DesignLila\Catalog;

use DesignLila\Database\Connection;
use DesignLila\Support\HttpException;
use DesignLila\Support\Input;
use PDO;

final class CatalogService
{
    /** @return array<string, mixed> */
    public static function listCategories(bool $includeDisabled = false): array
    {
        $sql = 'SELECT id, categoria_padre_id, nombre, slug, descripcion, imagen, meta_title,
                       meta_description, estado, orden
                FROM categorias';
        if (!$includeDisabled) {
            $sql .= ' WHERE estado = 1';
        }
        $sql .= ' ORDER BY orden, nombre';

        return Connection::get()->query($sql)->fetchAll();
    }

    /** @return array<string, mixed> */
    public static function listProducts(
        array $query,
        bool $includeDisabled = false,
        bool $includeInventory = false,
    ): array
    {
        $page = self::queryInteger($query['page'] ?? null, 1, 100000);
        $perPage = self::queryInteger($query['per_page'] ?? null, 20, 100);
        $conditions = [];
        $parameters = [];

        if (!$includeDisabled) {
            $conditions[] = 'p.estado = 1';
        }
        if (isset($query['category']) && $query['category'] !== '') {
            $conditions[] = 'c.slug = :category';
            $parameters['category'] = $query['category'];
        }
        if (isset($query['search']) && $query['search'] !== '') {
            $conditions[] = '(p.nombre LIKE :search_name OR p.sku LIKE :search_sku OR p.descripcion_corta LIKE :search_description)';
            $search = '%' . mb_substr($query['search'], 0, 100) . '%';
            $parameters['search_name'] = $search;
            $parameters['search_sku'] = $search;
            $parameters['search_description'] = $search;
        }
        if (isset($query['featured']) && $query['featured'] === '1') {
            $conditions[] = 'p.destacado = 1';
        }

        $where = $conditions === [] ? '' : ' WHERE ' . implode(' AND ', $conditions);
        $sorts = [
            'newest' => 'p.created_at DESC, p.id DESC',
            'name' => 'p.nombre ASC, p.id ASC',
            'price_asc' => 'COALESCE(p.precio_oferta, p.precio) ASC, p.id ASC',
            'price_desc' => 'COALESCE(p.precio_oferta, p.precio) DESC, p.id DESC',
        ];
        $sort = $sorts[$query['sort'] ?? 'newest'] ?? $sorts['newest'];
        $pdo = Connection::get();
        $count = $pdo->prepare("SELECT COUNT(*) FROM productos p LEFT JOIN categorias c ON c.id = p.categoria_id{$where}");
        $count->execute($parameters);
        $total = (int) $count->fetchColumn();

        $inventoryColumns = $includeInventory ? ', p.stock, p.stock_minimo, p.estado' : '';
        $sql = "SELECT p.id, p.categoria_id, c.nombre AS categoria, p.sku, p.nombre, p.slug,
                       p.descripcion, p.descripcion_corta, p.precio, p.precio_oferta,
                       p.controlar_stock, p.peso, p.meta_title, p.meta_description,
                       p.destacado, p.modo_inventario, p.created_at{$inventoryColumns},
                       CASE WHEN p.controlar_stock = 0 THEN 1
                            WHEN p.modo_inventario = 'simple' THEN (p.stock > 0)
                            ELSE EXISTS (
                                SELECT 1 FROM producto_variantes pv_stock
                                WHERE pv_stock.producto_id = p.id AND pv_stock.estado = 1 AND pv_stock.stock > 0
                            )
                       END AS in_stock,
                       (SELECT i.ruta FROM producto_imagenes i
                        WHERE i.producto_id = p.id ORDER BY i.es_principal DESC, i.orden, i.id LIMIT 1) AS imagen
                FROM productos p
                LEFT JOIN categorias c ON c.id = p.categoria_id{$where}
                ORDER BY {$sort}
                LIMIT {$perPage} OFFSET " . (($page - 1) * $perPage);
        $statement = $pdo->prepare($sql);
        $statement->execute($parameters);

        return [
            'items' => $statement->fetchAll(),
            'pagination' => ['page' => $page, 'per_page' => $perPage, 'total' => $total],
        ];
    }

    /** @return array<string, mixed> */
    public static function getProduct(string $slug, bool $includeDisabled = false): array
    {
        $sql = 'SELECT p.id, p.categoria_id, c.nombre AS categoria, p.sku, p.nombre, p.slug,
                       p.descripcion, p.descripcion_corta, p.precio, p.precio_oferta,
                       p.controlar_stock, p.peso, p.meta_title, p.meta_description,
                       p.destacado, p.modo_inventario, p.created_at,
                       CASE WHEN p.controlar_stock = 0 THEN 1
                            WHEN p.modo_inventario = \'simple\' THEN (p.stock > 0)
                            ELSE EXISTS (
                                SELECT 1 FROM producto_variantes pv_stock
                                WHERE pv_stock.producto_id = p.id AND pv_stock.estado = 1 AND pv_stock.stock > 0
                            )
                       END AS in_stock
                FROM productos p
                LEFT JOIN categorias c ON c.id = p.categoria_id
                WHERE p.slug = :slug';
        if (!$includeDisabled) {
            $sql .= ' AND p.estado = 1';
        }

        $statement = Connection::get()->prepare($sql . ' LIMIT 1');
        $statement->execute(['slug' => $slug]);
        $product = $statement->fetch();

        if (!is_array($product)) {
            throw new HttpException('Product not found.', 404);
        }

        $images = Connection::get()->prepare(
            'SELECT id, ruta, texto_alternativo, es_principal, orden
             FROM producto_imagenes WHERE producto_id = :id ORDER BY es_principal DESC, orden, id'
        );
        $images->execute(['id' => $product['id']]);
        $product['images'] = $images->fetchAll();

        if ($product['modo_inventario'] === 'variantes') {
            $variants = Connection::get()->prepare(
                'SELECT pv.id, pv.sku, pv.nombre, pv.atributos, pv.precio,
                        CASE WHEN p.controlar_stock = 0 OR pv.stock > 0 THEN 1 ELSE 0 END AS in_stock
                 FROM producto_variantes pv
                 JOIN productos p ON p.id = pv.producto_id
                 WHERE pv.producto_id = :id AND pv.estado = 1
                 ORDER BY pv.id'
            );
            $variants->execute(['id' => $product['id']]);
            $product['variants'] = self::decodeVariantAttributes($variants->fetchAll());
        } else {
            $product['variants'] = [];
        }

        return $product;
    }

    /** @return array<string, mixed> */
    public static function saveCategory(array $input, ?int $id = null): array
    {
        $name = Input::string($input, 'name', 150);
        $slug = self::slug(Input::string($input, 'slug', 180));
        $description = Input::string($input, 'description', 65535, false);
        $image = self::imagePath(Input::string($input, 'image', 255, false));
        $metaTitle = Input::string($input, 'meta_title', 255, false);
        $metaDescription = Input::string($input, 'meta_description', 320, false);
        $order = Input::integer($input + ['order' => 0], 'order', -2147483648, 2147483647);
        $parentId = null;
        if (isset($input['parent_id']) && $input['parent_id'] !== '') {
            $parentId = Input::id($input, 'parent_id');
            if ($id === $parentId) {
                throw new HttpException('A category cannot be its own parent.', 422);
            }
            $parent = Connection::get()->prepare('SELECT id FROM categorias WHERE id = :id AND estado = 1');
            $parent->execute(['id' => $parentId]);
            if ($parent->fetchColumn() === false) {
                throw new HttpException('Parent category is not available.', 422);
            }
        }

        $pdo = Connection::get();
        if ($id === null) {
            $statement = $pdo->prepare(
                'INSERT INTO categorias
                 (categoria_padre_id, nombre, slug, descripcion, imagen, meta_title, meta_description, estado, orden)
                 VALUES (:parent_id, :name, :slug, :description, :image, :meta_title, :meta_description, 1, :sort_order)'
            );
            $statement->execute([
                'parent_id' => $parentId, 'name' => $name, 'slug' => $slug, 'description' => $description,
                'image' => $image, 'meta_title' => $metaTitle, 'meta_description' => $metaDescription, 'sort_order' => $order,
            ]);
            $id = (int) $pdo->lastInsertId();
        } else {
            $statement = $pdo->prepare(
                'UPDATE categorias SET categoria_padre_id = :parent_id, nombre = :name, slug = :slug,
                 descripcion = :description, imagen = :image, meta_title = :meta_title,
                 meta_description = :meta_description, orden = :sort_order WHERE id = :id'
            );
            $statement->execute([
                'parent_id' => $parentId, 'name' => $name, 'slug' => $slug, 'description' => $description,
                'image' => $image, 'meta_title' => $metaTitle, 'meta_description' => $metaDescription,
                'sort_order' => $order, 'id' => $id,
            ]);
            self::requireAffectedOrExisting($statement->rowCount(), 'categorias', $id);
        }

        return self::getCategory($id);
    }

    /** @return array<string, mixed> */
    public static function getCategory(int $id): array
    {
        $statement = Connection::get()->prepare(
            'SELECT id, categoria_padre_id, nombre, slug, descripcion, imagen, meta_title,
                    meta_description, estado, orden
             FROM categorias WHERE id = :id'
        );
        $statement->execute(['id' => $id]);
        $row = $statement->fetch();

        if (!is_array($row)) {
            throw new HttpException('Category not found.', 404);
        }

        return $row;
    }

    public static function deactivateCategory(int $id): void
    {
        $statement = Connection::get()->prepare('UPDATE categorias SET estado = 0 WHERE id = :id');
        $statement->execute(['id' => $id]);
        self::requireAffectedOrExisting($statement->rowCount(), 'categorias', $id);
    }

    /** @return array<string, mixed> */
    public static function saveProduct(array $input, ?int $id = null): array
    {
        $pdo = Connection::get();
        $pdo->beginTransaction();

        try {
            $existing = null;
            if ($id !== null) {
                $query = $pdo->prepare('SELECT * FROM productos WHERE id = :id FOR UPDATE');
                $query->execute(['id' => $id]);
                $existing = $query->fetch();
                if (!is_array($existing)) {
                    throw new HttpException('Product not found.', 404);
                }
            }

            $data = $existing === null ? $input : array_replace([
                'category_id' => $existing['categoria_id'],
                'sku' => $existing['sku'],
                'name' => $existing['nombre'],
                'slug' => $existing['slug'],
                'description' => $existing['descripcion'],
                'short_description' => $existing['descripcion_corta'],
                'price' => (string) $existing['precio'],
                'sale_price' => $existing['precio_oferta'],
                'stock_control' => $existing['controlar_stock'],
                'stock' => $existing['stock'],
                'minimum_stock' => $existing['stock_minimo'],
                'weight' => $existing['peso'],
                'meta_title' => $existing['meta_title'],
                'meta_description' => $existing['meta_description'],
                'featured' => $existing['destacado'],
                'inventory_mode' => $existing['modo_inventario'],
            ], $input);
            if ($existing !== null && isset($input['inventory_mode']) && $input['inventory_mode'] !== $existing['modo_inventario']) {
                throw new HttpException('Inventory mode cannot be changed after product creation.', 409);
            }

            $categoryId = null;
            if (isset($data['category_id']) && $data['category_id'] !== '') {
                $categoryId = Input::id($data, 'category_id');
                $categoryQuery = $pdo->prepare('SELECT id FROM categorias WHERE id = :id AND estado = 1');
                $categoryQuery->execute(['id' => $categoryId]);
                if ($categoryQuery->fetchColumn() === false) {
                    throw new HttpException('Selected category is not available.', 422);
                }
            }
            $sku = Input::string($data, 'sku', 80);
            $name = Input::string($data, 'name', 200);
            $slug = self::slug(Input::string($data, 'slug', 220));
            $description = Input::string($data, 'description', 65535, false);
            $shortDescription = Input::string($data, 'short_description', 500, false);
            $price = Input::moneyCents($data, 'price');
            $salePrice = Input::moneyCents($data, 'sale_price', false);
            if ($salePrice !== null && $salePrice >= $price) {
                throw new HttpException('Sale price must be lower than the regular price.', 422);
            }
            $stockControl = Input::boolean($data, 'stock_control', true);
            $stock = Input::integer($data + ['stock' => 0], 'stock', 0, 4294967295);
            if ($existing === null && $stock !== 0) {
                throw new HttpException('Set initial stock through the inventory adjustment endpoint.', 422);
            }
            if ($existing !== null && array_key_exists('stock', $input) && $stock !== (int) $existing['stock']) {
                throw new HttpException('Stock must be changed through the inventory adjustment endpoint.', 422);
            }
            $minimumStock = Input::integer($data + ['minimum_stock' => 0], 'minimum_stock', 0, 4294967295);
            $weight = self::decimal($data['weight'] ?? null, 'weight', 3, true);
            $metaTitle = Input::string($data, 'meta_title', 255, false);
            $metaDescription = Input::string($data, 'meta_description', 320, false);
            $featured = Input::boolean($data, 'featured');
            $mode = $data['inventory_mode'] ?? 'simple';
            if (!in_array($mode, ['simple', 'variantes'], true)) {
                throw new HttpException('Invalid inventory mode.', 422);
            }
            if ($mode === 'variantes' && array_key_exists('stock', $data) && (int) $data['stock'] !== 0) {
                throw new HttpException('Variant products cannot store stock on the parent product.', 422);
            }

            $values = [
                'category_id' => $categoryId,
                'sku' => $sku,
                'name' => $name,
                'slug' => $slug,
                'description' => $description,
                'short_description' => $shortDescription,
                'price' => Input::moneyFromCents($price),
                'sale_price' => $salePrice === null ? null : Input::moneyFromCents($salePrice),
                'stock_control' => (int) $stockControl,
                'stock' => $mode === 'variantes' ? 0 : $stock,
                'minimum_stock' => $mode === 'variantes' ? 0 : $minimumStock,
                'weight' => $weight,
                'meta_title' => $metaTitle,
                'meta_description' => $metaDescription,
                'featured' => (int) $featured,
                'inventory_mode' => $mode,
            ];

            if ($id === null) {
                $statement = $pdo->prepare(
                    'INSERT INTO productos
                     (categoria_id, sku, nombre, slug, descripcion, descripcion_corta, precio, precio_oferta,
                      controlar_stock, stock, stock_minimo, peso, meta_title, meta_description, destacado, modo_inventario, estado)
                     VALUES
                     (:category_id, :sku, :name, :slug, :description, :short_description, :price, :sale_price,
                      :stock_control, :stock, :minimum_stock, :weight, :meta_title, :meta_description, :featured, :inventory_mode, 1)'
                );
                $statement->execute($values);
                $id = (int) $pdo->lastInsertId();
            } else {
                $values['id'] = $id;
                $statement = $pdo->prepare(
                    'UPDATE productos SET categoria_id = :category_id, sku = :sku, nombre = :name, slug = :slug,
                     descripcion = :description, descripcion_corta = :short_description, precio = :price,
                     precio_oferta = :sale_price, controlar_stock = :stock_control, stock = :stock,
                     stock_minimo = :minimum_stock, peso = :weight, meta_title = :meta_title,
                     meta_description = :meta_description, destacado = :featured, modo_inventario = :inventory_mode
                     WHERE id = :id'
                );
                $statement->execute($values);
            }

            if (isset($input['images'])) {
                self::replaceProductImages($pdo, $id, $input['images']);
            }

            if (isset($input['variants'])) {
                if ($mode !== 'variantes') {
                    throw new HttpException('Only variant products may contain variants.', 422);
                }
                self::saveProductVariants($pdo, $id, $input['variants'], $existing !== null);
            } elseif ($mode === 'variantes' && $existing === null) {
                throw new HttpException('Variant products require at least one variant.', 422);
            }

            if ($mode === 'variantes') {
                $variantCount = $pdo->prepare('SELECT COUNT(*) FROM producto_variantes WHERE producto_id = :id AND estado = 1');
                $variantCount->execute(['id' => $id]);
                if ((int) $variantCount->fetchColumn() < 1) {
                    throw new HttpException('Variant products require at least one active variant.', 422);
                }
            }

            $pdo->commit();

            return self::getProductById($id);
        } catch (\Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $exception;
        }
    }

    /** @return array<string, mixed> */
    public static function getProductById(int $id): array
    {
        $statement = Connection::get()->prepare('SELECT slug FROM productos WHERE id = :id');
        $statement->execute(['id' => $id]);
        $slug = $statement->fetchColumn();

        if (!is_string($slug)) {
            throw new HttpException('Product not found.', 404);
        }

        return self::getProduct($slug, true);
    }

    /** @return array<string, mixed> */
    public static function getAdminProduct(int $id): array
    {
        $statement = Connection::get()->prepare(
            'SELECT p.*, c.nombre AS categoria
             FROM productos p LEFT JOIN categorias c ON c.id = p.categoria_id
             WHERE p.id = :id'
        );
        $statement->execute(['id' => $id]);
        $product = $statement->fetch();
        if (!is_array($product)) {
            throw new HttpException('Product not found.', 404);
        }
        $images = Connection::get()->prepare(
            'SELECT id, ruta, texto_alternativo, es_principal, orden
             FROM producto_imagenes WHERE producto_id = :id ORDER BY es_principal DESC, orden, id'
        );
        $images->execute(['id' => $id]);
        $product['images'] = $images->fetchAll();
        $variants = Connection::get()->prepare(
            'SELECT id, sku, nombre, atributos, precio, stock, stock_minimo, estado
             FROM producto_variantes WHERE producto_id = :id ORDER BY id'
        );
        $variants->execute(['id' => $id]);
        $product['variants'] = self::decodeVariantAttributes($variants->fetchAll());

        return $product;
    }

    public static function deactivateProduct(int $id): void
    {
        $statement = Connection::get()->prepare('UPDATE productos SET estado = 0 WHERE id = :id');
        $statement->execute(['id' => $id]);
        self::requireAffectedOrExisting($statement->rowCount(), 'productos', $id);
    }

    private static function replaceProductImages(PDO $pdo, int $productId, mixed $images): void
    {
        if (!is_array($images) || !array_is_list($images) || count($images) > 20) {
            throw new HttpException('Images must be a list of at most 20 image records.', 422);
        }

        $pdo->prepare('DELETE FROM producto_imagenes WHERE producto_id = :id')->execute(['id' => $productId]);
        $insert = $pdo->prepare(
            'INSERT INTO producto_imagenes (producto_id, ruta, texto_alternativo, es_principal, orden)
             VALUES (:product_id, :path, :alt, :primary, :sort_order)'
        );

        foreach ($images as $index => $image) {
            if (!is_array($image)) {
                throw new HttpException('Each image must be an object.', 422);
            }
            $insert->execute([
                'product_id' => $productId,
                'path' => self::imagePath(Input::string($image, 'path', 255)),
                'alt' => Input::string($image, 'alt', 255, false),
                'primary' => (int) Input::boolean($image, 'primary', $index === 0),
                'sort_order' => Input::integer($image + ['order' => $index], 'order', -2147483648, 2147483647),
            ]);
        }
    }

    private static function saveProductVariants(PDO $pdo, int $productId, mixed $variants, bool $updating): void
    {
        if (!is_array($variants) || !array_is_list($variants) || count($variants) < 1 || count($variants) > 100) {
            throw new HttpException('Variants must be a list of 1 to 100 records.', 422);
        }

        $existingIds = [];
        if ($updating) {
            $query = $pdo->prepare(            'SELECT id, stock FROM producto_variantes WHERE producto_id = :id FOR UPDATE');
            $query->execute(['id' => $productId]);
            $existingRows = $query->fetchAll();
            $existingIds = array_map(static fn (array $row): int => (int) $row['id'], $existingRows);
            $existingStocks = [];
            foreach ($existingRows as $row) {
                $existingStocks[(int) $row['id']] = (int) $row['stock'];
            }
        }
        $seenIds = [];
        $insert = $pdo->prepare(
            'INSERT INTO producto_variantes
             (producto_id, sku, nombre, atributos, precio, stock, stock_minimo, estado)
             VALUES (:product_id, :sku, :name, :attributes, :price, :stock, :minimum_stock, 1)'
        );
        $update = $pdo->prepare(
            'UPDATE producto_variantes SET sku = :sku, nombre = :name, atributos = :attributes,
             precio = :price, stock_minimo = :minimum_stock, estado = 1
             WHERE id = :id AND producto_id = :product_id'
        );

        foreach ($variants as $variant) {
            if (!is_array($variant)) {
                throw new HttpException('Each variant must be an object.', 422);
            }
            $attributes = $variant['attributes'] ?? new \stdClass();
            if (
                (!is_array($attributes) && !$attributes instanceof \stdClass)
                || (is_array($attributes) && $attributes !== [] && array_is_list($attributes))
            ) {
                throw new HttpException('Variant attributes must be an object.', 422);
            }
            if ($attributes === []) {
                $attributes = new \stdClass();
            }
            $price = Input::moneyCents($variant, 'price', false);
            $values = [
                'product_id' => $productId,
                'sku' => Input::string($variant, 'sku', 80),
                'name' => Input::string($variant, 'name', 150),
                'attributes' => json_encode($attributes, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
                'price' => $price === null ? null : Input::moneyFromCents($price),
            ];
            $minimumStock = Input::integer($variant + ['minimum_stock' => 0], 'minimum_stock', 0, 4294967295);
            if (isset($variant['id'])) {
                $variantId = Input::id($variant, 'id');
                if (!$updating || !in_array($variantId, $existingIds, true) || isset($seenIds[$variantId])) {
                    throw new HttpException('Variant id does not belong to this product.', 422);
                }
                if (isset($variant['stock']) && Input::integer($variant, 'stock', 0, 4294967295) !== $existingStocks[$variantId]) {
                    throw new HttpException('Variant stock must be changed through the inventory adjustment endpoint.', 422);
                }
                $seenIds[$variantId] = true;
                $update->execute($values + ['minimum_stock' => $minimumStock, 'id' => $variantId]);
            } else {
                $initialStock = Input::integer($variant + ['stock' => 0], 'stock', 0, 4294967295);
                if ($initialStock !== 0) {
                    throw new HttpException('Set initial variant stock through the inventory adjustment endpoint.', 422);
                }
                $insert->execute($values + ['stock' => 0, 'minimum_stock' => $minimumStock]);
            }
        }

        if ($updating) {
            $removedIds = array_values(array_diff($existingIds, array_keys($seenIds)));
            if ($removedIds !== []) {
                $placeholders = [];
                $parameters = ['product_id' => $productId];
                foreach ($removedIds as $index => $removedId) {
                    $key = 'removed_' . $index;
                    $placeholders[] = ':' . $key;
                    $parameters[$key] = $removedId;
                }
                $statement = $pdo->prepare(
                    'UPDATE producto_variantes SET estado = 0 WHERE producto_id = :product_id AND id IN ('
                    . implode(',', $placeholders) . ')'
                );
                $statement->execute($parameters);
            }
        }
    }

    private static function slug(string $slug): string
    {
        if (!preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/D', $slug)) {
            throw new HttpException('Slug must contain lowercase letters, numbers and single hyphens.', 422);
        }

        return $slug;
    }

    /** @param list<array<string, mixed>> $variants
     *  @return list<array<string, mixed>>
     */
    private static function decodeVariantAttributes(array $variants): array
    {
        foreach ($variants as &$variant) {
            $attributes = $variant['atributos'] ?? null;
            $variant['atributos'] = is_string($attributes)
                ? json_decode($attributes, false, 512, JSON_THROW_ON_ERROR)
                : new \stdClass();
        }

        return $variants;
    }

    private static function imagePath(?string $path): ?string
    {
        if ($path === null || $path === '') {
            return null;
        }
        if (
            str_starts_with($path, '/')
            && !str_starts_with($path, '//')
            && !str_contains($path, '..')
            && !str_contains($path, '\\')
        ) {
            return $path;
        }
        if (filter_var($path, FILTER_VALIDATE_URL) !== false && parse_url($path, PHP_URL_SCHEME) === 'https') {
            return $path;
        }

        throw new HttpException('Image paths must be relative URLs or HTTPS URLs.', 422);
    }

    private static function decimal(mixed $value, string $field, int $scale, bool $nullable): ?string
    {
        if (($value === null || $value === '') && $nullable) {
            return null;
        }
        if (!is_string($value) && !is_int($value)) {
            throw new HttpException("Field {$field} must be a decimal number.", 422);
        }
        $pattern = '/^(0|[1-9][0-9]{0,6})(?:\.([0-9]{1,' . $scale . '}))?$/D';
        if (!preg_match($pattern, (string) $value)) {
            throw new HttpException("Field {$field} has an invalid decimal format.", 422);
        }

        return (string) $value;
    }

    private static function requireAffectedOrExisting(int $affected, string $table, int $id): void
    {
        if ($affected > 0) {
            return;
        }
        $statement = Connection::get()->prepare("SELECT 1 FROM {$table} WHERE id = :id");
        $statement->execute(['id' => $id]);
        if ($statement->fetchColumn() === false) {
            throw new HttpException('Resource not found.', 404);
        }
    }

    private static function queryInteger(?string $value, int $default, int $max): int
    {
        if ($value === null || $value === '') {
            return $default;
        }
        $parsed = filter_var($value, FILTER_VALIDATE_INT);
        if ($parsed === false || $parsed < 1 || $parsed > $max) {
            throw new HttpException('Invalid pagination value.', 422);
        }

        return $parsed;
    }
}
