import { HttpErrorResponse } from '@angular/common/http';
import { Component, DestroyRef, inject, signal } from '@angular/core';
import { takeUntilDestroyed } from '@angular/core/rxjs-interop';
import { FormsModule } from '@angular/forms';
import { AdminCatalogService, CategoryDraft } from '../data-access/admin-catalog.service';
import { CatalogCategory } from '../../catalog/data-access/catalog.models';

function emptyDraft(): CategoryDraft {
  return {
    parent_id: null,
    name: '',
    slug: '',
    description: '',
    image: null,
    meta_title: null,
    meta_description: null,
    order: 0,
  };
}

@Component({
  selector: 'app-admin-categories-page',
  standalone: true,
  imports: [FormsModule],
  template: `
    <section aria-labelledby="categories-title">
      <div class="page-heading">
        <div>
          <p class="eyebrow">Catálogo</p>
          <h1 id="categories-title">Categorías</h1>
          <p>Crea y organiza las categorías que aparecen en la tienda.</p>
        </div>
        <button type="button" class="primary" (click)="newCategory()">Nueva categoría</button>
      </div>

      @if (message()) { <p class="success" role="status">{{ message() }}</p> }
      @if (error()) { <p class="error" role="alert">{{ error() }}</p> }

      @if (formOpen()) {
        <form class="editor" (ngSubmit)="save()" #categoryForm="ngForm">
          <div class="form-heading">
            <h2>{{ editingId() ? 'Editar categoría' : 'Crear categoría' }}</h2>
            <button type="button" class="quiet" (click)="closeForm()">Cancelar</button>
          </div>
          <div class="fields">
            <label>Nombre
              <input name="name" required maxlength="150" [(ngModel)]="draft.name" />
            </label>
            <label>Slug
              <input name="slug" required maxlength="180" pattern="[a-z0-9]+(-[a-z0-9]+)*" [(ngModel)]="draft.slug" />
            </label>
            <label>Categoría principal
              <select name="parent" [ngModel]="draft.parent_id ?? ''" (ngModelChange)="draft.parent_id = $event ? +$event : null">
                <option value="">Sin categoría principal</option>
                @for (category of activeCategories(); track category.id) {
                  @if (category.id !== editingId()) {
                    <option [value]="category.id">{{ category.nombre }}</option>
                  }
                }
              </select>
            </label>
            <label>Orden
              <input name="order" type="number" step="1" [(ngModel)]="draft.order" />
            </label>
            <label class="wide">Descripción
              <textarea name="description" rows="4" [(ngModel)]="draft.description"></textarea>
            </label>
            <label class="wide">Imagen (URL HTTPS o ruta del sitio)
              <input name="image" maxlength="255" [ngModel]="draft.image ?? ''" (ngModelChange)="draft.image = $event || null" />
            </label>
            <details class="wide">
              <summary>Metadatos SEO (opcional)</summary>
              <div class="fields">
                <label>Título SEO <input name="metaTitle" maxlength="255" [ngModel]="draft.meta_title ?? ''" (ngModelChange)="draft.meta_title = $event || null" /></label>
                <label>Descripción SEO <textarea name="metaDescription" maxlength="320" rows="2" [ngModel]="draft.meta_description ?? ''" (ngModelChange)="draft.meta_description = $event || null"></textarea></label>
              </div>
            </details>
          </div>
          <div class="form-actions">
            <button type="submit" class="primary" [disabled]="categoryForm.invalid || saving()">
              {{ saving() ? 'Guardando…' : 'Guardar categoría' }}
            </button>
            <button type="button" class="quiet" (click)="closeForm()">Cancelar</button>
          </div>
        </form>
      }

      @if (loading()) {
        <p role="status">Cargando categorías…</p>
      } @else if (categories().length) {
        <div class="table-wrap">
          <table>
            <thead><tr><th>Categoría</th><th>Slug</th><th>Estado</th><th>Orden</th><th>Acciones</th></tr></thead>
            <tbody>
              @for (category of categories(); track category.id) {
                <tr>
                  <td><strong>{{ category.nombre }}</strong></td>
                  <td>{{ category.slug }}</td>
                  <td>{{ category.estado === 0 ? 'Inactiva' : 'Activa' }}</td>
                  <td>{{ category.orden }}</td>
                  <td class="actions">
                    <button type="button" class="quiet" (click)="editCategory(category)">Editar</button>
                    @if (category.estado !== 0) {
                      <button type="button" class="danger" (click)="deactivate(category)">Desactivar</button>
                    }
                  </td>
                </tr>
              }
            </tbody>
          </table>
        </div>
      } @else {
        <p>Aún no hay categorías.</p>
      }
    </section>
  `,
  styles: `
    h1 { margin:.1em 0; color:var(--color-heading); font:500 clamp(2rem,5vw,3rem) var(--font-display); }
    h2 { margin:0; color:var(--color-heading); font:500 1.6rem var(--font-display); }
    .eyebrow { margin:0; color:var(--color-accent); font-weight:700; }
    .page-heading,.form-heading,.form-actions { display:flex; align-items:center; justify-content:space-between; gap:14px; }
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
    .form-actions { justify-content:flex-start; margin-top:20px; }
    .table-wrap { overflow-x:auto; border:1px solid var(--color-border); border-radius:12px; }
    table { width:100%; border-collapse:collapse; text-align:left; }
    th,td { padding:13px 15px; border-bottom:1px solid var(--color-border); vertical-align:middle; }
    th { background:var(--color-secondary); color:var(--color-heading); }
    tbody tr:last-child td { border-bottom:0; }
    .actions { white-space:nowrap; }
    .actions button+button { margin-left:6px; }
    .error { color:var(--color-danger); }
    .success { color:var(--color-success); }
    .quiet { background:transparent; }
    @media(max-width:650px) { .page-heading,.form-heading { align-items:flex-start; flex-direction:column; } .fields { grid-template-columns:1fr; } .wide { grid-column:auto; } }
  `,
})
export class AdminCategoriesPage {
  private readonly catalog = inject(AdminCatalogService);
  private readonly destroyRef = inject(DestroyRef);

