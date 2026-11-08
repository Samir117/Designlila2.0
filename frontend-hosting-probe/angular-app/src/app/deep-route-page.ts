import { Component } from '@angular/core';
import { RouterLink } from '@angular/router';

@Component({
  selector: 'app-deep-route-page',
  imports: [RouterLink],
  template: `
    <section class="hero">
      <p class="eyebrow">Ruta profunda cargada</p>
      <h1>El router encontró<br />esta página.</h1>
      <p class="intro">
        Si llegaste aquí pegando la dirección o recargando el navegador,
        también funciona la regla de fallback del servidor.
      </p>
      <div class="result" role="status">
        <span class="result-icon" aria-hidden="true">✓</span>
        <span><strong>Ruta Angular activa</strong><br />/ruta-prueba-seo</span>
      </div>
      <a class="text-link" routerLink="/">Volver al inicio</a>
    </section>
  `,
  styleUrl: './page.css',
})
export class DeepRoutePage {}
