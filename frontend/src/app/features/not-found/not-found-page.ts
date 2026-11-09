import { Component } from '@angular/core';
import { RouterLink } from '@angular/router';

@Component({
  selector: 'app-not-found-page',
  standalone: true,
  imports: [RouterLink],
  template: `
    <section aria-labelledby="not-found-title">
      <p class="eyebrow">404 · Página no encontrada</p>
      <h1 id="not-found-title">No encontramos esa página.</h1>
      <a routerLink="/">Volver al inicio</a>
    </section>
  `,
  styles: `
    section { max-width: 720px; margin: 80px auto; padding: 32px; }
    .eyebrow { color: var(--color-heading); font-weight: 700; }
    h1 { color: var(--color-heading); font: 500 clamp(2rem, 6vw, 4rem)/1.1 var(--font-display); }
    a { color: var(--color-accent); }
  `,
})
export class NotFoundPage {}
