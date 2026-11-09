import { readdir, readFile, rm, writeFile } from 'node:fs/promises';
import { join, relative, resolve, sep } from 'node:path';
const mode = process.argv[2];
if (mode !== 'staging' && mode !== 'production') {
  throw new Error('Pass "staging" or "production" as the build mode.');
}

const browserOutput = resolve('deploy/browser');
const canonicalOrigin = 'https://www.designlila.com';

async function findHtmlFiles(directory) {
  const entries = await readdir(directory, { withFileTypes: true });
  const files = await Promise.all(
    entries.map(async (entry) => {
      const path = join(directory, entry.name);
      if (entry.isDirectory()) {
        return findHtmlFiles(path);
      }

      return entry.isFile() && entry.name.endsWith('.html') ? [path] : [];
    }),
  );
  return files.flat();
}

function getAttribute(tag, attribute) {
  const match = tag.match(new RegExp(`\\b${attribute}\\s*=\\s*["']([^"']*)["']`, 'i'));
  return match?.[1] ?? '';
}

const pages = [];
for (const file of await findHtmlFiles(browserOutput)) {
  const html = await readFile(file, 'utf8');
  const robotsTag = html.match(/<meta\b[^>]*\bname=["']robots["'][^>]*>/i)?.[0];
  if (!robotsTag || getAttribute(robotsTag, 'content') !== 'index, follow') {
    continue;
  }

  const canonicalTag = html.match(/<link\b[^>]*\brel=["']canonical["'][^>]*>/i)?.[0];
  const canonical = canonicalTag && getAttribute(canonicalTag, 'href');
  if (!canonical || new URL(canonical).origin !== canonicalOrigin) {
    throw new Error(`Indexable page has a missing or non-production canonical: ${relative(browserOutput, file)}`);
  }

  pages.push(canonical);
}

const uniquePages = [...new Set(pages)].sort();
const homePage = `${canonicalOrigin}/`;

if (mode === 'staging' && uniquePages.length) {
  throw new Error('Staging SEO build stopped: staging pages must remain noindex.');
}

if (mode === 'production' && !uniquePages.includes(homePage)) {
  throw new Error('Production SEO build stopped: the homepage has no indexable catalog content.');
}

const robots = [
  'User-agent: *',
  'Allow: /',
  ...(mode === 'production'
    ? [`Sitemap: ${canonicalOrigin}/sitemap.xml`]
    : []),
  '',
].join('\n');

await writeFile(join(browserOutput, 'robots.txt'), robots, 'utf8');
await writeFile(
  join(browserOutput, '.htaccess'),
  [
    'Options -Indexes',
    'RewriteEngine On',
    'DirectoryIndex index.html',
    ...(mode === 'staging'
      ? [
          '<IfModule mod_headers.c>',
          '  Header set X-Robots-Tag "noindex, nofollow"',
          '</IfModule>',
        ]
      : []),
    'RewriteRule ^api(?:/|$) index.php [QSA,L]',
    'RewriteCond %{REQUEST_FILENAME} !-f',
    'RewriteCond %{REQUEST_FILENAME} !-d',
    'RewriteRule ^ index.html [L]',
    '',
  ].join('\n'),
  'utf8',
);

const sitemapPath = join(browserOutput, 'sitemap.xml');
if (mode === 'staging') {
  await rm(sitemapPath, { force: true });
} else {
  const xml = [
    '<?xml version="1.0" encoding="UTF-8"?>',
    '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">',
    ...uniquePages.map((url) =>
      `  <url><loc>${url.replaceAll('&', '&amp;')}</loc></url>`,
    ),
    '</urlset>',
    '',
  ].join('\n');
  await writeFile(sitemapPath, xml, 'utf8');
}

console.log(
  `${mode} SEO artifacts generated: ${uniquePages.length} indexable URL(s)${mode === 'staging' ? '; no sitemap emitted' : '.'}`,
);
