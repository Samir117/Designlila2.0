# Prueba temporal de hosting para Angular

Esta carpeta es una prueba de entrega estática, no el frontend de la tienda. No requiere Node.js, PHP ni base de datos en el servidor. No copies credenciales aquí.

## Publicación temporal

El subdominio disponible es `dev.designlila.com` y su document root es `/home/designli/dev.designlila.com/backend/public`, donde ya funciona la API. El deploy de cPanel publica esta prueba de forma aislada en `frontend-hosting-probe/`; no reemplaces el `index.php` ni el `.htaccess` existentes.

1. En cPanel Git Version Control, ejecuta **Update from Remote** y luego **Deploy HEAD Commit**.
2. Abre `https://dev.designlila.com/frontend-hosting-probe/` y confirma que todos los checks aparecen como correctos.
3. Abre directamente `https://dev.designlila.com/frontend-hosting-probe/prueba-ruta-seo/` y recarga la página. Debe devolver la prueba, mostrar esa ruta y pasar el check de ruta profunda. Esto comprueba entrega de estáticos y fallback Apache/LiteSpeed en una subruta.
4. Después de validar, solicita retirar la carpeta `frontend-hosting-probe` del document root. La página lleva `noindex`, pero es una prueba temporal.

Si la primera URL responde pero la ruta profunda da 404, contacta al hosting para confirmar que `mod_rewrite` y las reglas `.htaccess` dentro de subdirectorios están habilitados. Si aparece error 500, no cambies el `.htaccess` principal de la API; pide al proveedor el formato compatible con LiteSpeed.

## SEO y límites de la prueba

La prueba solo confirma que el hosting puede servir archivos estáticos y enrutar una URL profunda dentro de una subruta a `index.html`; no valida SSR, prerender, las rutas del catálogo ni la integración con la API. El H1 no debe considerarse compatible con Node.js/SSR en runtime hasta que el proveedor lo confirme. Si se usa este plan, las páginas públicas deberán tener una estrategia SEO que no dependa de un servidor Node persistente (por ejemplo, prerender/build estático por URL o renderizado desde el backend PHP).
