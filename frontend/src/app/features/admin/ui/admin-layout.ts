import { Component, inject } from '@angular/core';
import { Router, RouterLink, RouterOutlet } from '@angular/router';
import { AdminAuthService } from '../data-access/admin-auth.service';

@Component({
  selector: 'app-admin-layout',
  standalone: true,
  imports: [RouterLink, RouterOutlet],
  template: `
    <a class="skip-link" href="#admin-content">Saltar al contenido</a>
    <header>
      <a routerLink="/admin/dashboard">Design Lila · Administración</a>
      @if (auth.user(); as user) {
        <nav aria-label="Administración">
          <a routerLink="/admin/dashboard">Resumen</a>
          <a routerLink="/admin/products">Productos</a>
          <a routerLink="/admin/categories">Categorías</a>
          <span>{{ user.name }}</span>
          <button type="button" (click)="logout()" [disabled]="loggingOut">
            {{ loggingOut ? 'Cerrando…' : 'Cerrar sesión' }}
          </button>
        </nav>
      }
    </header>
    <main id="admin-content" tabindex="-1">
      @if (logoutError) {
        <p role="alert">{{ logoutError }}</p>
      }
      <router-outlet />
    </main>
  `,
  styleUrl: './admin-layout.css',
})
export class AdminLayout {
  readonly auth = inject(AdminAuthService);
  private readonly router = inject(Router);
  loggingOut = false;
  logoutError = '';

  logout(): void {
    this.loggingOut = true;
    this.logoutError = '';
    this.auth.logout().subscribe({
      next: () => void this.router.navigate(['/admin/login']),
      error: () => {
        this.loggingOut = false;
        this.logoutError = 'No se pudo cerrar la sesión. Intenta de nuevo.';
      },
    });
  }
}
