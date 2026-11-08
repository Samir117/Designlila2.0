# Design Lila API

API REST para la tienda y su panel administrativo. Está implementada para PHP 8.4 y MariaDB 10.11, utiliza PDO y trabaja sobre las tablas existentes: no ejecuta migraciones ni modifica el esquema.

## Requisitos y configuración

- PHP 8.4 con `pdo_mysql`, `mbstring` y `fileinfo`.
- Acceso a la base de datos MariaDB existente.
- Document root del sitio apuntando a `backend/public`; conserva `.env` y `storage` fuera de la raíz pública.

En local, copia `.env.example` a `.env` dentro de `backend` y completa las variables de base de datos. Para desarrollo por HTTP local, configura `SESSION_COOKIE_SECURE=false`; en producción HTTPS debe permanecer `true`.

```text
APP_DEBUG=false
APP_SETUP_TOKEN=
SESSION_COOKIE_SECURE=true

DB_HOST=localhost
DB_PORT=3306
DB_NAME=designli_database
DB_USER=
DB_PASSWORD=
DB_CHARSET=utf8mb4
```

No agregues `.env`, comprobantes, credenciales ni el volcado SQL a Git. Si Composer está instalado, ejecuta `composer dump-autoload` desde `backend`; sin `vendor/`, el punto de entrada usa el autoloader incluido.

Prueba local de conexión:

```powershell
php .\scripts\test-connection.php
```

Endpoints básicos:

```text
GET /api/v1/health
GET /api/v1/health/database
```

El segundo ejecuta `SELECT 1`; responde 200 si PDO se conecta y 503 si falla, sin enviar detalles de conexión al cliente.

## Inicialización del primer administrador

1. Genera un token aleatorio de al menos 32 caracteres, por ejemplo con `php -r "echo bin2hex(random_bytes(32)), PHP_EOL;"`, y guárdalo temporalmente en `APP_SETUP_TOKEN` del `.env`.
2. Comprueba que las filas iniciales incluyan el rol `Superadministrador`.
3. Solicita primero `GET /api/v1/auth/csrf` y conserva la cookie de sesión y el token CSRF.
4. Envía `POST /api/v1/auth/setup` con el token y los datos del administrador:

   ```json
   {
     "setup_token": "TOKEN_TEMPORAL",
     "name": "Administradora",
     "email": "admin@example.com",
     "password": "una-clave-larga-de-al-menos-12"
   }
   ```

5. Elimina `APP_SETUP_TOKEN` del `.env` inmediatamente después. El endpoint de configuración deja de estar disponible si el token no está configurado o ya existe una cuenta.

## Contrato HTTP

Todas las respuestas JSON siguen este formato:

```json
{"success": true, "data": {}}
```

Los errores usan `success: false` y `error.message`; algunas respuestas incluyen `error.details`. Se usan códigos HTTP como 401 (sesión), 403 (CSRF/permisos), 404, 409 (conflicto de estado o datos), 422 (validación) y 503 (servicio temporalmente no disponible). Los montos se envían y devuelven como cadenas decimales para evitar errores de precisión.

La tienda y el API deben compartir origen en producción. Las sesiones administrativas usan cookie `HttpOnly`, `SameSite=Strict` y Secure en HTTPS; no se habilita CORS abierto.

### Sesión administrativa y CSRF

1. `GET /api/v1/auth/csrf` crea una sesión y devuelve `{ "token": "..." }`.
2. Enviar ese token en `X-CSRF-Token` al iniciar sesión y en cada solicitud administrativa `POST`, `PUT`, `PATCH` o `DELETE`. Mantener la cookie de sesión en las solicitudes (`credentials: 'include'` en `fetch`).
3. `POST /api/v1/auth/login` devuelve el usuario y un token CSRF nuevo; reemplaza el token anterior.
4. `GET /api/v1/auth/me` devuelve usuario y permisos. `POST /api/v1/auth/logout` requiere CSRF y cierra la sesión.

No se requiere CSRF para solicitudes de lectura (`GET`). El usuario obtiene acceso a cada módulo por los permisos existentes en las tablas de roles/permisos.

## Endpoints públicos

| Método y ruta | Uso |
|---|---|
| `GET /api/v1/config` | Datos públicos de tienda y tarifa configurada |
| `GET /api/v1/categories` | Categorías activas |
| `GET /api/v1/products` | Catálogo paginado |
| `GET /api/v1/products/{slug}` | Detalle, imágenes, variantes y disponibilidad |
| `POST /api/v1/orders` | Checkout de invitado |
| `POST /api/v1/payments/report` | Reportar pago y adjuntar comprobante |

El catálogo admite `page`, `per_page` (máximo 100), `category` (slug), `search`, `featured=1` y `sort` (`newest`, `name`, `price_asc`, `price_desc`). La respuesta paginada contiene `items` y `pagination`. `in_stock` es un indicador booleano numérico; no revela las unidades disponibles.
Los registros de catálogo se devuelven con los nombres de columna existentes en español (`nombre`, `precio`, `categoria_id`, etc.); los campos agregados incluyen `categoria`, `in_stock` e `imagen`. Los detalles incluyen `images` y `variants`; `atributos` de cada variante se serializa como objeto JSON.

