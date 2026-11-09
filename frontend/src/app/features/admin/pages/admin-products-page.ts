import { HttpErrorResponse } from '@angular/common/http';
import { Component, DestroyRef, inject, signal } from '@angular/core';
import { takeUntilDestroyed } from '@angular/core/rxjs-interop';
import { FormsModule } from '@angular/forms';
import { AdminCatalogService, ProductDraft } from '../data-access/admin-catalog.service';
import { CatalogCategory, CatalogProduct } from '../../catalog/data-access/catalog.models';

function emptyDraft(): ProductDraft {
  return {
    category_id: null,
    sku: '',
    name: '',
    slug: '',
    description: '',
    short_description: '',
    price: '',
    sale_price: null,
    stock_control: true,
    minimum_stock: 0,
    weight: null,
    meta_title: null,
    meta_description: null,
    featured: false,
    inventory_mode: 'simple',
    images: [],
  };
}

interface VariantEditorDraft {
  id?: number;
  sku: string;
  name: string;
  attributesText: string;
  price: string;
  minimum_stock: number;
}

@Component({
  selector: 'app-admin-products-page',
  standalone: true,
  imports: [FormsModule],
  template: `
    <section aria-labelledby="products-title">
      <div class="page-heading">
        <div>
          <p class="eyebrow">Catálogo</p>
          <h1 id="products-title">Productos</h1>
          <p>Los cambios se guardan en la base de datos y no requieren redesplegar la aplicación.</p>
        </div>
        <button type="button" class="primary" (click)="newProduct()">Nuevo producto</button>
      </div>

      @if (message()) { <p class="success" role="status">{{ message() }}</p> }
      @if (error()) { <p class="error" role="alert">{{ error() }}</p> }

      @if (formOpen()) {
        <form class="editor" (ngSubmit)="save()" #productForm="ngForm">
          <div class="form-heading">
            <h2>{{ editingId() ? 'Editar producto' : 'Crear producto' }}</h2>
            <button type="button" class="quiet" (click)="closeForm()">Cancelar</button>
          </div>

          <div class="fields">
            <label>Tipo de inventario
              <select name="inventoryMode" [(ngModel)]="draft.inventory_mode" [disabled]="editingId() !== null">
                <option value="simple">Producto simple</option>
                <option value="variantes">Producto con variantes</option>
              </select>
            </label>
            <label>Nombre
              <input name="name" required maxlength="200" [(ngModel)]="draft.name" />
            </label>
            <label>SKU
              <input name="sku" required maxlength="80" [(ngModel)]="draft.sku" />
            </label>
            <label>Slug
              <input name="slug" required maxlength="220" pattern="[a-z0-9]+(-[a-z0-9]+)*" [(ngModel)]="draft.slug" />
            </label>
            <label>Categoría
              <select name="category" [ngModel]="draft.category_id ?? ''" (ngModelChange)="draft.category_id = $event ? +$event : null">
                <option value="">Sin categoría</option>
                @for (category of activeCategories(); track category.id) {
                  <option [value]="category.id">{{ category.nombre }}</option>
                }
              </select>
            </label>
            <label>Precio
              <input name="price" type="number" min="0.01" step="0.01" required [(ngModel)]="draft.price" />
            </label>
            <label>Precio de oferta (opcional)
              <input name="salePrice" type="number" min="0.01" step="0.01" [ngModel]="draft.sale_price ?? ''" (ngModelChange)="draft.sale_price = $event || null" />
            </label>
            @if (draft.inventory_mode === 'simple') {
              <label>Stock mínimo
                <input name="minimumStock" type="number" min="0" step="1" [(ngModel)]="draft.minimum_stock" />
              </label>
              <label class="check"><input name="stockControl" type="checkbox" [(ngModel)]="draft.stock_control" /> Controlar inventario</label>
              <p class="notice wide">El stock inicial o los ajustes de existencias se gestionan desde el módulo de inventario; no se modifican al editar el producto.</p>
            }
            <label class="check"><input name="featured" type="checkbox" [(ngModel)]="draft.featured" /> Mostrar como destacado</label>
            <label class="wide">Descripción corta
              <textarea name="shortDescription" maxlength="500" rows="2" [(ngModel)]="draft.short_description"></textarea>
            </label>
            <label class="wide">Descripción
              <textarea name="description" rows="5" [(ngModel)]="draft.description"></textarea>
            </label>
            <label class="wide">Imágenes (URL o ruta, una por línea)
              <textarea name="images" rows="3" [(ngModel)]="imagesText" placeholder="/uploads/producto.jpg"></textarea>
            </label>
            <label class="wide">Texto alternativo de las imágenes
              <input name="imageAlt" maxlength="255" [(ngModel)]="imageAlt" />
            </label>
            @if (draft.inventory_mode === 'variantes') {
              <section class="wide variants" aria-labelledby="variants-title">
                <div class="form-heading">
                  <div>
                    <h3 id="variants-title">Variantes</h3>
                    <p class="muted">El stock de cada variante se registra desde inventario. Los atributos deben ser un objeto JSON, por ejemplo &#123;"talla":"M","color":"Lila"&#125;.</p>
                  </div>
                  <button type="button" class="quiet" (click)="addVariant()">Agregar variante</button>
                </div>
                @for (variant of variantDrafts; track $index; let index = $index) {
                  <div class="variant-fields">
                    <label>Nombre <input [name]="'variantName' + index" required maxlength="150" [(ngModel)]="variant.name" /></label>
                    <label>SKU <input [name]="'variantSku' + index" required maxlength="80" [(ngModel)]="variant.sku" /></label>
                    <label>Precio (opcional) <input [name]="'variantPrice' + index" type="number" min="0.01" step="0.01" [(ngModel)]="variant.price" /></label>
                    <label>Stock mínimo <input [name]="'variantMinStock' + index" type="number" min="0" step="1" [(ngModel)]="variant.minimum_stock" /></label>
                    <label class="wide">Atributos JSON <textarea [name]="'variantAttributes' + index" rows="2" [(ngModel)]="variant.attributesText"></textarea></label>
                    <button type="button" class="danger" (click)="removeVariant(index)">Quitar variante</button>
                  </div>
                }
                @if (!variantDrafts.length) { <p class="error">Agrega al menos una variante.</p> }
              </section>
            }
            <details class="wide">
              <summary>Metadatos SEO (opcional)</summary>
              <div class="fields metadata">
                <label>Título SEO <input name="metaTitle" maxlength="255" [ngModel]="draft.meta_title ?? ''" (ngModelChange)="draft.meta_title = $event || null" /></label>
                <label>Descripción SEO <textarea name="metaDescription" maxlength="320" rows="2" [ngModel]="draft.meta_description ?? ''" (ngModelChange)="draft.meta_description = $event || null"></textarea></label>
              </div>
            </details>
          </div>
          <div class="form-actions">
            <button type="submit" class="primary" [disabled]="productForm.invalid || saving()">
              {{ saving() ? 'Guardando…' : 'Guardar producto' }}
            </button>
            <button type="button" class="quiet" (click)="closeForm()">Cancelar</button>
          </div>
        </form>
      }

      <form class="search" (ngSubmit)="searchProducts()">
        <label for="product-search">Buscar por nombre, SKU o descripción</label>
        <input id="product-search" name="search" [(ngModel)]="search" />
        <button type="submit" [disabled]="loading()">Buscar</button>
      </form>

      @if (loading()) {
        <p role="status">Cargando productos…</p>
      } @else if (products().length) {
        <div class="table-wrap">
          <table>
            <thead><tr><th>Producto</th><th>SKU</th><th>Precio</th><th>Estado</th><th>Acciones</th></tr></thead>
            <tbody>
              @for (product of products(); track product.id) {
                <tr>
                  <td><strong>{{ product.nombre }}</strong><small>{{ product.slug }}</small></td>
                  <td>{{ product.sku }}</td>
                  <td>
                    {{ product.precio }}
                    @if (product.precio_oferta) { <small>Oferta: {{ product.precio_oferta }}</small> }
                  </td>
                  <td>{{ product.estado === 0 ? 'Inactivo' : 'Activo' }}</td>
                  <td class="actions">
                    <button type="button" class="quiet" (click)="editProduct(product)">Editar</button>
                    @if (product.estado !== 0) {
                      <button type="button" class="danger" (click)="deactivate(product)">Desactivar</button>
                    }
                  </td>
                </tr>
              }
            </tbody>
          </table>
        </div>
        <div class="pagination">
          <button type="button" class="quiet" [disabled]="page() <= 1 || loading()" (click)="changePage(page() - 1)">Anterior</button>
          <span>Página {{ page() }} · {{ total() }} productos</span>
          <button type="button" class="quiet" [disabled]="page() * 100 >= total() || loading()" (click)="changePage(page() + 1)">Siguiente</button>
        </div>
      } @else {
        <p>No hay productos que coincidan con la búsqueda.</p>
      }
    </section>
  `,
  styles: `
    :host { display:block; }
    h1 { margin:.1em 0; color:var(--color-heading); font:500 clamp(2rem,5vw,3rem) var(--font-display); }
    h2 { margin:0; color:var(--color-heading); font:500 1.6rem var(--font-display); }
    .eyebrow { margin:0; color:var(--color-accent); font-weight:700; }
    .page-heading,.form-heading,.form-actions,.search,.pagination { display:flex; align-items:center; justify-content:space-between; gap:14px; }
    .page-heading { margin-bottom:24px; }
    button { min-height:40px; padding:8px 14px; border:1px solid var(--color-border); border-radius:var(--radius-pill); background:var(--color-background); color:var(--color-heading); font:inherit; font-weight:650; cursor:pointer; }
    button:disabled { cursor:not-allowed; opacity:.55; }
    button.primary { border-color:var(--color-accent); background:var(--color-accent); color:var(--color-background); }
    button.danger { color:var(--color-danger); }
    .editor { margin:24px 0; padding:24px; border:1px solid var(--color-border); border-radius:var(--radius-card); background:var(--color-background); }
    .fields { display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); gap:16px; margin-top:20px; }
    label { display:grid; gap:6px; font-weight:600; }
    input,select,textarea { width:100%; min-height:42px; padding:9px 11px; border:1px solid var(--color-border); border-radius:8px; background:var(--color-background); color:var(--color-body); font:inherit; }
    textarea { resize:vertical; }
    .wide { grid-column:1/-1; }
    .check { display:flex; align-items:center; gap:9px; }
    .check input { width:18px; min-height:18px; }
    .metadata { margin-top:14px; }
    .variants { display:grid; gap:14px; padding:16px; border:1px solid var(--color-border); border-radius:12px; }
    .variant-fields { display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); gap:12px; padding:14px 0; border-top:1px solid var(--color-border); }
    .muted { color:var(--color-body-muted); }
    .form-actions { justify-content:flex-start; margin-top:20px; }
    .search { justify-content:flex-start; flex-wrap:wrap; margin:24px 0 14px; }
    .search label { flex-basis:100%; }
    .search input { width:min(440px,100%); }
    .table-wrap { overflow-x:auto; border:1px solid var(--color-border); border-radius:12px; }
    table { width:100%; border-collapse:collapse; text-align:left; }
    th,td { padding:13px 15px; border-bottom:1px solid var(--color-border); vertical-align:middle; }
    th { background:var(--color-secondary); color:var(--color-heading); }
    tbody tr:last-child td { border-bottom:0; }
    td small { display:block; margin-top:4px; color:var(--color-body-muted); }
    .actions { white-space:nowrap; }
    .actions button+button { margin-left:6px; }
    .pagination { justify-content:center; margin-top:16px; }
    .error { color:var(--color-danger); }
    .success { color:var(--color-success); }
    .notice { padding:12px; border-radius:8px; background:var(--color-secondary); }
    .quiet { background:transparent; }
    @media(max-width:650px) { .page-heading,.form-heading { align-items:flex-start; flex-direction:column; } .fields,.variant-fields { grid-template-columns:1fr; } .wide { grid-column:auto; } .pagination { flex-wrap:wrap; } }
  `,
})
export class AdminProductsPage {
  private readonly catalog = inject(AdminCatalogService);
  private readonly destroyRef = inject(DestroyRef);

