# Design Lila API

Backend REST de Design Lila para PHP 8.4 y MariaDB 10.11.

## Estado actual

Esta primera etapa contiene únicamente la infraestructura base. No crea ni modifica tablas. El endpoint de salud de base de datos ejecuta únicamente `SELECT 1`.

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

Para comprobar la conexión PDO/MariaDB con las variables de `backend/.env`, abre:

```text
GET /api/v1/health/database
```

Una conexión correcta responde HTTP 200 con `"database":"connected"`. Si falla, responde HTTP 503 sin exponer credenciales ni detalles internos; el error completo queda en el log PHP del servidor.

## Despliegue con Git Version Control de cPanel

El archivo `.cpanel.yml` despliega el backend a `/home/designli/dev.designlila.com/backend` cuando se usa **Deploy HEAD Commit** desde cPanel. La ruta corresponde al directorio del subdominio mostrado en el Administrador de archivos.

1. Haz commit y push de los cambios del proyecto a la rama `main` en GitHub.
2. En cPanel → **Git Version Control**, clona el repositorio en una carpeta fuera de `dev.designlila.com`, por ejemplo `/home/designli/repositories/Designlila2.0`. Si el repositorio es privado, sigue el método de autenticación GitHub que ofrezca cPanel; no pongas tokens en archivos del proyecto.
3. Configura `dev.designlila.com` para usar `/home/designli/dev.designlila.com/backend/public` como **Document Root**, de modo que el código y `.env` queden fuera de la raíz pública.
4. En Git Version Control, selecciona **Update from Remote** y luego **Deploy HEAD Commit**.
5. Crea `/home/designli/dev.designlila.com/backend/.env` con las credenciales de producción, basándote en `.env.example`. El `.env` no se versiona y no se reemplaza durante el despliegue.
6. Prueba `https://dev.designlila.com/api/v1/health`.

El despliegue se inicia desde cPanel después de cada push a GitHub; no se ejecuta automáticamente al hacer push.

## Próximas etapas

- Validación de configuración y conexión PDO.
- Autenticación administrativa y permisos.
- Catálogo de productos y variantes.
- Pedidos, pagos e inventario transaccional.
