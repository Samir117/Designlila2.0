import { CurrencyPipe } from '@angular/common';
import { HttpErrorResponse } from '@angular/common/http';
import { Component, DestroyRef, inject, signal } from '@angular/core';
import { takeUntilDestroyed } from '@angular/core/rxjs-interop';
import { ActivatedRoute, RouterLink } from '@angular/router';
import { catchError, forkJoin, map, of, switchMap, tap } from 'rxjs';
import { SeoService } from '../../../../core/seo/seo.service';
import { CatalogApi } from '../../../catalog/data-access/catalog-api.service';
import {
  CatalogCategory,
  CatalogProduct,
} from '../../../catalog/data-access/catalog.models';

@Component({
  selector: 'app-category-page',
  standalone: true,
  imports: [CurrencyPipe, RouterLink],
  template: `
    @if (loading()) {
      <p role="status">Cargando categoría…</p>
    } @else if (error()) {
      <section aria-labelledby="category-title">
        <p class="eyebrow">Colección</p>
        <h1 id="category-title">{{ error() }}</h1>
      </section>
    } @else if (category(); as currentCategory) {
      <section aria-labelledby="category-title">
        <p class="eyebrow">Colección</p>
        <h1 id="category-title">{{ currentCategory.nombre }}</h1>
        @if (currentCategory.descripcion) {
          <p class="category-description">{{ currentCategory.descripcion }}</p>
        }
      </section>

      @if (products().length) {
        <ul class="product-grid">
          @for (product of products(); track product.id) {
            <li>
              <a class="product-card" [routerLink]="['/product', product.slug]">
                @if (product.imagen) {
                  <img
                    [src]="product.imagen"
                    [alt]="product.nombre"
                    width="480"
                    height="480"
                    loading="lazy"
                  />
                }
                <span class="product-name">{{ product.nombre }}</span>
                <span class="product-price">
                  {{ (product.precio_oferta || product.precio) | currency: 'COP' : 'symbol' : '1.0-0' : 'es-CO' }}
                </span>
              </a>
            </li>
          }
        </ul>
      } @else {
        <p>En esta colección no hay productos disponibles por ahora.</p>
      }
    }
  `,
  styleUrl: './category-page.css',
})
export class CategoryPage {
  private readonly route = inject(ActivatedRoute);
  private readonly catalog = inject(CatalogApi);
  private readonly seo = inject(SeoService);
  private readonly destroyRef = inject(DestroyRef);

  readonly loading = signal(true);
  readonly error = signal<string | null>(null);
  readonly category = signal<CatalogCategory | null>(null);
  readonly products = signal<CatalogProduct[]>([]);

  constructor() {
    this.route.paramMap
      .pipe(
        map((params) => params.get('slug') ?? ''),
        switchMap((slug) => {
          this.loading.set(true);
          this.error.set(null);
          return forkJoin({
            categories: this.catalog.getCategories(),
            products: this.catalog.getProducts(1, 100, { category: slug }),
          }).pipe(
            map(({ categories, products }) => ({
              category: categories.find((item) => item.slug === slug) ?? null,
              products: products.items,
            })),
            tap(({ category, products }) => {
              this.category.set(category);
              this.products.set(products);
              this.loading.set(false);
              if (!category) {
                this.error.set('No encontramos esta categoría.');
              }
              this.updateSeo(category, products, slug);
            }),
            catchError((error: unknown) => {
              this.loading.set(false);
              this.error.set(this.errorMessage(error));
              this.updateSeo(null, [], slug);
              return of(null);
            }),
          );
        }),
        takeUntilDestroyed(this.destroyRef),
      )
      .subscribe();
  }

  private updateSeo(
    category: CatalogCategory | null,
    products: CatalogProduct[],
    slug: string,
  ): void {
    const description =
      category?.meta_description?.trim() || category?.descripcion?.trim() || '';
    const canonicalPath = `/product-category/${encodeURIComponent(slug)}/`;
    this.seo.apply(
      {
        title:
          category?.meta_title?.trim() ||
          (category ? `${category.nombre} | Design Lila` : 'Categoría | Design Lila'),
        description,
        canonicalPath,
        indexable: Boolean(category && description && products.length),
      },
      canonicalPath,
    );

    if (category && products.length) {
      this.seo.setStructuredData({
        '@context': 'https://schema.org',
        '@type': 'CollectionPage',
        name: category.nombre,
        description,
        url: `https://www.designlila.com${canonicalPath}`,
        mainEntity: {
          '@type': 'ItemList',
          itemListElement: products.map((product, index) => ({
            '@type': 'ListItem',
            position: index + 1,
            url: `https://www.designlila.com/product/${product.slug}/`,
            name: product.nombre,
          })),
        },
      });
    }
  }

  private errorMessage(error: unknown): string {
    if (error instanceof HttpErrorResponse && error.status === 404) {
      return 'No encontramos esta categoría.';
    }

    return 'No pudimos cargar esta categoría. Intenta de nuevo más tarde.';
  }
}
