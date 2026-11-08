import { Component } from '@angular/core';
import { FeedbackPanel } from '../../../shared/ui/feedback-panel/feedback-panel';

@Component({
  selector: 'app-admin-login-page',
  standalone: true,
  imports: [FeedbackPanel],
  template: `
    <section aria-labelledby="admin-login-title">
      <h1 id="admin-login-title">Acceso al panel</h1>
      <app-feedback-panel
        title="Inicio de sesión"
        message="La autenticación administrativa usará sesión segura y protección CSRF de la API."
      />
    </section>
  `,
  styles: [
    `
      h1 { color: var(--color-ink); font: 500 2rem var(--font-display); }
    `,
  ],
})
export class AdminLoginPage {}
