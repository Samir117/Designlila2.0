import { Component } from '@angular/core';
import { RouterLink, RouterOutlet } from '@angular/router';

@Component({
  selector: 'app-admin-layout',
  standalone: true,
  imports: [RouterLink, RouterOutlet],
  template: `
    <a class="skip-link" href="#admin-content">Saltar al contenido</a>
    <header>
      <a routerLink="/admin/dashboard">Design Lila · Administración</a>
    </header>
    <main id="admin-content" tabindex="-1">
      <router-outlet />
    </main>
  `,
  styleUrl: './admin-layout.css',
})
export class AdminLayout {}
