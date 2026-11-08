import { Routes } from '@angular/router';
import { HomePage } from './home-page';
import { DeepRoutePage } from './deep-route-page';

export const routes: Routes = [
  { path: '', component: HomePage, title: 'Prueba Angular | Design Lila' },
  {
    path: 'ruta-prueba-seo',
    component: DeepRoutePage,
    title: 'Ruta profunda | Prueba Angular | Design Lila',
  },
  { path: '**', redirectTo: '' },
];
