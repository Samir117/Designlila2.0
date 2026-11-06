# Design Lila API

Backend REST de Design Lila para PHP 8.4 y MariaDB 10.11.

## Estado actual

Esta primera etapa contiene únicamente la infraestructura base. No crea ni modifica tablas y no ejecuta conexiones a MariaDB desde el endpoint de salud.

## Configuración local

1. Copiar `.env.example` como `.env` dentro de `backend`.
2. Completar `DB_HOST`, `DB_NAME`, `DB_USER` y `DB_PASSWORD` con los datos de la base de datos existente. En cPanel, el nombre de la base y el usuario suelen llevar el prefijo de la cuenta; confirma los valores en el panel de hosting.
3. Verificar que PHP tenga habilitado `pdo_mysql`. Si Composer está instalado, ejecutar `composer dump-autoload` desde `backend`.
4. Desde PowerShell, probar la conexión con:

   ```powershell
   php .\scripts\test-connection.php
   ```

   Una conexión correcta muestra `CONNECTION_OK` y `TEST=SELECT 1 OK`. Si falla, revisar los valores del `.env`, que MariaDB permita conexiones desde el equipo y que PHP tenga habilitado `pdo_mysql`.
5. Usar `backend/public` como document root del servidor web.

El archivo `.env` contiene credenciales y está excluido de Git. No pegues ni subas ese archivo; comparte solo los mensajes de error, ocultando nombres de usuario, hosts privados y otros datos sensibles.

El endpoint inicial es:

```text
GET /api/v1/health
```

## Próximas etapas

- Validación de configuración y conexión PDO.
- Autenticación administrativa y permisos.
- Catálogo de productos y variantes.
- Pedidos, pagos e inventario transaccional.