  readonly categories = signal<CatalogCategory[]>([]);
  readonly loading = signal(false);
  readonly saving = signal(false);
  readonly formOpen = signal(false);
  readonly editingId = signal<number | null>(null);
  readonly error = signal('');
  readonly message = signal('');
  readonly activeCategories = () => this.categories().filter((category) => category.estado !== 0);

  draft = emptyDraft();

  constructor() {
    this.load();
  }

  load(): void {
    this.loading.set(true);
    this.error.set('');
    this.catalog.getCategories().pipe(takeUntilDestroyed(this.destroyRef)).subscribe({
      next: (categories) => {
        this.categories.set(categories);
        this.loading.set(false);
      },
      error: (error: unknown) => this.fail(error, 'No se pudieron cargar las categorías.'),
    });
  }

  newCategory(): void {
    this.draft = emptyDraft();
    this.editingId.set(null);
    this.formOpen.set(true);
    this.message.set('');
    this.error.set('');
  }

  editCategory(category: CatalogCategory): void {
    this.draft = {
      parent_id: category.categoria_padre_id,
      name: category.nombre,
      slug: category.slug,
      description: category.descripcion ?? '',
      image: category.imagen,
      meta_title: category.meta_title,
      meta_description: category.meta_description,
      order: category.orden,
    };
    this.editingId.set(category.id);
    this.formOpen.set(true);
    this.message.set('');
    this.error.set('');
  }

  save(): void {
    if (this.saving()) return;
    this.saving.set(true);
    this.error.set('');
    this.message.set('');
    const draft: CategoryDraft = {
      ...this.draft,
      name: this.draft.name.trim(),
      slug: this.draft.slug.trim(),
    };
    this.catalog.saveCategory(draft, this.editingId() ?? undefined)
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe({
        next: () => {
          this.saving.set(false);
          this.formOpen.set(false);
          this.message.set('Categoría guardada correctamente.');
          this.load();
        },
        error: (error: unknown) => {
          this.saving.set(false);
          this.fail(error, 'No se pudo guardar la categoría.');
        },
      });
  }

  deactivate(category: CatalogCategory): void {
    if (!confirm(`¿Desactivar "${category.nombre}"?`)) return;
    this.error.set('');
    this.catalog.deactivateCategory(category.id).pipe(takeUntilDestroyed(this.destroyRef)).subscribe({
      next: () => {
        this.message.set('Categoría desactivada.');
        this.load();
      },
      error: (error: unknown) => this.fail(error, 'No se pudo desactivar la categoría.'),
    });
  }

  closeForm(): void {
    this.formOpen.set(false);
    this.error.set('');
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
