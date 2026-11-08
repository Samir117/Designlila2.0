import { Component } from '@angular/core';
import { RouterLink } from '@angular/router';

@Component({
  selector: 'app-home-page',
  imports: [RouterLink],
  template: `
    <section class="hero">
      <p class="eyebrow">Prueba de compatibilidad · Angular 21</p>
      <h1>La tienda puede crecer<br />sin cambiar de casa.</h1>
      <p class="intro">
        Esta app se compiló localmente y se entrega como archivos estáticos.
        No requiere un proceso Node.js activo en el hosting.
      </p>
      <a class="button" routerLink="/ruta-prueba-seo">Probar una ruta interna</a>
    </section>

    <section class="checks" aria-labelledby="checks-title">
      <div>
        <p class="eyebrow">Lo que estamos comprobando</p>
        <h2 id="checks-title">Angular en el hosting compartido</h2>
      </div>
      <ul>
        <li><span class="check">✓</span> La aplicación Angular inicia</li>
        <li><span class="check">✓</span> Los recursos compilados cargan</li>
        <li><span class="check">✓</span> El router cambia de página</li>
        <li><span class="pending">…</span> Recargar una URL profunda</li>
      </ul>
    </section>
  `,
  styleUrl: './page.css',
})
export class HomePage {}
