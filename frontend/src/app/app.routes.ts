import { Routes } from '@angular/router';

export const routes: Routes = [
  {
    path: 'admin',
    loadChildren: () =>
      import('./features/admin/admin.routes').then((module) => module.ADMIN_ROUTES),
  },
  {
    path: '',
    loadChildren: () =>
      import('./features/storefront/storefront.routes').then(
        (module) => module.STOREFRONT_ROUTES,
      ),
  },
];