  readonly products = signal<CatalogProduct[]>([]);
  readonly categories = signal<CatalogCategory[]>([]);
  readonly loading = signal(false);
  readonly saving = signal(false);
  readonly formOpen = signal(false);
  readonly editingId = signal<number | null>(null);
  readonly error = signal('');
  readonly message = signal('');
  readonly page = signal(1);
  readonly total = signal(0);
  readonly activeCategories = () => this.categories().filter((category) => category.estado !== 0);

  draft = emptyDraft();
  variantDrafts: VariantEditorDraft[] = [];
  imagesText = '';
  imageAlt = '';
  search = '';

  constructor() {
    this.loadCategories();
    this.loadProducts();
  }

  loadProducts(): void {
    this.loading.set(true);
    this.error.set('');
    this.catalog.getProducts(this.page(), this.search).pipe(takeUntilDestroyed(this.destroyRef)).subscribe({
      next: (result) => {
        this.products.set(result.items);
        this.total.set(result.pagination.total);
        this.loading.set(false);
      },
      error: (error: unknown) => this.fail(error, 'No se pudieron cargar los productos.'),
    });
  }

  private loadCategories(): void {
    this.catalog.getCategories().pipe(takeUntilDestroyed(this.destroyRef)).subscribe({
      next: (categories) => this.categories.set(categories),
      error: (error: unknown) => this.fail(error, 'No se pudieron cargar las categorías.'),
    });
  }

