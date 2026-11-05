# Design Lila API

Backend REST de Design Lila para PHP 8.4 y MariaDB 10.11.

## Estado actual

Esta primera etapa contiene únicamente la infraestructura base. No crea ni modifica tablas y no ejecuta conexiones a MariaDB desde el endpoint de salud.

## Configuración local

1. Copiar `.env.example` como `.env`.
2. Completar las credenciales de la base de datos existente.
3. Ejecutar `composer dump-autoload` si Composer está disponible.
4. Usar `backend/public` como document root del servidor web.

El endpoint inicial es:

```text
GET /api/v1/health
```

## Próximas etapas

- Validación de configuración y conexión PDO.
- Autenticación administrativa y permisos.
- Catálogo de productos y variantes.
- Pedidos, pagos e inventario transaccional.

