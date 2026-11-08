export interface CatalogImage {
  id: number;
  ruta: string;
  texto_alternativo: string | null;
  es_principal: number;
  orden: number;
}

export interface ProductVariant {
  id: number;
  sku: string;
  nombre: string;
  atributos: Record<string, string>;
  precio: string | null;
  in_stock: number;
}

export interface CatalogProduct {
  id: number;
  categoria_id: number | null;
  categoria: string | null;
  sku: string;
  nombre: string;
  slug: string;
  descripcion: string;
  descripcion_corta: string;
  precio: string;
  precio_oferta: string | null;
  controlar_stock: number;
  peso: string | null;
  meta_title: string | null;
  meta_description: string | null;
  destacado: number;
  modo_inventario: 'simple' | 'variantes';
  in_stock: number;
  images: CatalogImage[];
  variants: ProductVariant[];
}

export interface ProductListResponse {
  items: CatalogProduct[];
  pagination: {
    page: number;
    per_page: number;
    total: number;
  };
}
