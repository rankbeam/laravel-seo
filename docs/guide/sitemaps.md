# Sitemap registry

The package generates XML sitemaps (one file per source plus an index) and
serves them at `/sitemap.xml` and `/sitemap-{name}.xml`. Generation wraps
[spatie/laravel-sitemap](https://github.com/spatie/laravel-sitemap):

```bash
composer require spatie/laravel-sitemap
```

## Registering sources

Register named sources in a service provider's `boot()`:

```php
use App\Models\Post;
use Rankbeam\Seo\Facades\SEO;

// A model class — every (indexable) record's getUrlForSEO()
SEO::sitemaps()->register('posts', Post::class);

// A closure returning URLs
SEO::sitemaps()->register('pages', fn () => ['/about', '/contact']);

// Any iterable of URLs
SEO::sitemaps()->register('legal', ['/imprint', '/privacy']);
```

Each source renders to `sitemap-{name}.xml`; `sitemap.xml` becomes the index
listing them all.

The registry API also offers `has($name)`, `names()`, `forget($name)`, and
`flush()`.

## Config-driven sources

Prefer configuration? `config/seo.php` accepts model sources and static URLs:

```php
'sitemap' => [
    'models' => [
        \App\Models\Post::class => ['priority' => 0.8, 'changefreq' => 'weekly'],
    ],
    'static_urls' => [
        ['url' => '/', 'priority' => 1.0, 'changefreq' => 'daily'],
    ],
],
```

::: info Auto-discovery defers to the registry
If a model is covered by a named registered source, auto-discovery skips it —
registering `'posts'` will not also produce a `sitemap-post.xml`.
:::

## Generating

```bash
php artisan seo:sitemap
```

Files are written to the disk configured at `seo.sitemap.disk` (default
`public`). Schedule the command to keep sitemaps fresh:

```php
// routes/console.php or bootstrap/app.php scheduling
Schedule::command('seo:sitemap')->daily();
```

Sitemaps exceeding `seo.sitemap.max_urls_per_sitemap` (default 50,000 — the
XML spec limit) are split automatically.

## Serving

The package routes serve whatever the command generated, with XML headers,
cache headers, and `X-Robots-Tag: noindex`:

- `/sitemap.xml` — the index (or single sitemap)
- `/sitemap-posts.xml` — a named source

Serving your own statically generated sitemap instead? Disable the routes:

```php
// config/seo.php
'routes' => ['enabled' => false],
```

## What gets included

Model sources include records that resolve as indexable; a model whose
robots resolve to `noindex` stays out of the sitemap. URLs come from
`getUrlForSEO()` — the same method that powers canonicals, so the sitemap
and the canonical never disagree.

## Image & hreflang extensions

Two optional extensions enrich each model URL with the data the package
already resolves for that record. Both are **off by default** — enable the
ones you want in `config/seo.php`:

```php
'sitemap' => [
    'images' => true,      // <image:image> per URL
    'alternates' => true,  // <xhtml:link rel="alternate"> per URL
],
```

They apply to models using the `HasSEO` trait (the values come from the
model's fully resolved `seoData()`):

- **`images`** adds a [Google image-sitemap](https://developers.google.com/search/docs/crawling-indexing/sitemaps/image-sitemaps)
  entry built from the resolved og/content image — the *same* value rendered
  as `og:image`, so the sitemap never disagrees with the page. When a record
  has no image of its own this is the site-wide `default_og_image`, so enable
  it only if a per-URL image is meaningful for your content.
- **`alternates`** adds `<xhtml:link rel="alternate" hreflang="…">` entries
  from the model's `getSEOAlternates()` — the same hreflang links rendered in
  the page `<head>`. Return absolute URLs:

```php
public function getSEOAlternates(): ?array
{
    return [
        ['hreflang' => 'en', 'href' => route('posts.show', [$this, 'locale' => 'en'])],
        ['hreflang' => 'fr', 'href' => route('posts.show', [$this, 'locale' => 'fr'])],
        ['hreflang' => 'x-default', 'href' => route('posts.show', $this)],
    ];
}
```

::: warning hreflang must be reciprocal and self-referencing
The values you return are emitted **verbatim** — the package does not validate
language codes or invent links. Google only honours an annotation when every
language version lists **itself and all the others**, and the references are
**reciprocal** (each page points back). So `getSEOAlternates()` must return the
**complete** set (including a self-reference) and every localised variant must
return that same complete set. Use valid `language` / `language-REGION` codes
or `x-default`, and absolute `http(s)` URLs. Entries missing a non-empty
`hreflang` or `href` are skipped.
:::

::: info Cost at scale
With either extension on, the builder resolves each record's full `seoData()`
(the entire precedence chain — defaults lookups, computed values, the model's
`getSEO*()` getters) once per URL. On a large catalogue that is a real cost:
each record can issue several cache/database operations, and custom getters may
add their own queries. It is built for the **scheduled** `seo:sitemap` command,
not a request cycle — benchmark before enabling it on a sitemap approaching the
50,000-URL limit, and keep both flags off if you don't need the entries.
:::

::: tip Already publishing a config?
`config/seo.php` is merged **shallowly**, so an app that published the config
file before this release won't pick up the `sitemap.images` / `sitemap.alternates`
keys automatically — the `SEO_SITEMAP_IMAGES` / `SEO_SITEMAP_ALTERNATES` env
vars alone won't switch them on. Add the two keys to your published `sitemap`
array (see the block above) or re-publish the config.
:::

## Full control: hand-built Spatie tags

For anything the resolved data doesn't cover — image captions, **video**, or
**news** entries, or bespoke `hreflang` sets — return a fully hand-built
[`Spatie\Sitemap\Tags\Url`](https://github.com/spatie/laravel-sitemap#adding-images)
from a registered source. The builder passes `Url` tags through verbatim and
never appends its own extensions to them, so you stay in complete control:

```php
use Spatie\Sitemap\Tags\Url;

SEO::sitemaps()->register('videos', fn () => Video::query()
    ->get()
    ->map(fn (Video $video) => Url::create($video->url)
        ->addImage($video->thumbnail_url, caption: $video->title)
        ->addVideo(
            thumbnailLoc: $video->thumbnail_url,
            title: $video->title,
            description: $video->description,
            contentLoc: $video->file_url,
        )
        ->addAlternate($video->frenchUrl, 'fr')
    ));
```

The same escape hatch is available per record: a model implementing
`Sitemapable` whose `toSitemapTag()` returns a `Url` is emitted exactly as
returned.
