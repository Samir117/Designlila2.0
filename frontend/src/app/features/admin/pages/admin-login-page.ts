import { HttpErrorResponse } from '@angular/common/http';
import { Component, inject } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { Router } from '@angular/router';
import { ActionButton } from '../../../shared/ui/action-button/action-button';
import { FormField } from '../../../shared/ui/form-field/form-field';
import { AdminAuthService } from '../data-access/admin-auth.service';

@Component({
  selector: 'app-admin-login-page',
  standalone: true,
  imports: [FormsModule, ActionButton, FormField],
  template: `
    <section class="login" aria-labelledby="admin-login-title">
      <p class="eyebrow">Design Lila</p>
      <h1 id="admin-login-title">Acceso al panel</h1>
      <p>Inicia sesión para administrar tu catálogo.</p>
      <form (ngSubmit)="submit()" #loginForm="ngForm">
        <app-form-field label="Correo electrónico" [required]="true">
          <input
            name="email"
            type="email"
            autocomplete="username"
            required
            maxlength="190"
            [(ngModel)]="email"
          />
        </app-form-field>
        <app-form-field label="Contraseña" [required]="true">
          <input
            name="password"
            type="password"
            autocomplete="current-password"
            required
            maxlength="200"
            [(ngModel)]="password"
          />
        </app-form-field>
        @if (error) {
          <p class="error" role="alert">{{ error }}</p>
        }
        <button
          appActionButton
          type="submit"
          [disabled]="loginForm.invalid || submitting"
          [busy]="submitting"
        >
          {{ submitting ? 'Verificando…' : 'Iniciar sesión' }}
        </button>
      </form>
    </section>
  `,
  styles: `
    .login { max-width: 440px; margin: 32px auto; padding: 32px; border: 1px solid var(--color-border); border-radius: var(--radius-card); background: var(--color-background); }
    h1 { margin: 0; color: var(--color-heading); font: 500 2rem var(--font-display); }
    .eyebrow { color: var(--color-accent); font-weight: 700; }
    form { display: grid; gap: 14px; margin-top: 24px; }
    button { justify-self: start; margin-top: 8px; }
    .error { color: var(--color-danger); }
  `,
})
export class AdminLoginPage {
  private readonly auth = inject(AdminAuthService);
  private readonly router = inject(Router);

  email = '';
  password = '';
  submitting = false;
  error = '';

  submit(): void {
    if (this.submitting) return;
    this.submitting = true;
    this.error = '';
    this.auth.login(this.email.trim(), this.password).subscribe({
      next: () => void this.router.navigate(['/admin/dashboard']),
      error: (error: unknown) => {
        this.submitting = false;
        this.error =
          error instanceof HttpErrorResponse && error.status === 401
            ? 'El correo o la contraseña no son correctos.'
            : 'No se pudo iniciar sesión. Revisa la conexión e inténtalo de nuevo.';
      },
    });
  }
}
