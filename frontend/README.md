# Frontend Design Lila

Aplicación Angular 21 para la tienda pública y el panel administrativo. La API sigue en PHP y es la única capa que accede a MariaDB.

## Estructura

```text
frontend/
├── public/                         # Archivos estáticos copiados al build
├── src/
│   ├── app/
│   │   ├── core/                   # API, configuración y SEO singleton
│   │   ├── features/
│   │   │   ├── admin/              # Panel y acceso administrativo
│   │   │   ├── catalog/            # Tipos y acceso a catálogo
│   │   │   ├── not-found/
│   │   │   └── storefront/         # Tienda, producto, contenido y checkout
│   │   ├── shared/ui/              # Componentes reutilizables accesibles
│   │   ├── app.config.ts
│   │   └── app.routes.ts
│   ├── environments/               # API, canonical y control de indexación
│   ├── index.html
│   └── styles.css                  # Tokens, reset y accesibilidad global
└── deploy/browser/                 # Build estático versionado para cPanel
```

### Convenciones

- Componentes standalone agrupados por funcionalidad; rutas de tienda y administración se cargan de forma diferida.
- `core` contiene servicios singleton compartidos; las API de dominio viven junto a su funcionalidad (`features/catalog/data-access`).
- `shared/ui` es para componentes visuales genéricos, no para lógica de negocio.
- Tipado estricto, señales para el estado reactivo, imports pequeños y manejo visible de estados vacíos, carga y error.
- Las solicitudes HTTP usan el envoltorio `{ success, data }` de PHP y el mismo origen `/api/v1`; el navegador nunca se conecta a MariaDB.
- Los colores, tipografía, anchos y radios se centralizan como custom properties CSS en `src/styles.css`.

## Desarrollo local

Requiere Node.js compatible con Angular 21 (Node 20.19+, 22.12+ o 24+) y npm.

```powershell
cd backend
php -S 127.0.0.1:8000 -t public public/index.php
```

En una segunda terminal, inicia Angular:

```powershell
cd frontend
npm ci
npm start
```

Abre `http://localhost:4200`. El proxy de desarrollo envía `/api/` a la API PHP local, evitando configurar CORS. Si el puerto 4200 está ocupado, usa `npm start -- --port 4300`.

El `.env` local del backend debe tener sus variables de conexión completadas según [backend/README.md](../backend/README.md). No compartas ni subas ese archivo.

Para validar el build local cuando corresponda:

```powershell
npm run build
```

`npm run build` genera `frontend/deploy/browser`. Este build estático se versiona porque cPanel sirve archivos sin necesitar Node.js. El flujo de despliegue configurado copia los archivos al document root del subdominio de pruebas y no apunta al dominio principal.

## SEO y hosting

- Las rutas iniciales reflejan los patrones históricos: `/product/:slug/`, `/product-category/:slug/`, `/category/:slug/` y contenido/artículos en `/:slug/`. No renombrar slugs ni publicar rutas no aprobadas mientras el mapeo SEO esté pausado.
- `SeoService` administra título, descripción, canonical absoluto en `https://www.designlila.com/`, robots y JSON-LD. El acceso a indexación está deshabilitado (`indexingEnabled: false`) hasta aprobar los metadatos y rutas reales. El panel, checkout y páginas provisionales son `noindex`.
- La aplicación usa rutas desde `/`, iguales a las previstas para el dominio principal; no se añade ningún prefijo temporal. Al probar luego en el subdominio, las rutas de tienda conservarán ese mismo formato.
- Node se usa localmente para compilar; el hosting H1 sirve los archivos estáticos. `.cpanel.yml` copia el build a `backend/public`, donde `.htaccess` dirige `/api/` al front controller PHP y las rutas de Angular a `index.html`.
- El despliegue cPanel apunta únicamente a `/home/designli/dev.designlila.com`. El paso de **Update from Remote** / **Deploy HEAD Commit** es manual; hacer push no despliega por sí solo. No cambiar el destino al dominio principal sin autorización expresa.
- La estrategia final de SEO **no está completa**: la aplicación CSR por sí sola puede enviar un HTML inicial vacío. Antes de indexar la tienda se necesita prerender/SSG con contenido real y metadatos por URL (generados durante el build) o una arquitectura SSR compatible. Para URLs de producto dinámicas, el build debe obtener la lista de slugs publicados de la API; si no puede hacerlo, debe fallar claramente o excluir esas rutas del sitemap, nunca emitir páginas vacías como indexables.
- El `noindex` global de `src/index.html` permanece mientras el SEO esté pausado. No habilitar indexación ni retirar `noindex` hasta aprobar la estrategia de renderizado, canonicales, redirecciones, sitemap y Search Console.

## Alcance actual

Esta es una base arquitectónica, no una tienda terminada. La portada es una composición de inicio; el producto consulta el endpoint existente. Las páginas de categoría, contenido y administración son marcadores explícitos, no deben indexarse ni confundirse con funcionalidad final. Falta integrar carrito, checkout, autenticación/CSRF del panel y contenido SEO aprobado.
