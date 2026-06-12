# Quickstart

Five minutes from install to fully rendered meta tags. This walkthrough is
verified against a fresh Laravel app — every block is copy-pasteable.

## 1. Add the trait to a model

```php
use Illuminate\Database\Eloquent\Model;
use Rankbeam\Seo\Traits\HasSEO;

class Post extends Model
{
    use HasSEO;

    public function getUrlForSEO(): string
    {
        return route('posts.show', $this);
    }
}
```

`getUrlForSEO()` tells the resolver what canonical URL the model lives at —
it powers canonicals, `og:url`, and sitemap entries.

## 2. Render the head

```blade
<!DOCTYPE html>
<html>
<head>
    @seo($post)
</head>
```

`@seo($post)` emits title, meta description, canonical, robots, Open Graph,
Twitter Card tags, and any JSON-LD attached to the resolved data. With no
explicit values saved yet, everything comes from computed fallbacks (the
post's own attributes) and your configured defaults — see
[resolver precedence](/concepts/resolver-precedence).

## 3. Set explicit values

```php
$post->saveSEO([
    'title' => 'Custom SEO Title',
    'description' => 'Custom meta description',
]);
```

Explicit values win over every fallback layer. Pass a locale for translated
meta: `$post->saveSEO(['title' => 'Titre'], 'fr')`.

::: tip Seeding models?
Laravel's default `DatabaseSeeder` uses the `WithoutModelEvents` trait, which
silently disables `HasSEO`'s auto-create hook. Remove the trait or call
`saveSEO()` explicitly in seeders.
:::

## 4. Sitemaps

```php
// e.g. in AppServiceProvider::boot()
use Rankbeam\Seo\Facades\SEO;

SEO::sitemaps()->register('posts', Post::class);
```

```bash
composer require spatie/laravel-sitemap
php artisan seo:sitemap
```

`/sitemap.xml` now serves the generated index. Full options in the
[sitemap registry guide](/guide/sitemaps).

## Where to go next

- [Resolver precedence](/concepts/resolver-precedence) — how values are chosen
- [Blade guide](/guide/blade) — all seven directives
- [Inertia & JSON](/guide/inertia-json) — headless rendering
- [Schema graph](/guide/schema) — linked JSON-LD
- [Filament fields](/guide/filament) — admin UI in two lines
