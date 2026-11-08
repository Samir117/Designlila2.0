# Prueba temporal de hosting para Angular

Esta carpeta es una prueba de entrega estática, no el frontend de la tienda. No requiere Node.js, PHP ni base de datos en el servidor. No copies credenciales aquí.

## Publicación temporal

1. Crea/selecciona el subdominio `devi.designlila.com` en cPanel y confirma que su document root esté vacío o que puedas respaldar lo que contiene.
2. Sube **el contenido** de esta carpeta al document root del subdominio. Deben quedar allí `.htaccess`, `index.html`, `probe.css` y `probe.js`.
3. Abre `https://devi.designlila.com/` y confirma que todos los checks aparecen como correctos.
4. Abre directamente `https://devi.designlila.com/prueba-ruta-seo/` y recarga la página. Debe devolver la misma prueba, mostrar esa ruta y pasar el check de ruta profunda. Esto comprueba el fallback Apache/LiteSpeed necesario para rutas Angular.
5. Retira inmediatamente los cuatro archivos de la prueba, o reemplázalos cuando se inicie el despliegue real. La página lleva `noindex`, pero no se debe dejar publicada.

Si la raíz responde pero la ruta profunda da 404, guarda una copia del error y contacta al hosting para confirmar que `mod_rewrite` y las reglas de `.htaccess` están habilitados. Si aparece error 500, retira `.htaccess` y solicita al proveedor el formato de reglas compatible con LiteSpeed.

## SEO y límites de la prueba

La prueba solo confirma que el hosting puede servir archivos estáticos y enrutar una URL profunda a `index.html`; no valida SSR, prerender, las rutas del catálogo ni la integración con la API. El H1 no debe considerarse compatible con Node.js/SSR en runtime hasta que el proveedor lo confirme. Si se usa este plan, las páginas públicas deberán tener una estrategia SEO que no dependa de un servidor Node persistente (por ejemplo, prerender/build estático por URL o renderizado desde el backend PHP).
