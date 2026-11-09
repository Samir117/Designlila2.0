import { inject, Injectable } from '@angular/core';
import { Observable } from 'rxjs';
import { ApiClient } from '../../../core/api/api-client.service';
import {
  CatalogCategory,
  CatalogProduct,
  ProductListResponse,
} from './catalog.models';

@Injectable({ providedIn: 'root' })
export class CatalogApi {
  private readonly api = inject(ApiClient);

  getProducts(
    page = 1,
    perPage = 20,
    filters: { category?: string; featured?: boolean } = {},
  ): Observable<ProductListResponse> {
    const query = new URLSearchParams({
      page: String(page),
      per_page: String(perPage),
    });
    if (filters.category) {
      query.set('category', filters.category);
    }
    if (filters.featured) {
      query.set('featured', '1');
    }

    return this.api.get<ProductListResponse>(`products?${query.toString()}`);
  }

  getCategories(): Observable<CatalogCategory[]> {
    return this.api.get<CatalogCategory[]>('categories');
  }

  getProduct(slug: string): Observable<CatalogProduct> {
    return this.api.get<CatalogProduct>(`products/${encodeURIComponent(slug)}`);
  }
}