### Checkout

El servidor vuelve a consultar precio, estado y disponibilidad; nunca confía en precios enviados por el navegador. El coste del envío se lee de `configuracion.costo_envio_base`. Configúralo en el panel antes de habilitar pedidos; si falta o no es válido, el checkout responde 503.
Aunque `impuesto_porcentaje` se entrega en la configuración pública y puede administrarse, actualmente no se suma al total: el esquema existente no conserva la tasa aplicada al pedido. Mantén ese valor en `0` hasta acordar la regla fiscal y cómo guardar su snapshot.

Ejemplo de `POST /api/v1/orders`:

```json
{
  "customer": {
    "name": "Ana",
    "last_name": "Pérez",
    "email": "ana@example.com",
    "phone": "3001234567",
    "document": "123456"
  },
  "shipping": {
    "recipient": "Ana Pérez",
    "address": "Calle 10 # 20-30",
    "complement": "Apto 2",
    "city": "Bogotá",
    "department": "Cundinamarca",
    "postal_code": "110111",
    "country": "Colombia"
  },
  "items": [
    {"product_id": 12, "quantity": 1},
    {"product_id": 24, "variant_id": 31, "quantity": 2}
  ]
}
```

Los artículos deben tener entre 1 y 99 unidades (máximo 30 líneas). La respuesta contiene referencia, desglose y total calculados por el servidor. El pedido crea una instantánea de nombres, SKU, precio y variante. El stock **no se descuenta al crear el pedido**; se valida otra vez y se descuenta transaccionalmente al aprobar el pago. No se reserva stock entre esos dos momentos.

### Reporte de pago

`POST /api/v1/payments/report` recibe `multipart/form-data`: `order_reference`, `email`, `method`, opcional `transaction_reference` y archivo `receipt`. El comprobante debe ser PDF, JPEG, PNG o WebP de hasta 5 MB. Se almacena fuera de `public`; solo un administrador con permiso `pagos.validar` puede descargarlo.

## Endpoints administrativos

Todos requieren sesión y permiso correspondiente. Las operaciones que cambian datos requieren también `X-CSRF-Token`.

| Método y ruta | Permiso / función |
|---|---|
| `GET /api/v1/admin/categories`, `POST /api/v1/admin/categories`, `PUT /api/v1/admin/categories/{id}`, `DELETE /api/v1/admin/categories/{id}` | `categorias.gestionar` |
| `GET /api/v1/admin/products`, `GET /api/v1/admin/products/{id}` | `productos.ver` |
| `POST /api/v1/admin/products` | `productos.crear` |
| `PUT /api/v1/admin/products/{id}` | `productos.editar` |
| `DELETE /api/v1/admin/products/{id}` | `productos.eliminar` |
| `GET /api/v1/admin/orders`, `GET /api/v1/admin/orders/{id}` | `pedidos.ver` |
| `POST /api/v1/admin/orders/{id}/status` | `pedidos.gestionar` |
| `GET /api/v1/admin/payments`, `POST /api/v1/admin/payments/{id}/approve`, `POST /api/v1/admin/payments/{id}/reject`, `GET /api/v1/admin/payments/{id}/receipt` | `pagos.validar` |
| `GET /api/v1/admin/shipments`, `PATCH /api/v1/admin/orders/{id}/shipping` | `envios.gestionar` |
| `GET /api/v1/admin/customers` | `clientes.ver` |
| `GET /api/v1/admin/inventory`, `GET /api/v1/admin/inventory/movements` | `inventario.gestionar` |
| `POST /api/v1/admin/inventory/adjustments` | `inventario.ajustar` |
| `GET /api/v1/admin/settings`, `PUT /api/v1/admin/settings` | `configuracion.gestionar` |
| `GET /api/v1/admin/metrics` | `metricas.ver` |
| `GET /api/v1/admin/roles`, `GET /api/v1/admin/users`, `POST /api/v1/admin/users`, `PUT /api/v1/admin/users/{id}` | `usuarios.gestionar` |

Los listados paginados aceptan `page` y `per_page` (máximo 100); pedidos, pagos y envíos también aceptan `status`, y los listados de productos/clientes/inventario admiten búsqueda según el módulo.

### Gestión de productos e inventario

Las categorías aceptan `name`, `slug`, `parent_id`, `description`, `image`, `meta_title`, `meta_description` y `order`.

Los productos aceptan `category_id`, `sku`, `name`, `slug`, `description`, `short_description`, `price`, `sale_price`, `stock_control`, `minimum_stock`, `weight`, `meta_title`, `meta_description`, `featured` e `inventory_mode`. Montos (`price`, `sale_price`) deben ser cadenas decimales. Los productos admiten inventario `simple` o `variantes`; el modo no se puede cambiar después de crear el producto. Para editar variantes se envía la lista `variants`, por ejemplo:

