import { Component, inject } from '@angular/core';
import { ActivatedRoute } from '@angular/router';

@Component({
  selector: 'app-category-page',
  standalone: true,
  template: `
    <section aria-labelledby="category-title">
      <p class="eyebrow">Colección</p>
      <h1 id="category-title">Categoría: {{ slug }}</h1>
      <p>
        Esta página conservará la estructura de categoría histórica y se
        conectará al catálogo cuando integremos el contenido aprobado.
      </p>
    </section>
  `,
  styleUrl: '../page-placeholder.css',
})
export class CategoryPage {
  private readonly route = inject(ActivatedRoute);
  readonly slug = this.route.snapshot.paramMap.get('slug') ?? '';
}
