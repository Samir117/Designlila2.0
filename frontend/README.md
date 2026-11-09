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
- Los colores, tipografía, anchos y radios se centralizan como custom properties CSS en `src/styles.css`. La paleta del diseño usa `--color-background: #fff` (blanco), `--color-lilac-light: #f6d7ff` (lila claro), `--color-accent: #7e3399` (morado), `--color-heading: #765fa7` (morado claro) y `--color-body: #1e1e1e` (neutral); `--color-border` deriva del neutral al 20 %. Estos valores se toman de la referencia visual. Los componentes deben consumir tokens; colores semánticos (`--color-success`, `--color-danger`) se reservan para mensajes de estado.
- Importa componentes reutilizables desde `shared/ui/index.ts`; usa `<button appActionButton>` para acciones y `<app-form-field>` para campos con etiqueta, ayuda y error.

#### Botones y campos reutilizables

`ActionButton` es una directiva standalone aplicada al botón nativo, de modo que siguen funcionando de forma natural `type="submit"`, `disabled`, eventos y formularios:

```html
<button appActionButton type="button" variant="primary">Explorar colección</button>
<button appActionButton type="button" variant="outline">Conoce nuestros productos</button>
<button appActionButton type="button" variant="soft">Ver la tienda</button>
<button appActionButton type="submit" [busy]="saving" [disabled]="saving">Guardar</button>
```

Variantes disponibles: `primary` (morado), `outline` (blanco con borde y texto morados) y `soft` (lila claro). El estado ocupado comunica `aria-busy`; el formulario debe enlazar `disabled` para evitar envíos repetidos.

`FormField` envuelve un control nativo y acepta input, select y textarea. Admite `label`, `required` (indicador visual), `hint` y `error`; el atributo nativo `required` sigue siendo necesario para validación HTML:

```html
<app-form-field label="Nombre para personalizar" [required]="true" hint="Como quieres que aparezca impreso">
  <input name="name" required maxlength="120" placeholder="Ej: Laura Gómez" [(ngModel)]="name" />
</app-form-field>

<app-form-field label="Color de portada">
  <select name="cover" [(ngModel)]="cover">
    <option value="lila">Lila pastel</option>
    <option value="blanco">Blanco</option>
  </select>
</app-form-field>

<app-form-field label="Frase o detalle especial" [error]="messageError">
  <textarea name="message" rows="4" [(ngModel)]="message"></textarea>
</app-form-field>

<app-form-field label="¿Para cuándo lo necesitas?">
  <input name="date" type="date" [(ngModel)]="date" />
</app-form-field>
```

Los controles permanecen HTML nativo y son compatibles con `ngModel` o Reactive Forms. Cada componente standalone debe importar `ActionButton` y/o `FormField` desde `shared/ui`; los tokens y estados de foco están centralizados, y los estilos de campo aceptan los inputs nativos, select, textarea y fecha. Las etiquetas envuelven el control para asociarlo accesiblemente; el mensaje de error usa `role="alert"`.

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

Para generar el HTML que se sirve en el subdominio:

```powershell
npm run build                 # SSG de pruebas: noindex, no sitemap
npm run build:production      # Build indexable; solo después de aprobar contenido y SEO
```

Ambos comandos generan HTML estático bajo `frontend/deploy/browser`. Angular genera HTML por **ruta**, no un archivo HTML por cada componente: las plantillas de los componentes están en `template` dentro de archivos `.ts`; el HTML prerenderizado es un artefacto compilado. El prerender obtiene slugs de productos y categorías activas de la API configurada para ese entorno; al renderizar cada página, Angular vuelve a cargar sus datos para incluir contenido y metadatos en el HTML.

El build de pruebas es el predeterminado, emite `noindex`/`X-Robots-Tag` y no crea sitemap. El build de producción habilita `index, follow` solo en páginas que tienen contenido real y produce `robots.txt` y `sitemap.xml` a partir de los HTML indexables. Si la página principal no tiene catálogo indexable o la API falla, el build de producción se detiene. En el build de staging actual se generó `/` solamente: como la API de pruebas no tiene productos ni categorías activas, no se crearon HTML de sus rutas.

El build estático se versiona para que cPanel sirva los archivos sin Node.js. El despliegue configurado copia el build de pruebas al document root del subdominio y no apunta al dominio principal. No publiques un build de producción hasta que autorices el cambio y revisemos las URLs y redirecciones históricas.

## SEO y hosting

