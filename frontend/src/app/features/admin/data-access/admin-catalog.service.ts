import { inject, Injectable } from '@angular/core';
import { Observable } from 'rxjs';
import { ApiClient } from '../../../core/api/api-client.service';
import {
  CatalogCategory,
  CatalogProduct,
  ProductListResponse,
} from '../../catalog/data-access/catalog.models';
import { AdminAuthService } from './admin-auth.service';

export interface ProductDraft {
  category_id: number | null;
  sku: string;
  name: string;
  slug: string;
  description: string;
  short_description: string;
  price: string;
  sale_price: string | null;
  stock_control: boolean;
  minimum_stock: number;
  weight: string | null;
  meta_title: string | null;
  meta_description: string | null;
  featured: boolean;
  inventory_mode: 'simple' | 'variantes';
  images: Array<{ path: string; alt: string; primary: boolean; order: number }>;
  variants?: Array<{
    id?: number;
    sku: string;
    name: string;
    attributes: Record<string, unknown>;
    price: string | null;
    minimum_stock: number;
  }>;
}

export interface CategoryDraft {
  parent_id: number | null;
  name: string;
  slug: string;
  description: string;
  image: string | null;
  meta_title: string | null;
  meta_description: string | null;
  order: number;
}

@Injectable({ providedIn: 'root' })
export class AdminCatalogService {
  private readonly api = inject(ApiClient);
  private readonly auth = inject(AdminAuthService);

  getProducts(page = 1, search = ''): Observable<ProductListResponse> {
    const query = new URLSearchParams({ page: String(page), per_page: '100' });
    if (search.trim()) query.set('search', search.trim());
    return this.api.get<ProductListResponse>(`admin/products?${query}`);
  }

  getProduct(id: number): Observable<CatalogProduct> {
    return this.api.get<CatalogProduct>(`admin/products/${id}`);
  }

  saveProduct(
    draft: ProductDraft,
    id?: number,
  ): Observable<CatalogProduct> {
    const token = this.auth.token();
    return id
      ? this.api.put<CatalogProduct, ProductDraft>(
          `admin/products/${id}`,
          draft,
          token,
        )
      : this.api.post<CatalogProduct, ProductDraft>(
          'admin/products',
          draft,
          token,
        );
  }

  deactivateProduct(id: number): Observable<{ deactivated: boolean }> {
    return this.api.delete(`admin/products/${id}`, this.auth.token());
  }

  getCategories(): Observable<CatalogCategory[]> {
    return this.api.get<CatalogCategory[]>('admin/categories');
  }

  saveCategory(
    draft: CategoryDraft,
    id?: number,
  ): Observable<CatalogCategory> {
    const token = this.auth.token();
    return id
      ? this.api.put<CatalogCategory, CategoryDraft>(
          `admin/categories/${id}`,
          draft,
          token,
        )
      : this.api.post<CatalogCategory, CategoryDraft>(
          'admin/categories',
          draft,
          token,
        );
  }

  deactivateCategory(id: number): Observable<{ deactivated: boolean }> {
    return this.api.delete(`admin/categories/${id}`, this.auth.token());
  }
}
