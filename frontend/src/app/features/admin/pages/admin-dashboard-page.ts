import { Component } from '@angular/core';
import { FeedbackPanel } from '../../../shared/ui/feedback-panel/feedback-panel';

@Component({
  selector: 'app-admin-dashboard-page',
  standalone: true,
  imports: [FeedbackPanel],
  template: `
    <section aria-labelledby="admin-dashboard-title">
      <h1 id="admin-dashboard-title">Panel de administración</h1>
      <app-feedback-panel
        title="Módulos de gestión"
        message="Cada módulo se añadirá con permisos y acceso a datos específicos."
      />
    </section>
  `,
  styles: [
    `
      h1 { color: var(--color-ink); font: 500 2rem var(--font-display); }
    `,
  ],
})
export class AdminDashboardPage {}
