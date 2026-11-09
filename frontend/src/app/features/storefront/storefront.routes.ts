import { Routes } from '@angular/router';
import { StorefrontLayout } from './ui/storefront-layout';

export const STOREFRONT_ROUTES: Routes = [
  {
    path: '',
    component: StorefrontLayout,
    children: [
      {
        path: '',
        loadComponent: () =>
          import('./pages/home/home-page').then((module) => module.HomePage),
        title: 'Papelería y regalos personalizados | Design Lila',
        data: {
          seo: {
            description:
              'Detalles personalizados para celebrar momentos especiales.',
            indexable: true,
          },
        },
      },
      {
        path: 'product/:slug',
        loadComponent: () =>
          import('./pages/product/product-page').then(
            (module) => module.ProductPage,
          ),
        title: 'Producto | Design Lila',
        data: { seo: { indexable: true } },
      },
      {
        path: 'product-category/:slug',
        loadComponent: () =>
          import('./pages/category/category-page').then(
            (module) => module.CategoryPage,
          ),
        title: 'Categoría | Design Lila',
        data: { seo: { indexable: false } },
      },
      {
        path: 'category/:slug',
        loadComponent: () =>
          import('./pages/content/content-page').then(
            (module) => module.ContentPage,
          ),
        title: 'Ideas y novedades | Design Lila',
        data: { seo: { indexable: false } },
      },
      {
        path: 'checkout',
        loadComponent: () =>
          import('./pages/checkout/checkout-page').then(
            (module) => module.CheckoutPage,
          ),
        title: 'Finalizar compra | Design Lila',
        data: { seo: { indexable: false } },
      },
      {
        path: ':slug',
        loadComponent: () =>
          import('./pages/content/content-page').then(
            (module) => module.ContentPage,
          ),
        title: 'Design Lila',
        data: { seo: { indexable: false } },
      },
      {
        path: '**',
        loadComponent: () =>
          import('../not-found/not-found-page').then(
            (module) => module.NotFoundPage,
          ),
        title: 'Página no encontrada | Design Lila',
        data: { seo: { indexable: false } },
      },
    ],
  },
];