  searchProducts(): void {
    this.page.set(1);
    this.loadProducts();
  }

  changePage(page: number): void {
    this.page.set(page);
    this.loadProducts();
  }

  newProduct(): void {
    this.draft = emptyDraft();
    this.variantDrafts = [];
    this.imagesText = '';
    this.imageAlt = '';
    this.editingId.set(null);
    this.formOpen.set(true);
    this.error.set('');
    this.message.set('');
  }

  editProduct(product: CatalogProduct): void {
    this.error.set('');
    this.message.set('');
    this.catalog.getProduct(product.id).pipe(takeUntilDestroyed(this.destroyRef)).subscribe({
      next: (detail) => {
        const admin = detail;
        this.draft = {
          ...emptyDraft(),
          category_id: admin.categoria_id,
          sku: admin.sku,
          name: admin.nombre,
          slug: admin.slug,
          description: admin.descripcion ?? '',
          short_description: admin.descripcion_corta ?? '',
          price: admin.precio,
          sale_price: admin.precio_oferta,
          stock_control: Boolean(admin.controlar_stock),
          minimum_stock: admin.stock_minimo ?? 0,
          weight: admin.peso,
          meta_title: admin.meta_title,
          meta_description: admin.meta_description,
          featured: Boolean(admin.destacado),
          inventory_mode: admin.modo_inventario,
          images: admin.images.map((image, order) => ({
            path: image.ruta,
            alt: image.texto_alternativo ?? '',
            primary: Boolean(image.es_principal) || order === 0,
            order: image.orden,
          })),
        };
        this.variantDrafts = admin.variants
          .filter((variant) => variant.estado !== 0)
          .map((variant) => ({
            id: variant.id,
            sku: variant.sku,
            name: variant.nombre,
            attributesText: JSON.stringify(variant.atributos ?? {}),
            price: variant.precio ?? '',
            minimum_stock: variant.stock_minimo ?? 0,
          }));
        this.imagesText = admin.images.map((image) => image.ruta).join('\n');
        this.imageAlt = admin.images[0]?.texto_alternativo ?? '';
        this.editingId.set(product.id);
        this.formOpen.set(true);
      },
      error: (error: unknown) => this.fail(error, 'No se pudo cargar el producto.'),
    });
  }

