export interface SeoMetadata {
  title?: string;
  description?: string;
  indexable?: boolean;
  canonicalPath?: string;
  image?: string;
  type?: 'website' | 'product';
}
