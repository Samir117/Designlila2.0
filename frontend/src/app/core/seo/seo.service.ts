import { DOCUMENT } from '@angular/common';
import { inject, Injectable } from '@angular/core';
import { Meta, Title } from '@angular/platform-browser';
import { environment } from '../../../environments/environment';
import { SeoMetadata } from './seo-metadata';

@Injectable({ providedIn: 'root' })
export class SeoService {
  private readonly title = inject(Title);
  private readonly meta = inject(Meta);
  private readonly document = inject(DOCUMENT);

  apply(metadata: SeoMetadata, currentUrl: string): void {
    this.title.setTitle(metadata.title ?? 'Design Lila | Detalles para celebrar');

    if (metadata.description) {
      this.meta.updateTag({
        name: 'description',
        content: metadata.description,
      });
    } else {
      this.meta.removeTag('name="description"');
    }

    const indexable = environment.indexingEnabled && metadata.indexable === true;
    this.meta.updateTag({
      name: 'robots',
      content: indexable ? 'index, follow' : 'noindex, nofollow',
    });

    this.setCanonical(metadata.canonicalPath ?? currentUrl);
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

  private setCanonical(path: string): void {
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
  }
}
