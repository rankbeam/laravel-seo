---
description: "Install Rankbeam, add the HasSEO trait to an existing model, save SEO fields, and verify the rendered tags in Blade."
---

# Quickstart

Start with an existing Laravel 11, 12 or 13 application and a working database.
Laravel 11: PHP 8.2–8.4; Laravel 12: PHP 8.2–8.5; Laravel 13: PHP 8.3–8.5. The core is free under the MIT license;
no account or Pro license is required.

## Install

Run these commands from your application's directory:

```bash
composer require rankbeam/laravel-seo
php artisan vendor:publish --tag=seo-config
php artisan migrate
```

The service provider is auto-discovered. The migration creates the SEO tables;
it does not create your application's content models.

## Before the example

The steps below assume you already have a `Post` model, a saved post, and a
`posts.show` route whose Blade view receives that post as `$post`. Adapt these
names to your app. This guide adds SEO to that page; it does not build a blog.

Set `APP_URL` to your site's public origin in `.env`. For other rendering
stacks, use the [Inertia & JSON guide](/guide/inertia-json) or
[Livewire guide](/guide/livewire).

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

## 4. Verify the result

Open the post's public page and use **View page source**. In the `<head>`, check
that the title contains `Custom SEO Title`, the description is
`Custom meta description`, and the canonical points to the public post URL.
Your configured title suffix may follow the title.

Render `@seo($post)` once per page. If the layout already emits title or meta
tags, replace those tags to avoid duplicates. If a value is unexpected, use the
[resolution guide](/guide/explain) to inspect where it came from.

## 5. Add a sitemap (optional)

```php
// e.g. in AppServiceProvider::boot()
use App\Models\Post;
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
