# Design Lila — Contexto del proyecto

## Objetivo
Migrar completamente la tienda Design Lila desde WordPress a una aplicación propia, eliminando la dependencia de WordPress.

## Stack tecnológico
- Frontend: Angular 21.
- Backend: PHP 8.4.
- Acceso a datos: PDO.
- Base de datos: MariaDB 10.11.
- Hosting final: cPanel compartido de Latinoamérica Hosting.
- Entorno de desarrollo: Windows + Visual Studio Code.

## Arquitectura
- Angular para la tienda pública.
- Angular para el panel administrativo.
- API REST centralizada en PHP.
- MariaDB como almacenamiento.
- Comunicación mediante HTTPS y JSON.
- El frontend nunca se conecta directamente a MariaDB.
- Código del frontend en `frontend/`, organizado en `core/`, `features/` y `shared/ui/`; compila a archivos estáticos para cPanel.
- El hosting entrega los archivos compilados; no se presupone Node.js persistente en el plan H1.
- El documento `frontend/README.md` describe comandos, módulos, despliegue y restricciones SEO.
- Desarrollo actual local: Angular usa rutas desde `/`, como en el dominio principal, y el proxy local `/api/` apunta a la API PHP; no se agrega un prefijo de pruebas a las rutas.
- El build de Angular se genera localmente en `frontend/deploy/browser` y se versiona para cPanel. `.cpanel.yml` despliega backend y frontend solamente a `dev.designlila.com`; no publicar en el dominio principal sin autorización expresa.
- Angular usa prerenderizado estático (SSG) para portada, productos y categorías activas desde la API. El build predeterminado es staging `noindex`; el build de producción es explícito y genera sitemap solo para HTML indexable.
- El panel Angular implementa login con sesión/CSRF y CRUD administrativo para productos simples y con variantes, precios de oferta y categorías, usando los endpoints existentes de la API. Los cambios de catálogo se guardan en MariaDB sin redesplegar el frontend.
- Limitaciones del catálogo administrativo: imágenes se gestionan por URL/ruta, no mediante carga de archivos; las ofertas no tienen programación por fecha; el stock requiere el endpoint de ajustes de inventario. Los cambios no regeneran HTML prerenderizado ni sitemap.
- Las plantillas visuales de los componentes están actualmente dentro de los archivos TypeScript (propiedad `template`/`styles`); Angular no genera un `.html` fuente por componente. El build SSG genera HTML compilado por ruta, no por componente.

## Decisiones comerciales
- Se permiten productos simples y productos con variantes.
- Los clientes pueden comprar como invitados.
- No se exige registro.
- Los pagos se validan manualmente.
- El cliente adjunta un comprobante.
- Los envíos se gestionan manualmente.
- El administrador gestiona pedidos, pagos, inventario, envíos y métricas.

## Política de inventario CONFIRMADA
El inventario se descuenta exclusivamente cuando el administrador aprueba el pago.

- Crear pedido: no descuenta stock.
- Adjuntar comprobante: no descuenta stock.
- Aprobar pago: descuenta stock.
- Rechazar pago: no descuenta stock.
- Cancelar pedido pagado: debe gestionar la devolución del inventario.

Los descuentos deben realizarse dentro de transacciones SQL, utilizando bloqueos SELECT FOR UPDATE para evitar ventas simultáneas de las mismas existencias.

Los productos simples utilizan productos.stock.
Los productos con variantes utilizan producto_variantes.stock.
Nunca se deben descontar ambas existencias para el mismo producto.

Cada movimiento debe quedar registrado en movimientos_inventario.

## Estados de pedidos
- pendiente_pago
- pago_confirmado
- en_preparacion
- listo_para_envio
- enviado
- entregado
- cancelado
- reembolsado

## Estados de pagos
- pendiente
- reportado
- aprobado
- rechazado

## Base de datos
Nombre: designli_database.

Tiene 19 tablas físicas y 2 vistas.

