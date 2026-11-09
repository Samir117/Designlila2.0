import { CurrencyPipe } from '@angular/common';
import { Component, DestroyRef, inject, signal } from '@angular/core';
import { takeUntilDestroyed } from '@angular/core/rxjs-interop';
import { RouterLink } from '@angular/router';
import { catchError, of, switchMap } from 'rxjs';
import { SeoService } from '../../../../core/seo/seo.service';
import { CatalogApi } from '../../../catalog/data-access/catalog-api.service';
import {
  CatalogProduct,
  ProductListResponse,
} from '../../../catalog/data-access/catalog.models';

@Component({
  selector: 'app-home-page',
  standalone: true,
  imports: [CurrencyPipe, RouterLink],
  template: `
    <section class="hero" aria-labelledby="home-title">
      <div class="hero-copy">
        <p class="eyebrow">Papelería creativa · Regalos con intención</p>
        <h1 id="home-title">Un detalle pequeño.<br />Un recuerdo para siempre.</h1>
        <p class="intro">
          Creamos piezas personalizadas para acompañar tus celebraciones,
          desde la primera idea hasta el último moño.
        </p>
        <a class="button" routerLink="/" fragment="catalogo">Explorar la tienda</a>
      </div>
      <div class="hero-art" role="img" aria-label="Composición de papelería y regalos en tonos lila">
        <span class="orbit orbit-one"></span>
        <span class="orbit orbit-two"></span>
        <span class="gift-card">hecho<br />para ti</span>
        <span class="ribbon ribbon-one"></span>
        <span class="ribbon ribbon-two"></span>
      </div>
    </section>

    <section class="catalog-intro" id="catalogo" aria-labelledby="catalog-title">
      <p class="eyebrow">Diseñado para tus momentos</p>
      <h2 id="catalog-title">Detalles que cuentan tu historia</h2>
      <p>
        Papelería y regalos personalizados para celebrar con detalles pensados
        para cada ocasión.
      </p>
    </section>

    <section class="featured" aria-labelledby="featured-title">
      <div class="featured-heading">
        <div>
          <p class="eyebrow">El catálogo</p>
          <h2 id="featured-title">Encuentra tu próximo detalle</h2>
        </div>
        <a routerLink="/" fragment="catalogo">Ver el catálogo</a>
      </div>

      @if (loading()) {
        <p role="status">Cargando productos…</p>
      } @else if (error()) {
        <p role="status">El catálogo no está disponible en este momento.</p>
      } @else if (products().length) {
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
        <p>Estamos preparando nuevos productos para ti.</p>
      }
    </section>
  `,
  styleUrl: './home-page.css',
})
export class HomePage {
  private readonly catalog = inject(CatalogApi);
  private readonly seo = inject(SeoService);
  private readonly destroyRef = inject(DestroyRef);

  readonly loading = signal(true);
  readonly error = signal(false);
  readonly products = signal<CatalogProduct[]>([]);

  constructor() {
    this.catalog
      .getProducts(1, 8, { featured: true })
      .pipe(
        switchMap((featured) =>
          featured.items.length
            ? of(featured)
            : this.catalog.getProducts(1, 8),
        ),
        catchError(() => {
          this.error.set(true);
          return of({
            items: [],
            pagination: { page: 1, per_page: 8, total: 0 },
          } satisfies ProductListResponse);
        }),
        takeUntilDestroyed(this.destroyRef),
      )
      .subscribe(({ items }) => {
        this.products.set(items);
        this.loading.set(false);
        this.seo.apply(
          {
            title: 'Papelería creativa y regalos personalizados | Design Lila',
            description:
              'Encuentra papelería y regalos personalizados para celebrar cumpleaños, nacimientos y momentos especiales.',
            indexable: items.length > 0,
            canonicalPath: '/',
          },
          '/',
        );
        if (items.length) {
          this.seo.setStructuredData({
            '@context': 'https://schema.org',
            '@graph': [
              {
                '@type': 'Organization',
                name: 'Design Lila',
                url: 'https://www.designlila.com/',
                logo: 'https://www.designlila.com/favicon.ico',
              },
              {
                '@type': 'WebSite',
                name: 'Design Lila',
                url: 'https://www.designlila.com/',
                inLanguage: 'es-CO',
              },
              {
                '@type': 'ItemList',
                name: 'Productos de Design Lila',
                itemListElement: items.map((item, index) => ({
                  '@type': 'ListItem',
                  position: index + 1,
                  url: `https://www.designlila.com/product/${item.slug}/`,
                  name: item.nombre,
                })),
              },
            ],
          });
        }
      });
  }
}
