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

## Próximo paso
Iniciar la implementación del backend PHP:

1. Crear estructura de carpetas.
2. Configurar variables de entorno.
3. Implementar conexión PDO.
4. Crear enrutador REST.
5. Implementar respuestas JSON y manejo de errores.
6. Implementar autenticación y permisos.
7. Desarrollar módulos funcionales progresivamente.

## Forma de trabajo
- Trabajar paso a paso.
- Revisar primero el código existente antes de modificarlo.
- No recrear tablas que ya existen.
- No modificar el esquema sin justificarlo.
- Entregar código completo y funcional.
- Explicar dónde crear cada archivo.
- Priorizar seguridad, mantenibilidad y compatibilidad con el hosting.
- No asumir que una operación fue ejecutada sin comprobarlo.