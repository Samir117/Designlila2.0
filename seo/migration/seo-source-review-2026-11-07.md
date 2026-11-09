# Revisión de fuentes SEO para migración

Fecha de análisis: 7 de noviembre de 2026. Fuente canónica observada: `https://www.designlila.com/`. Este documento y la matriz son un inventario de planificación, no autorizan cambios en producción.

## Dominio de pruebas y dominio final

El frontend se probará en `https://devi.designlila.com/` y, al migrar, se servirá en el dominio SEO existente `https://www.designlila.com/`. `devi` es un subdominio distinto del host principal, aunque ambos pertenecen al dominio registrable `designlila.com`; no se deben tratar como la misma URL ni publicar el staging como sitio indexable.

- Configurar el host/base URL por entorno, sin codificar `devi` en rutas, canonicales, sitemap ni datos persistidos. El entorno de producción debe emitir URLs absolutas de `https://www.designlila.com/`.
- Proteger el staging con autenticación. Mientras esté en pruebas, añadir `noindex` global (idealmente también `X-Robots-Tag`) y no incluirlo en sitemaps públicos. No depender solo de `robots.txt`, porque bloquear el rastreo impide que Google vea el `noindex`.
- En QA, comprobar que al desplegar en producción las canonicales, enlaces absolutos, sitemap, Open Graph y datos estructurados usan el dominio principal. No redirigir `devi` al dominio principal hasta que el corte esté aprobado.
- El lanzamiento final conserva el dominio principal actual y migra la aplicación detrás de él; por tanto, se deben preservar primero las rutas y slugs históricos. El cambio de host en staging no sustituye el mapa de URLs ni la auditoría de las redirecciones de WordPress.

## Conclusión ejecutiva

Los archivos permiten iniciar el diseño y priorizar URLs de la migración, pero no constituyen una exportación completa de los metadatos por página ni de las redirecciones de WordPress. La decisión más segura para el primer lanzamiento es conservar las rutas actuales de páginas, artículos, productos y categorías, además del host `www`, HTTPS y las barras finales; no renombrar slugs ni cambiar prefijos durante la migración inicial.

Search Console muestra URLs con rendimiento que no figuran en los sitemaps entregados. Por eso el sitemap no debe ser la única fuente para decidir qué rutas conservar. `/shop/` tiene clics y actualmente redirige con HTTP 301 a la URL larga de tienda. Se debe preservar esa ruta alternativa y su destino o decidir, con pruebas, una única canonical nueva y redirecciones directas a ella.

No ejecutar un cambio de dominio, ruta o slug en producción hasta completar el mapa de URLs, obtener las reglas de redirección y validar el nuevo sitio en staging.

## Archivos recibidos y alcance

- Los nueve sitemaps XML describen 208 entradas y 207 URLs únicas. La URL larga de la tienda está duplicada entre el sitemap de páginas y el de productos.
- `robots.txt` contiene reglas específicas de WordPress/WooCommerce y referencia el índice `sitemap_index.xml`; se debe adaptar al nuevo sitio, no copiarlo ciegamente.
- `ajustes de yoast_Seo.txt` documenta ajustes globales de Yoast. Incluye Yoast SEO 28.5, `/%postname%/`, el host sin `www` en la opción `home_url`, sitemap y schema habilitados, y perfiles globales de título/schema para contenido. No contiene un inventario de campos SEO individuales por URL ni reglas de redirección.
- `Metadatos.csv` solo contiene la URL del sitemap, no metadatos de páginas.
- El ZIP `wordpress-seo.zip` es el código fuente del plugin Yoast SEO (2.441 entradas, aproximadamente 5,3 MB), no una copia/exportación del contenido, la base de datos, la configuración por página ni las redirecciones del sitio. No hace falta incorporarlo ni extraerlo en el repositorio de la aplicación.
- El ZIP `https___www.designlila.com_-Https-2026-11-07.zip` contiene un gráfico de estado HTTPS de 90 días. En la última fecha, 2026-11-06, reporta 37 URLs HTTPS y 0 URLs HTTP; `Tabla.csv` solo trae encabezados. Esto no es un inventario de las 207 URLs ni certifica que cada URL redirija correctamente.
- El ZIP `https___www.designlila.com_-Performance-on-Search-2026-11-07 (1).zip` contiene siete CSV de Search Console: gráfico, consultas, páginas, países, dispositivos, apariencia y filtros. El filtro declarado es búsqueda web y últimos 16 meses. El gráfico incluido tiene datos del 2026-03-18 al 2026-11-05 (233 días).

## Hallazgos de Search Console

`Páginas.csv` reporta 184 URLs, con una suma por filas de 613 clics y 18.763 impresiones. Las páginas de mayor relevancia incluyen:

| URL | Clics | Impresiones | Importancia para la migración |
|---|---:|---:|---|
| `/product/carpeta-prenatal-personalizada/` | 349 | 11.270 | Producto prioritario; mantener su ruta y contenido, imágenes, canonical y marcado de producto. |
| `/` | 93 | 608 | Conservar la portada y sus metadatos/canonical actuales. |
| `/product/bolsa-pvc-transparente/` | 17 | 88 | Conservar ruta de producto. |
| `/shop/` | 13 | 158 | Aunque no está en el sitemap, recibe clics; actualmente responde 301 a la ruta larga de tienda. |
| `/ideas-de-regalos-personalizados-en-barranquilla-para-sorprender-este-mes/` | 12 | 229 | Artículo con tráfico; preservar su URL raíz y contenido. |
| `/product/botellas-de-agua-personalizadas/` | 10 | 298 | Conservar ruta de producto. |
| `/product/caja-premium-cr7/` | 9 | 161 | Revisar en el mapa de rutas/productos y conservar o redirigir a un equivalente pertinente. |
| `/product/rompecabezas-sublimable/` | 9 | 43 | Revisar en el mapa de rutas/productos y conservar o redirigir a un equivalente pertinente. |
| `/regalos-para-emprendedores-ideas-utiles-y-creativas-que-si-aportan-valor/` | 8 | 1.066 | Artículo con impresiones; conservar contenido, URL y enlaces internos. |

La suma de clics/impresiones del CSV de páginas (613/18.763) no coincide con la suma del gráfico por día (602/16.792). Antes de usar agregados como KPI de referencia, se debe exportar de nuevo ambas vistas con el mismo rango y filtros, y guardar el total mostrado por Search Console. No inferir rendimiento nulo a partir de una URL ausente de este CSV: el reporte de páginas no es un informe de estado de indexación.

El informe contiene además 18 URLs con rendimiento que no aparecen en los sitemaps proporcionados. Incluye `/shop/`, un archivo de testimonios, URLs de eventos, productos/categorías y páginas de archivo paginadas. La matriz registra todas para que se decida individualmente qué se conserva, qué se excluye por no ser contenido público útil y qué necesita redirección. Algunas tienen muy pocas impresiones, pero no se deben redirigir automáticamente a la portada.

La apariencia en búsquedas suma 385 clics y 12.724 impresiones en “Fragmentos de productos”, y 39 clics y 94 impresiones en “Fichas de comerciantes”. El nuevo catálogo debe conservar datos de producto fiables y generar JSON-LD correcto de `Product`/`Offer` —precio, disponibilidad, imagen, SKU y moneda reales—, junto con organización y breadcrumbs. Validar el marcado actual y el nuevo con herramientas de resultados enriquecidos; no asumir que activar el schema de Yoast equivale a conservar todos los tipos/datos generados por WooCommerce.

Un CSV separado de cobertura de indexación, fechado entre agosto y octubre, termina con 161 indexadas y 37 sin indexar. El informe de incidencias lista 1 Soft 404, 27 “Rastreada: actualmente sin indexar” y 9 “Descubierta: actualmente sin indexar”, sin adjuntar las URLs afectadas ni su validación. Hace falta exportar los detalles de esas URLs y revisarlas antes de decidir destinos.

## Patrones actuales de URL

Conservar durante el primer lanzamiento:

- Host canonical `https://www.designlila.com/`. Las opciones de Yoast exportadas declaran `home_url = "https://www.designlila.com"` (sin `www`), mientras que los sitemaps y las páginas HTML inspeccionadas usan `www`. Verificar `www` como host final en Search Console y asegurar una sola redirección permanente desde el host no canonical.
- Barra final.
- Productos: `/product/{slug}/`.
- Categorías de productos: `/product-category/{slug}/`.
- Publicaciones: `/{slug}/`.
- Categorías de blog: `/category/{slug}/`.
- Marca: `/marca/{slug}/`.
- Página de tienda: conservar inicialmente la URL larga del sitemap; mantener `/shop/` como alias 301 según el comportamiento actual o acordar una única canonical nueva y dirigir ambas rutas en un solo salto.
- Canonicalizar las formas URL codificada y Unicode de slugs históricos sin alterar accidentalmente el recurso. No normalizar slugs con emoji o numéricos sin confirmar la equivalencia.

Para URLs de contenido nuevo, utilizar slugs cortos, descriptivos, estables, en minúsculas y separados por guiones. Esta buena práctica no justifica renombrar en bloque URLs históricas que ya reciben enlaces o tráfico. Si una página antigua debe cambiar de ruta, registrar origen → equivalente exacto, hacer una sola redirección 301 server-side, actualizar enlaces internos/canonical y retirar la URL antigua del sitemap nuevo.

