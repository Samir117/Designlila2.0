# Prueba temporal de Angular en hosting compartido

Esta carpeta contiene dos pruebas aisladas: una estática simple y una aplicación real compilada con Angular 21. No son el frontend de la tienda. No requieren Node.js, PHP ni base de datos en el servidor una vez compiladas. No copies credenciales aquí.

## Preparar el build Angular

Desde `frontend-hosting-probe/angular-app` ejecuta localmente:

```powershell
npm ci
npm test -- --watch=false
npm run build
```

El build se genera en `frontend-hosting-probe/angular-dist`. Ese artefacto estático se incluye en el repositorio para el despliegue de prueba desde cPanel; no se compila en el servidor.

## Publicar y probar

El subdominio `dev.designlila.com` tiene como document root `/home/designli/dev.designlila.com/backend/public`, donde ya funciona la API. `.cpanel.yml` despliega la prueba en una subcarpeta aislada y no reemplaza el `index.php` ni el `.htaccess` principal.

1. Confirma que el commit que contiene el build compilado está en GitHub.
2. En cPanel Git Version Control, ejecuta **Update from Remote** y luego **Deploy HEAD Commit**.
3. Abre `https://dev.designlila.com/angular-hosting-probe/`. Debe mostrarse la página de prueba Angular.
4. Abre `https://dev.designlila.com/angular-hosting-probe/ruta-prueba-seo` directamente y recarga. Debe seguir mostrando la página de ruta profunda de Angular.
5. Si ambas páginas funcionan, H1 sirve el build Angular estático y permite rutas profundas de cliente-side routing en esta subcarpeta. Luego retira `angular-hosting-probe` del document root cuando ya no se necesite.

La prueba anterior sigue disponible en `https://dev.designlila.com/frontend-hosting-probe/` y verifica solo archivos estáticos simples.

Si el build no carga, hay error de recursos/base path. Si la raíz carga pero la URL profunda da 404, contacta al hosting para confirmar que `mod_rewrite` y `.htaccess` dentro de subdirectorios están habilitados. Si aparece error 500, no cambies el `.htaccess` principal de la API; pide al proveedor el formato compatible con LiteSpeed.

## SEO y límites de la prueba

La app lleva `noindex` y es solo técnica. Esta prueba confirma ejecución de Angular en el navegador, entrega de assets y fallback de ruta profunda; **no** confirma SSR, prerender ni SEO de páginas de la tienda. Tampoco demuestra que H1 permita un proceso Node persistente: el build se crea localmente y el hosting solo sirve archivos estáticos. Antes de indexar páginas de producto habrá que implementar y validar una estrategia SEO que entregue metadatos y contenido rastreables.