```json
{
  "sku": "CAMISA-01",
  "name": "Camisa",
  "slug": "camisa",
  "price": "50000.00",
  "inventory_mode": "variantes",
  "variants": [
    {
      "sku": "CAMISA-01-M",
      "name": "Talla M",
      "attributes": {"talla": "M", "color": "azul"},
      "price": null,
      "minimum_stock": 2
    }
  ],
  "images": [
    {"path": "/assets/camisa.jpg", "alt": "Camisa azul", "primary": true, "order": 0}
  ]
}
```

Al actualizar, las propiedades omitidas conservan su valor. Si se envía `images` o `variants`, esa lista reemplaza el conjunto actual; variantes omitidas se desactivan en vez de borrarse para preservar referencias históricas.

El stock inicial y los cambios posteriores se realizan con `POST /api/v1/admin/inventory/adjustments`, por ejemplo:

```json
{"product_id": 12, "delta": 5, "notes": "Inventario inicial"}
```

Para una variante, incluye `variant_id`. Cada ajuste registra el usuario y los valores anterior/nuevo en `movimientos_inventario`. El CRUD de productos no permite editar cantidades directamente.

La tarifa se configura con `PUT /api/v1/admin/settings`, por ejemplo `{ "key": "costo_envio_base", "value": "12000.00", "description": "Tarifa fija de envío" }`.

Los usuarios administrativos se crean con `POST /api/v1/admin/users` y `{ "name": "...", "email": "...", "password": "...", "role_ids": [1] }`. La contraseña debe tener entre 12 y 200 caracteres. `PUT /api/v1/admin/users/{id}` acepta los mismos campos, excepto que `password` puede omitirse para conservarla; `active` permite habilitar o deshabilitar la cuenta. No se permite editar la cuenta propia desde este módulo.

### Pedidos, pagos y envíos

- Transiciones de pedido: `POST /api/v1/admin/orders/{id}/status` con `{ "status": "...", "comment": "..." }`. Las transiciones inválidas responden 409.
- Aprobar/rechazar pago: `POST /api/v1/admin/payments/{id}/approve` o `/reject`, opcionalmente con `{ "comment": "..." }`.
- Al aprobar, el servidor valida y descuenta stock dentro de una transacción; si no hay existencias suficientes, no confirma el pago ni aplica movimientos parciales.
- Envío: `PATCH /api/v1/admin/orders/{id}/shipping` con `status` (`en_preparacion`, `listo_para_envio`, `enviado`, `entregado`) y opcionalmente `carrier`, `tracking_number` y `observations`. El estado del pedido y sus fechas de envío se actualizan juntos.

## Despliegue en cPanel

El archivo `.cpanel.yml` publica el backend y el build estático incluido del frontend en `/home/designli/dev.designlila.com/backend`; el document root del subdominio es `/home/designli/dev.designlila.com/backend/public`. El build se genera localmente en `frontend/deploy/browser` y se versiona para que cPanel no necesite Node.js. El directorio clonado en Git Version Control debe estar fuera del document root, por ejemplo `/home/designli/repositories/Designlila2.0`.

1. Desde `frontend`, ejecuta `npm ci` y `npm run build`; verifica que `frontend/deploy/browser/index.html` exista y conserve `noindex, nofollow`.
2. Haz commit y push a `main`, incluyendo el artefacto generado en `frontend/deploy/browser`.
3. En cPanel, ejecuta **Update from Remote** y luego **Deploy HEAD Commit**. El archivo de despliegue tiene como destino únicamente el entorno `dev.designlila.com`.
4. Conserva el document root del subdominio en `/home/designli/dev.designlila.com/backend/public`.
5. Crea `/home/designli/dev.designlila.com/backend/.env` con las credenciales de la base de pruebas; cPanel no lo reemplaza durante el despliegue.
6. Verifica `https://dev.designlila.com/`, `https://dev.designlila.com/admin/login`, `https://dev.designlila.com/api/v1/health` y `https://dev.designlila.com/api/v1/health/database`.

El push por sí solo no despliega los cambios: el paso de despliegue se inicia desde cPanel. No apuntes este flujo al dominio principal; cualquier despliegue de producción requiere autorización expresa de la usuaria.

## Tareas pendientes antes de migrar el SEO

- Recopilar y conservar las URLs actualmente indexadas, sus títulos y descripciones, `sitemap.xml`, `robots.txt`, canonicales, datos estructurados y reglas de redirección. No modificar slugs existentes durante este inventario.
- Recibir el frontend Angular y replicar ahí la generación de metadatos por página; los campos que devuelve esta API no publican por sí solos etiquetas SEO ni reemplazan sitemap o robots.
- Integrar y probar el uso de la tabla existente `redirecciones_seo` para responder con redirecciones permanentes 301, preservando las reglas actuales. La API aún no sirve esas redirecciones.
- Comparar las rutas antiguas y nuevas, probar sitemap/redirecciones y revisar una exportación de Search Console antes de publicar la migración.

Para empezar, comparte un ZIP o exportación sin credenciales que incluya las URLs y redirecciones actuales, sitemap, robots, configuración del plugin SEO y el código/rutas Angular relacionadas con SEO. No incluyas `.env`, credenciales, tokens ni accesos a Search Console. No desplegar la migración ni cambiar slugs hasta completar este inventario y validar el plan de redirecciones.