Las rutas de artículos en la raíz y las páginas estáticas pueden compartir el mismo patrón. La nueva aplicación debe resolver explícitamente cada URL histórica y evitar colisiones ambiguas entre tipos de contenido. Las variantes de producto deben usar la canonical del producto principal, salvo que exista una estrategia de contenido indexable independiente aprobada.

## Metadatos, directivas y sitemap

- Mantener por URL el título, descripción y canonical individual que realmente publica Yoast; la configuración general no reemplaza los valores específicos.
- Generar desde el primer HTML rastreable (SSR o prerender según las capacidades del frontend) `title`, descripción, canonical absoluto, `robots`, Open Graph/Twitter y JSON-LD. No depender solamente de cambios de metadatos ejecutados tras cargar JavaScript en el navegador.
- Recuperar el estado individual `index/noindex`, canonical, títulos, descripciones, imágenes sociales y tipo de schema de cada contenido.
- El sitemap nuevo debe listar solo URLs canonicales con respuesta 200 y habilitadas para indexación. Corregir la entrada duplicada de tienda; excluir plantillas de Elementor, archivos funcionales/noindex y páginas de resultados internos según una política validada.
- El sitemap entregado contiene carrito, checkout, `store-manager`, autor y plantillas de header/footer. No decidir su destino solamente por presencia en el sitemap: revisar estado/canonical, URLs de Search Console, enlaces y función. Normalmente carrito, checkout y administración no deben indexarse ni aparecer en el sitemap; su funcionalidad no se elimina por eso.
- Revalidar la configuración de marca: la exportación dice `website_name = "Designlila"`, `company_name = "Desing Lila"` y `company_alternate_name = "DSL"`. En HTML hay una descripción/logo de organización. Confirmar con la propietaria el nombre legal y comercial correcto antes de unificar el schema para no propagar el error tipográfico.
- Adaptar `robots.txt`, conservar el sitemap final y no bloquear por `robots.txt` URLs que Google aún necesita rastrear para ver una redirección o una etiqueta `noindex`.

## Faltantes antes del mapa final

1. Exportar de Search Console las URLs específicas de Soft 404, “Rastreada: actualmente sin indexar” y “Descubierta: actualmente sin indexar”, junto con inspección/estado de URL y rango de fechas. Exportar también páginas y consultas de nuevo con fechas y filtros iguales para reconciliar el gráfico con el CSV.
2. Obtener exportación de páginas, publicaciones, productos y términos con los metadatos SEO individuales de Yoast: slug/permalink, título, descripción, canonical, `index/noindex`, Open Graph y tipo de schema. El dump de opciones globales que se recibió no basta.
3. Identificar qué plugin o mecanismo gestiona las redirecciones existentes. Exportar sus reglas actuales, `.htaccess` pertinente y las filas actuales de `redirecciones_seo` (origen, destino, código y estado). No enviar credenciales ni un dump completo de la base.
4. Confirmar disponibilidad y equivalentes actuales de las URLs de rendimiento omitidas del sitemap, especialmente `/shop/` y los productos que reciben impresiones.
5. Recibir el frontend Angular para acordar e implementar rutas, SSR/prerender, metadatos y sitemap con contenido y datos del API.

## Matriz de URLs

La matriz [legacy-url-inventory.csv](./legacy-url-inventory.csv) contiene 225 URLs: 207 URLs únicas de sitemaps, enriquecidas con métricas de Search Console cuando coinciden, y 18 URLs adicionales vistas en el informe de rendimiento. `performance_data_status = not_in_page_performance_export` solo significa que no hay una fila en ese CSV; **no significa que la URL esté desindexada**. Las propuestas son provisionales y deben cerrarse tras los faltantes anteriores.

## Secuencia segura de migración

1. Respaldar WordPress, base de datos, archivos y reglas vigentes; mantener WordPress disponible como rollback.
2. Completar inventario y clasificar cada URL en conservar, redirigir a equivalente, noindex/retirar con cuidado, o 404/410 cuando el contenido realmente desaparezca y no tenga equivalente.
3. Cargar muestras y contenido en `devi.designlila.com`, con autenticación y `noindex`. Verificar las URLs prioritarias, y probar explícitamente la configuración de producción para que canonicales, enlaces absolutos y sitemap usen `www.designlila.com`; evitar cadenas/loops y canonicales inconsistentes.
4. Revisar HTML de respuesta inicial, metadatos, imágenes, JSON-LD, breadcrumbs, sitemap, robots, enlaces internos y páginas de paginación.
5. Ejecutar un crawl comparativo de todas las URLs antiguas y destinos, aprobar el mapa 1:1 y la política de páginas noindex antes del lanzamiento.
6. Desplegar con rollback listo, enviar el nuevo sitemap y monitorear Search Console, logs 404, URLs excluidas, consultas y conversiones. Mantener las redirecciones mientras las URLs antiguas sigan recibiendo enlaces o tráfico.