- Las rutas iniciales reflejan los patrones históricos: `/product/:slug/`, `/product-category/:slug/`, `/category/:slug/` y contenido/artículos en `/:slug/`. No renombrar slugs ni publicar rutas no aprobadas mientras el mapeo SEO esté pausado.
- `SeoService` administra título, descripción, canonical absoluto en `https://www.designlila.com/`, Open Graph, Twitter Cards, robots y JSON-LD. El build de staging mantiene `noindex`; el de producción solo indexa páginas marcadas como contenido real.
- La aplicación usa rutas desde `/`, iguales a las previstas para el dominio principal; no se añade ningún prefijo temporal. Al probar luego en el subdominio, las rutas de tienda conservarán ese mismo formato.
- Node se usa localmente para compilar; el hosting H1 sirve los archivos estáticos. `.cpanel.yml` copia el build a `backend/public`, donde `.htaccess` dirige `/api/` al front controller PHP y las rutas de Angular a `index.html`.
- El despliegue cPanel apunta únicamente a `/home/designli/dev.designlila.com`. El paso de **Update from Remote** / **Deploy HEAD Commit** es manual; hacer push no despliega por sí solo. No cambiar el destino al dominio principal sin autorización expresa.
- El proyecto ya tiene configurado SSG para `/`, productos activos y categorías de producto con contenido. La cantidad de archivos HTML depende de las rutas y datos que el build haya podido prerenderizar; confirmar el inventario real en cada build. El resto de rutas —blog, páginas raíz históricas, login/admin y checkout— no se incluye en el sitemap. Las páginas de blog y contenido raíz siguen siendo marcadores hasta migrar su contenido; no se deben indexar como si fueran artículos reales.
- Los slugs de producto y categoría se obtienen del catálogo activo de la API. El inventario recibido contiene 160 URLs de producto y 30 categorías históricas, pero la API de pruebas actualmente responde con cero productos y cero categorías. Antes del lanzamiento SEO hay que migrar los productos/categorías reales, comparar todos los slugs con la matriz histórica y configurar las redirecciones 301 aprobadas en `redirecciones_seo`.
- `npm run build` es seguro para staging y mantiene `noindex`. `npm run build:production` genera metadatos indexables, `robots.txt` y sitemap de las páginas que efectivamente tienen HTML y contenido. Ese build no se despliega desde cPanel y no debe usarse en staging.

## Administración de catálogo

- `/admin/login` autentica contra la API PHP con sesión HttpOnly y CSRF. Productos, categorías y dashboard requieren sesión; la API vuelve a validar los permisos de cada operación.
- En `/admin/products` se pueden crear, consultar, editar y desactivar productos; ajustar precio regular y precio de oferta, categoría, texto, imagen por URL/ruta, producto destacado y metadatos SEO. En `/admin/categories` se pueden crear, editar y desactivar categorías.
- Los cambios se guardan por API en MariaDB: cambiar datos no requiere volver a compilar ni desplegar el frontend. La web consume el catálogo activo de la API.
- El precio de oferta actual no tiene fechas de inicio/fin ni programación automática. Las imágenes se asocian mediante URL HTTPS o ruta del sitio; la carga de archivos desde el ordenador no está implementada.
- En productos con variantes se pueden gestionar nombre, SKU, atributos, precio y stock mínimo; el stock inicial y los movimientos se deben registrar por el flujo de inventario, no editando el producto.
- Desactivar es una baja lógica; no se elimina físicamente el registro. Las páginas estáticas prerenderizadas y el sitemap SEO no se regeneran por un cambio en el catálogo. **Pendiente obligatorio antes de producción/indexación:** resolver y verificar cómo actualizar HTML, metadatos, canonicales y sitemap cuando se creen, editen, cambien de precio o desactiven productos/categorías desde el panel, sin una subida manual ni redeploy frontend. Evaluar una publicación automática segura o SSR compatible con el hosting y demostrar que los cambios producen HTML rastreable actualizado. Mantener `noindex` en staging y no activar indexación hasta superar esta validación.

## Alcance actual

Esta es una base arquitectónica, no una tienda terminada. La portada, categorías y detalle consultan el catálogo de la API; el panel incluye acceso administrativo y CRUD de productos y categorías. Las páginas de artículos y contenido raíz siguen siendo marcadores. Falta integrar carrito, checkout, carga de imágenes, programación de promociones, migrar el catálogo y conciliar el inventario SEO histórico.
