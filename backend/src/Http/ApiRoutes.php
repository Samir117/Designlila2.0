<?php

declare(strict_types=1);

namespace DesignLila\Http;

use DesignLila\Admin\AdminService;
use DesignLila\Auth\AdminAuth;
use DesignLila\Auth\Session;
use DesignLila\Catalog\CatalogService;
use DesignLila\Orders\CommerceService;
use DesignLila\Support\HttpException;
use DesignLila\Support\Input;

final class ApiRoutes
{
    public static function register(Router $router): void
    {
        $authorize = static function (string $permission): array {
            if (in_array(Request::method(), ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
                Session::verifyCsrf();
            }

            return AdminAuth::requirePermission($permission);
        };

        $router->get('/api/v1/auth/csrf', static function (): never {
            JsonResponse::success(['token' => Session::csrfToken()]);
        });

        $router->post('/api/v1/auth/setup', static function (): never {
            JsonResponse::success(AdminAuth::setupFirstAdmin(Request::json()), 201);
        });

        $router->post('/api/v1/auth/login', static function (): never {
            Session::verifyCsrf();
            $user = AdminAuth::login(Request::json());
            JsonResponse::success(['user' => $user, 'csrf_token' => Session::csrfToken()]);
        });

        $router->get('/api/v1/auth/me', static function (): never {
            JsonResponse::success(['user' => AdminAuth::currentUser(), 'csrf_token' => Session::csrfToken()]);
        });

        $router->post('/api/v1/auth/logout', static function (): never {
            Session::verifyCsrf();
            Session::destroy();
            JsonResponse::success(['logged_out' => true]);
        });

        $router->get('/api/v1/config', static function (): never {
            JsonResponse::success(AdminService::publicSettings());
        });

        $router->get('/api/v1/categories', static function (): never {
            JsonResponse::success(CatalogService::listCategories());
        });

        $router->get('/api/v1/products', static function (): never {
            JsonResponse::success(CatalogService::listProducts(Request::query()));
        });

        $router->get('/api/v1/products/{slug}', static function (array $params): never {
            JsonResponse::success(CatalogService::getProduct($params['slug']));
        });

        $router->post('/api/v1/orders', static function (): never {
            JsonResponse::success(CommerceService::createGuestOrder(Request::json()), 201);
        });

        $router->post('/api/v1/payments/report', static function (): never {
            JsonResponse::success(CommerceService::reportPayment(Request::input(), Request::file('receipt')), 201);
        });

        $router->get('/api/v1/admin/categories', static function () use ($authorize): never {
            $authorize('categorias.gestionar');
            JsonResponse::success(CatalogService::listCategories(true));
        });

        $router->post('/api/v1/admin/categories', static function () use ($authorize): never {
            $authorize('categorias.gestionar');
            JsonResponse::success(CatalogService::saveCategory(Request::json()), 201);
        });

        $router->put('/api/v1/admin/categories/{id}', static function (array $params) use ($authorize): never {
            $authorize('categorias.gestionar');
            JsonResponse::success(CatalogService::saveCategory(Request::json(), self::routeId($params)));
        });

        $router->delete('/api/v1/admin/categories/{id}', static function (array $params) use ($authorize): never {
            $authorize('categorias.gestionar');
            CatalogService::deactivateCategory(self::routeId($params));
            JsonResponse::success(['deactivated' => true]);
        });

        $router->get('/api/v1/admin/products', static function () use ($authorize): never {
            $authorize('productos.ver');
            JsonResponse::success(CatalogService::listProducts(Request::query(), true, true));
        });

        $router->get('/api/v1/admin/products/{id}', static function (array $params) use ($authorize): never {
            $authorize('productos.ver');
            JsonResponse::success(CatalogService::getAdminProduct(self::routeId($params)));
        });

        $router->post('/api/v1/admin/products', static function () use ($authorize): never {
            $authorize('productos.crear');
            JsonResponse::success(CatalogService::saveProduct(Request::json()), 201);
        });

        $router->put('/api/v1/admin/products/{id}', static function (array $params) use ($authorize): never {
            $authorize('productos.editar');
            JsonResponse::success(CatalogService::saveProduct(Request::json(), self::routeId($params)));
        });

        $router->delete('/api/v1/admin/products/{id}', static function (array $params) use ($authorize): never {
            $authorize('productos.eliminar');
            CatalogService::deactivateProduct(self::routeId($params));
            JsonResponse::success(['deactivated' => true]);
        });

        $router->get('/api/v1/admin/orders', static function () use ($authorize): never {
            $authorize('pedidos.ver');
            JsonResponse::success(CommerceService::listOrders(Request::query()));
        });

        $router->get('/api/v1/admin/orders/{id}', static function (array $params) use ($authorize): never {
            $authorize('pedidos.ver');
            JsonResponse::success(CommerceService::getOrder(self::routeId($params)));
        });

        $router->post('/api/v1/admin/orders/{id}/status', static function (array $params) use ($authorize): never {
            $user = $authorize('pedidos.gestionar');
            $input = Request::json();
            $status = Input::string($input, 'status', 30);
            $comment = Input::string($input, 'comment', 65535, false);
            CommerceService::changeOrderStatus(self::routeId($params), (int) $user['id'], $status, $comment);
            JsonResponse::success(['updated' => true]);
        });

        $router->get('/api/v1/admin/payments', static function () use ($authorize): never {
            $authorize('pagos.validar');
            JsonResponse::success(CommerceService::listPayments(Request::query()));
        });

        $router->post('/api/v1/admin/payments/{id}/approve', static function (array $params) use ($authorize): never {
            $user = $authorize('pagos.validar');
            CommerceService::reviewPayment(
                self::routeId($params),
                (int) $user['id'],
                true,
                Input::string(Request::json(), 'comment', 65535, false),
            );
            JsonResponse::success(['status' => 'aprobado']);
        });

        $router->post('/api/v1/admin/payments/{id}/reject', static function (array $params) use ($authorize): never {
            $user = $authorize('pagos.validar');
            CommerceService::reviewPayment(
                self::routeId($params),
                (int) $user['id'],
                false,
                Input::string(Request::json(), 'comment', 65535, false),
            );
            JsonResponse::success(['status' => 'rechazado']);
        });

        $router->get('/api/v1/admin/payments/{id}/receipt', static function (array $params) use ($authorize): never {
            $authorize('pagos.validar');
            $receipt = CommerceService::paymentReceipt(self::routeId($params));
            header('Content-Type: ' . $receipt['mime']);
            header('Content-Disposition: attachment; filename="payment-receipt"');
            header('Content-Length: ' . (string) filesize($receipt['path']));
            header('X-Content-Type-Options: nosniff');
            header('Cache-Control: no-store');
            readfile($receipt['path']);
            exit;
        });

        $router->patch('/api/v1/admin/orders/{id}/shipping', static function (array $params) use ($authorize): never {
            $user = $authorize('envios.gestionar');
            CommerceService::updateShipping(
                self::routeId($params),
                (int) $user['id'],
                Request::json(),
            );
            JsonResponse::success(['updated' => true]);
        });

        $router->get('/api/v1/admin/shipments', static function () use ($authorize): never {
            $authorize('envios.gestionar');
            JsonResponse::success(AdminService::shipments(Request::query()));
        });

        $router->get('/api/v1/admin/customers', static function () use ($authorize): never {
            $authorize('clientes.ver');
            JsonResponse::success(AdminService::customers(Request::query()));
        });

        $router->get('/api/v1/admin/inventory', static function () use ($authorize): never {
            $authorize('inventario.gestionar');
            JsonResponse::success(AdminService::inventory(Request::query()));
        });

        $router->get('/api/v1/admin/inventory/movements', static function () use ($authorize): never {
            $authorize('inventario.gestionar');
            JsonResponse::success(AdminService::movements(Request::query()));
        });

        $router->post('/api/v1/admin/inventory/adjustments', static function () use ($authorize): never {
            $user = $authorize('inventario.ajustar');
            JsonResponse::success(AdminService::adjustInventory(Request::json(), (int) $user['id']), 201);
        });

        $router->get('/api/v1/admin/settings', static function () use ($authorize): never {
            $authorize('configuracion.gestionar');
            JsonResponse::success(AdminService::settings());
        });

        $router->put('/api/v1/admin/settings', static function () use ($authorize): never {
            $authorize('configuracion.gestionar');
            AdminService::saveSetting(Request::json());
            JsonResponse::success(['updated' => true]);
        });

        $router->get('/api/v1/admin/metrics', static function () use ($authorize): never {
            $authorize('metricas.ver');
            JsonResponse::success(AdminService::metrics());
        });

        $router->get('/api/v1/admin/roles', static function () use ($authorize): never {
            $authorize('usuarios.gestionar');
            JsonResponse::success(AdminService::roles());
        });

        $router->get('/api/v1/admin/users', static function () use ($authorize): never {
            $authorize('usuarios.gestionar');
            JsonResponse::success(AdminService::users());
        });

        $router->post('/api/v1/admin/users', static function () use ($authorize): never {
            $user = $authorize('usuarios.gestionar');
            JsonResponse::success(AdminService::saveUser(Request::json(), (int) $user['id']), 201);
        });

        $router->put('/api/v1/admin/users/{id}', static function (array $params) use ($authorize): never {
            $user = $authorize('usuarios.gestionar');
            JsonResponse::success(AdminService::saveUser(Request::json(), (int) $user['id'], self::routeId($params)));
        });
    }

    /** @param array<string, string> $params */
    private static function routeId(array $params): int
    {
        $id = filter_var($params['id'] ?? null, FILTER_VALIDATE_INT);
        if ($id === false || $id < 1) {
            throw new HttpException('Invalid route identifier.', 422);
        }

        return (int) $id;
    }
}
