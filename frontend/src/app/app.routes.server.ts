import { inject } from '@angular/core';
import { RenderMode, ServerRoute } from '@angular/ssr';
import { API_BASE_URL } from './core/api/api-base-url';

export const serverRoutes: ServerRoute[] = [
  {
    path: '',
    renderMode: RenderMode.Prerender,
  },
  {
    path: 'product/:slug',
    renderMode: RenderMode.Prerender,
    async getPrerenderParams() {
      const products = await getAllProducts();
      return products.map(({ slug }) => ({ slug }));
    },
  },
  {
    path: 'product-category/:slug',
    renderMode: RenderMode.Prerender,
    async getPrerenderParams() {
      const categories = await getCategories();
      return categories.map(({ slug }) => ({ slug }));
    },
  },
  {
    path: '**',
    renderMode: RenderMode.Client,
  },
];

interface ApiEnvelope<T> {
  success: boolean;
  data: T;
  error?: { message: string };
}

interface ProductSlug {
  slug: string;
}

interface ProductListResponse {
  items: ProductSlug[];
  pagination: {
    page: number;
    per_page: number;
    total: number;
  };
}

interface CategorySlug {
  slug: string;
}

async function getAllProducts(): Promise<ProductSlug[]> {
  const products: ProductSlug[] = [];
  let page = 1;
  let total = 0;

  do {
    const response = await getApiData<ProductListResponse>(
      `products?page=${page}&per_page=100`,
    );
    products.push(...response.items);
    total = response.pagination.total;
    page += 1;
  } while (products.length < total);

  return products;
}

async function getCategories(): Promise<CategorySlug[]> {
  return getApiData<CategorySlug[]>('categories');
}

async function getApiData<T>(path: string): Promise<T> {
  const apiBaseUrl = inject(API_BASE_URL).replace(/\/+$/, '');
  const response = await fetch(`${apiBaseUrl}/${path}`);

  if (!response.ok) {
    throw new Error(
      `SEO prerender data request failed (${response.status} ${response.statusText}): ${path}`,
    );
  }

  const envelope = (await response.json()) as ApiEnvelope<T>;
  if (!envelope.success) {
    throw new Error(
      `SEO prerender data request failed: ${envelope.error?.message ?? path}`,
    );
  }

  return envelope.data;
}
