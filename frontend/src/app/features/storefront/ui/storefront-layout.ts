import { Component } from '@angular/core';
import { RouterLink, RouterOutlet } from '@angular/router';

@Component({
  selector: 'app-storefront-layout',
  standalone: true,
  imports: [RouterLink, RouterOutlet],
  template: `
    <a class="skip-link" href="#main-content">Saltar al contenido</a>
    <div class="storefront">
      <header class="site-header">
        <a class="brand" routerLink="/" aria-label="Design Lila, inicio">
          <span class="brand-mark" aria-hidden="true">dl</span>
          <span>Design Lila</span>
        </a>
        <nav aria-label="Navegación principal">
          <a routerLink="/" fragment="catalogo">Tienda</a>
          <a routerLink="/blog/">Ideas</a>
        </nav>
      </header>
      <main id="main-content" tabindex="-1">
        <router-outlet />
      </main>
      <footer class="site-footer">
        <p>Detalles hechos para celebrar.</p>
        <span>Design Lila · Colombia</span>
      </footer>
    </div>
  `,
  styleUrl: './storefront-layout.css',
})
export class StorefrontLayout {}
