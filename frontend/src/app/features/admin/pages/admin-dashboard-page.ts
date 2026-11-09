import { Component } from '@angular/core';
import { RouterLink } from '@angular/router';

@Component({
  selector: 'app-admin-dashboard-page',
  standalone: true,
  imports: [RouterLink],
  template: `
    <section aria-labelledby="admin-dashboard-title">
      <p class="eyebrow">Administración</p>
      <h1 id="admin-dashboard-title">Panel de administración</h1>
      <p>Actualiza el catálogo guardando los cambios directamente en el sistema.</p>
      <nav aria-label="Gestión del catálogo">
        <a routerLink="/admin/products">
          <strong>Productos</strong>
          <span>Crear productos, cambiar precios y administrar promociones.</span>
        </a>
        <a routerLink="/admin/categories">
          <strong>Categorías</strong>
          <span>Organizar y actualizar las colecciones de la tienda.</span>
        </a>
      </nav>
    </section>
  `,
  styles: `
    h1 { color: var(--color-heading); font: 500 clamp(2rem, 5vw, 3rem) var(--font-display); }
    .eyebrow { color: var(--color-accent); font-weight: 700; }
    nav { display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 16px; margin-top: 28px; }
    nav a { display: grid; gap: 8px; padding: 22px; border: 1px solid var(--color-border); border-radius: var(--radius-card); color: var(--color-body); text-decoration: none; }
    nav a:hover { border-color: var(--color-accent); }
    nav strong { color: var(--color-heading); font-size: 1.2rem; }
  `,
})
export class AdminDashboardPage {}
