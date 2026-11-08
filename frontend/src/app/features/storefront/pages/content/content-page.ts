import { Component, inject } from '@angular/core';
import { ActivatedRoute } from '@angular/router';

@Component({
  selector: 'app-content-page',
  standalone: true,
  template: `
    <section aria-labelledby="content-title">
      <p class="eyebrow">Design Lila</p>
      <h1 id="content-title">{{ slug }}</h1>
      <p>
        Esta ruta está reservada para una página o artículo histórico. Antes de
        publicarla, conectaremos su contenido y sus metadatos verificados.
      </p>
    </section>
  `,
  styleUrl: '../page-placeholder.css',
})
export class ContentPage {
  private readonly route = inject(ActivatedRoute);
  readonly slug = this.route.snapshot.paramMap.get('slug') ?? '';
}
