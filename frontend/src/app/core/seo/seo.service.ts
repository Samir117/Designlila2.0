import { DOCUMENT } from '@angular/common';
import { inject, Injectable } from '@angular/core';
import { Meta, MetaDefinition, Title } from '@angular/platform-browser';
import { environment } from '../../../environments/environment';
import { SeoMetadata } from './seo-metadata';

@Injectable({ providedIn: 'root' })
export class SeoService {
  private readonly title = inject(Title);
  private readonly meta = inject(Meta);
  private readonly document = inject(DOCUMENT);

  apply(metadata: SeoMetadata, currentUrl: string): void {
    const title = metadata.title ?? 'Design Lila | Detalles para celebrar';
    const canonicalUrl = this.setCanonical(metadata.canonicalPath ?? currentUrl);
    this.title.setTitle(title);

    if (metadata.description) {
      this.meta.updateTag({
        name: 'description',
        content: metadata.description,
      });
    } else {
      this.meta.removeTag('name="description"');
    }

    const indexable = environment.indexingEnabled && metadata.indexable === true;
    this.meta.updateTag(
      {
        name: 'robots',
        content: indexable ? 'index, follow' : 'noindex, nofollow',
      },
      'name="robots"',
    );
    this.updateTag('og:title', title, 'property');
    this.updateTag('og:description', metadata.description ?? '', 'property');
    this.updateTag('og:type', metadata.type ?? 'website', 'property');
    this.updateTag('og:url', canonicalUrl, 'property');
    this.updateTag('og:site_name', 'Design Lila', 'property');
    this.updateTag('og:image', metadata.image ?? '', 'property');
    this.updateTag(
      'twitter:card',
      metadata.image ? 'summary_large_image' : 'summary',
    );
    this.updateTag('twitter:title', title);
    this.updateTag('twitter:description', metadata.description ?? '');
    this.updateTag('twitter:image', metadata.image ?? '');

    if (!metadata.image) {
      this.meta.removeTag('property="og:image"');
      this.meta.removeTag('name="twitter:image"');
    }

    if (!metadata.description) {
      this.meta.removeTag('property="og:description"');
      this.meta.removeTag('name="twitter:description"');
    }

    this.clearStructuredData();
  }

  setStructuredData(value: Record<string, unknown>): void {
    const scriptId = 'design-lila-jsonld';
    let script = this.document.getElementById(scriptId) as HTMLScriptElement | null;
    if (!script) {
      script = this.document.createElement('script');
      script.id = scriptId;
      script.type = 'application/ld+json';
      this.document.head.appendChild(script);
    }

    script.textContent = JSON.stringify(value).replace(/</g, '\\u003c');
  }

  private updateTag(
    name: string,
    content: string,
    attribute: 'name' | 'property' = 'name',
  ): void {
    if (!content) {
      this.meta.removeTag(`${attribute}="${name}"`);
      return;
    }

    const definition: MetaDefinition =
      attribute === 'property'
        ? { property: name, content }
        : { name, content };
    this.meta.updateTag(definition);
  }

  clearStructuredData(): void {
    this.document.getElementById('design-lila-jsonld')?.remove();
  }

  private setCanonical(path: string): string {
    const routePath = path.split(/[?#]/, 1)[0] || '/';
    const slashPath =
      routePath === '/' ? '/' : `/${routePath.replace(/^\/+|\/+$/g, '')}/`;
    const canonicalUrl = new URL(slashPath, environment.canonicalBaseUrl).toString();
    let link = this.document.querySelector<HTMLLinkElement>('link[rel="canonical"]');

    if (!link) {
      link = this.document.createElement('link');
      link.rel = 'canonical';
      this.document.head.appendChild(link);
    }

    link.href = canonicalUrl;
    return canonicalUrl;
  }
}
