import { Routes } from '@angular/router';
import { adminAuthGuard } from './admin-auth.guard';
import { AdminLayout } from './ui/admin-layout';

export const ADMIN_ROUTES: Routes = [
  {
    path: '',
    component: AdminLayout,
    children: [
      {
        path: '',
        pathMatch: 'full',
        redirectTo: 'login',
      },
      {
        path: 'login',
        loadComponent: () =>
          import('./pages/admin-login-page').then(
            (module) => module.AdminLoginPage,
          ),
        title: 'Acceso administrativo | Design Lila',
        data: { seo: { indexable: false } },
      },
      {
        path: 'dashboard',
        canActivate: [adminAuthGuard],
        loadComponent: () =>
          import('./pages/admin-dashboard-page').then(
            (module) => module.AdminDashboardPage,
          ),
        title: 'Administración | Design Lila',
        data: { seo: { indexable: false } },
      },
      {
        path: 'products',
        canActivate: [adminAuthGuard],
        loadComponent: () =>
          import('./pages/admin-products-page').then(
            (module) => module.AdminProductsPage,
          ),
        title: 'Productos | Administración | Design Lila',
        data: { seo: { indexable: false } },
      },
      {
        path: 'categories',
        canActivate: [adminAuthGuard],
        loadComponent: () =>
          import('./pages/admin-categories-page').then(
            (module) => module.AdminCategoriesPage,
          ),
        title: 'Categorías | Administración | Design Lila',
        data: { seo: { indexable: false } },
      },
    ],
  },
];
