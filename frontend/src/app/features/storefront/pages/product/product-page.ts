import { CurrencyPipe } from '@angular/common';
import { HttpErrorResponse } from '@angular/common/http';
import { Component, DestroyRef, inject, signal } from '@angular/core';
import { takeUntilDestroyed } from '@angular/core/rxjs-interop';
import { ActivatedRoute } from '@angular/router';
import { catchError, distinctUntilChanged, EMPTY, map, switchMap, tap } from 'rxjs';
import { SeoService } from '../../../../core/seo/seo.service';
import { FeedbackPanel } from '../../../../shared/ui/feedback-panel/feedback-panel';
import { CatalogApi } from '../../../catalog/data-access/catalog-api.service';
import { CatalogProduct } from '../../../catalog/data-access/catalog.models';

@Component({
  selector: 'app-product-page',
  standalone: true,
  imports: [CurrencyPipe, FeedbackPanel],
  template: `
    @if (loading()) {
      <app-feedback-panel
        title="Cargando producto"
        message="Estamos consultando la información más reciente."
      />
    } @else if (error()) {
      <app-feedback-panel
        tone="error"
        title="No pudimos cargar este producto"
        [message]="error()!"
      />
    } @else if (product(); as item) {
      <article class="product">
        <div class="product-image">
          @if (item.images[0]; as image) {
            <img
              [src]="image.ruta"
              [alt]="image.texto_alternativo || item.nombre"
              width="720"
              height="720"
              fetchpriority="high"
            />
          } @else {
            <div class="image-placeholder" role="img" [attr.aria-label]="item.nombre">
              Imagen próximamente
            </div>
          }
        </div>
        <div class="product-details">
          @if (item.categoria) {
            <p class="eyebrow">{{ item.categoria }}</p>
          }
          <h1>{{ item.nombre }}</h1>
          @if (item.descripcion_corta) {
            <p class="summary">{{ item.descripcion_corta }}</p>
          }
          <p class="price">
            {{ (item.precio_oferta || item.precio) | currency: 'COP' : 'symbol' : '1.0-0' : 'es-CO' }}
          </p>
          <p class="availability">
            {{ item.in_stock ? 'Disponible para comprar' : 'Consulta disponibilidad' }}
          </p>
        </div>
      </article>
    }
  `,
  styleUrl: './product-page.css',
})
export class ProductPage {
  private readonly route = inject(ActivatedRoute);
  private readonly catalog = inject(CatalogApi);
  private readonly seo = inject(SeoService);
  private readonly destroyRef = inject(DestroyRef);

  readonly loading = signal(true);
  readonly error = signal<string | null>(null);
  readonly product = signal<CatalogProduct | null>(null);

  constructor() {
    this.route.paramMap
      .pipe(
        map((params) => params.get('slug') ?? ''),
        distinctUntilChanged(),
        tap(() => {
          this.loading.set(true);
          this.error.set(null);
          this.product.set(null);
        }),
        switchMap((slug) =>
          this.catalog.getProduct(slug).pipe(
            catchError((error: unknown) => {
              this.loading.set(false);
              this.error.set(this.errorMessage(error));
              return EMPTY;
            }),
          ),
        ),
        takeUntilDestroyed(this.destroyRef),
      )
      .subscribe((product) => {
        this.product.set(product);
        this.loading.set(false);
        this.seo.apply(
          {
            title: product.meta_title || `${product.nombre} | Design Lila`,
            description: product.meta_description || product.descripcion_corta,
            indexable: false,
          },
          `/product/${product.slug}/`,
        );
      });
  }

  private errorMessage(error: unknown): string {
    if (error instanceof HttpErrorResponse && error.status === 404) {
      return 'El producto no existe o ya no está disponible.';
    }

    if (error instanceof Error) {
      return error.message;
    }

    return 'Intenta de nuevo en unos minutos.';
  }
}
