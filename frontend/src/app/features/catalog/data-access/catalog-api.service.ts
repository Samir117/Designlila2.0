import { inject, Injectable } from '@angular/core';
import { Observable } from 'rxjs';
import { ApiClient } from '../../../core/api/api-client.service';
import { CatalogProduct, ProductListResponse } from './catalog.models';

@Injectable({ providedIn: 'root' })
export class CatalogApi {
  private readonly api = inject(ApiClient);

  getProducts(page = 1, perPage = 20): Observable<ProductListResponse> {
    const params = new URLSearchParams({
      page: String(page),
      per_page: String(perPage),
    });

    return this.api.get<ProductListResponse>(`products?${params.toString()}`);
  }

  getProduct(slug: string): Observable<CatalogProduct> {
    return this.api.get<CatalogProduct>(`products/${encodeURIComponent(slug)}`);
  }
}