Tablas principales:
- categorias
- clientes
- configuracion
- detalle_pedido
- direcciones
- movimientos_inventario
- pagos
- pedidos
- permisos
- productos
- producto_imagenes
- producto_variantes
- roles
- rol_permisos
- usuarios
- usuario_roles
- pedido_historial_estados
- envios
- redirecciones_seo

Vistas:
- vw_inventario_productos
- vw_pedidos_resumen

Las tablas comerciales todavía no tienen productos ni pedidos de prueba.

## Seguridad y reglas técnicas
- Utilizar PDO y consultas preparadas.
- Validar todos los datos en el backend.
- Contraseñas con password_hash y password_verify.
- Control de acceso basado en roles y permisos.
- Proteger las sesiones administrativas.
- Validar comprobantes y archivos cargados.
- Usar transacciones para operaciones comerciales críticas.
- No exponer credenciales ni archivos sensibles.
- Mantener snapshots de los productos y variantes comprados.

## Roles iniciales
- Superadministrador
- Administrador
- Inventario
- Ventas

## Estado del proyecto
La fase de diseño y ajustes principales de la base de datos está terminada.
Las relaciones de pedidos, pagos y movimientos de inventario están verificadas.
La política de descuento de inventario está confirmada.
La migración SEO sigue pendiente de aprobación de URLs y redirecciones, aunque la infraestructura SSG ya está preparada. El entorno de pruebas siempre debe quedar `noindex`; el build de producción indexable no debe desplegarse hasta migrar el contenido real, conciliar los slugs con el inventario y aprobar las redirecciones. No publicar la migración ni cambiar slugs sin esa revisión.

## Próximos pasos pendientes
- Completar la integración de SEO antes de cambiar slugs o publicar la migración: recopilar las URLs indexadas actuales, metadatos, sitemap.xml, robots.txt, canonicales, datos estructurados y reglas de redirección; contrastarlos con las rutas del nuevo sitio.
- Replicar en Angular la salida SEO necesaria para las páginas públicas y conservar las URLs actuales cuando sea posible.
- Antes de producción, resolver y probar cómo mantener sincronizados el HTML prerenderizado, canonicales/metadatos y `sitemap.xml` cada vez que el catálogo cambie desde el administrador, sin una subida manual ni un redeploy de frontend. Evaluar mecanismo compatible con el hosting (por ejemplo regeneración/publicación automática segura o SSR) y demostrar creación, edición, cambio de precio, baja lógica y categoría con respuesta HTML rastreable actualizada. No habilitar indexación de producción hasta completar esta validación.
- Verificar qué rutas SSG se generaron realmente en cada build: Angular genera HTML por ruta, no un archivo por componente. El build de staging actual solo genera `/` porque la API de pruebas no tiene productos/categorías activas; no hay HTML estático de detalle de productos hasta que el catálogo esté cargado y el prerender vuelva a ejecutarse.
- Conectar y validar la tabla existente `redirecciones_seo` para servir redirecciones permanentes 301 de las URLs antiguas a sus destinos nuevos. No borrar ni reemplazar reglas existentes.
- Validar las reglas y páginas con el sitemap y una exportación de Search Console antes del cambio de dominio/plataforma. No publicar la migración ni cambiar slugs hasta tener este inventario y un plan de redirecciones.
- Probar en el hosting los módulos del backend contra la base de datos real y verificar los permisos iniciales antes de habilitar operaciones comerciales.

Para realizar el trabajo de SEO se necesita recibir una exportación sin credenciales de las URLs actuales y sus redirecciones, `sitemap.xml`, `robots.txt`, la configuración/exportación del plugin SEO y el código/rutas Angular que genera títulos, descripciones, canonicales y datos estructurados. No compartir `.env`, contraseñas, tokens ni accesos a Search Console.

## Forma de trabajo
- Trabajar paso a paso.
- Revisar primero el código existente antes de modificarlo.
- No recrear tablas que ya existen.
- No modificar el esquema sin justificarlo.
- Entregar código completo y funcional.
- Explicar dónde crear cada archivo.
- Priorizar seguridad, mantenibilidad y compatibilidad con el hosting.
- No asumir que una operación fue ejecutada sin comprobarlo.