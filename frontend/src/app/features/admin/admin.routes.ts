import { Routes } from '@angular/router';
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
        loadComponent: () =>
          import('./pages/admin-dashboard-page').then(
            (module) => module.AdminDashboardPage,
          ),
        title: 'Administración | Design Lila',
        data: { seo: { indexable: false } },
      },
    ],
  },
];