  save(): void {
    if (this.saving()) return;
    if (this.draft.inventory_mode === 'variantes' && this.variantDrafts.length === 0) {
      this.error.set('Agrega al menos una variante para guardar este producto.');
      return;
    }
    this.saving.set(true);
    this.error.set('');
    this.message.set('');
    const images = this.imagesText
      .split(/\r?\n/)
      .map((path) => path.trim())
      .filter(Boolean)
      .map((path, order) => ({
        path,
        alt: this.imageAlt.trim(),
        primary: order === 0,
        order,
      }));
    let variants: NonNullable<ProductDraft['variants']> | undefined;
    if (this.draft.inventory_mode === 'variantes') {
      try {
        variants = this.variantDrafts.map((variant) => {
          const parsed: unknown = JSON.parse(variant.attributesText || '{}');
          if (!parsed || typeof parsed !== 'object' || Array.isArray(parsed)) {
            throw new Error('Los atributos de cada variante deben ser un objeto JSON.');
          }
          return {
            ...(variant.id ? { id: variant.id } : {}),
            sku: variant.sku.trim(),
            name: variant.name.trim(),
            attributes: Object.fromEntries(Object.entries(parsed)),
            price: variant.price ? String(variant.price) : null,
            minimum_stock: Number(variant.minimum_stock),
          };
        });
      } catch (error: unknown) {
        this.saving.set(false);
        this.error.set(
          error instanceof SyntaxError
            ? 'Revisa el JSON de atributos de las variantes.'
            : error instanceof Error
              ? error.message
              : 'No se pudieron validar las variantes.',
        );
        return;
      }
    }
    const draft: ProductDraft = {
      ...this.draft,
      name: this.draft.name.trim(),
      sku: this.draft.sku.trim(),
      slug: this.draft.slug.trim(),
      price: String(this.draft.price),
      sale_price: this.draft.sale_price ? String(this.draft.sale_price) : null,
      images,
      ...(variants ? { variants } : {}),
    };

    this.catalog.saveProduct(draft, this.editingId() ?? undefined)
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe({
        next: () => {
          this.saving.set(false);
          this.formOpen.set(false);
          this.message.set('Producto guardado correctamente.');
          this.loadProducts();
        },
        error: (error: unknown) => {
          this.saving.set(false);
          this.fail(error, 'No se pudo guardar el producto.');
        },
      });
  }

  deactivate(product: CatalogProduct): void {
    if (!confirm(`¿Desactivar "${product.nombre}"? Dejará de mostrarse en la tienda.`)) return;
    this.error.set('');
    this.catalog.deactivateProduct(product.id).pipe(takeUntilDestroyed(this.destroyRef)).subscribe({
      next: () => {
        this.message.set('Producto desactivado.');
        this.loadProducts();
      },
      error: (error: unknown) => this.fail(error, 'No se pudo desactivar el producto.'),
    });
  }

  closeForm(): void {
    this.formOpen.set(false);
    this.error.set('');
  }

  addVariant(): void {
    this.variantDrafts = [
      ...this.variantDrafts,
      { sku: '', name: '', attributesText: '{}', price: '', minimum_stock: 0 },
    ];
  }

  removeVariant(index: number): void {
    this.variantDrafts = this.variantDrafts.filter((_, itemIndex) => itemIndex !== index);
  }

  private fail(error: unknown, fallback: string): void {
    this.loading.set(false);
    const message =
      error instanceof HttpErrorResponse
        ? error.error?.error?.message
        : error instanceof Error
          ? error.message
          : null;
    this.error.set(typeof message === 'string' ? message : fallback);
  }
}
