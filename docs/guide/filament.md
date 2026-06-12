# Filament admin fields

The free [`rankbeam/laravel-seo-filament`](https://github.com/rankbeam/laravel-seo-filament)
package adds a complete SEO section to any Filament resource form —
**two lines per resource**. It supports Filament **4.x and 5.x** (Livewire 3
and 4); the test suite passes unchanged on both majors.

## Install

```bash
composer require rankbeam/laravel-seo-filament
```

The model behind the resource must use the core `HasSEO` trait.

## Add the section to a resource

```php
use Rankbeam\Seo\Filament\Concerns\HasSEOFields;

class PostResource extends Resource
{
    use HasSEOFields;                       // 1

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('title'),
            // ...
            static::seoSection(),           // 2
        ]);
    }
}
```

That renders a collapsible "SEO" section with:

- **Title & description** with live character counters — the 60/160
  thresholds come from the core `SEOWarningEvaluator`, so the admin UI and
  the audit layer can never disagree.
- **Canonical URL** (empty = automatic, query string stripped).
- **Robots** select (empty = site default).
- **Social sharing image** upload (og:image / twitter:image), stored on
  Filament's default disk under `seo/`.
- **Search-snippet preview** that mirrors the resolver's fallback chain live
  while you type.
- **Source indicators** — for each field, which resolver layer produced the
  effective value: *Manual*, *Content fallback*, *Model-type default*,
  *Global default*, *Site config*, or *Derived from URL*.

## Limiting fields

```php
static::seoSection(['title', 'description'])
```

Accepts any subset of `title`, `description`, `canonical`, `robots`,
`og_image`.

Without the trait, `SEOFields::make(?array $only)` returns the same section
directly.

## How values persist

The section binds to a `seo_meta` state group and saves through the core
`seoMeta()` relationship (update-or-create) — no columns on your own tables,
and the values immediately become layer 6 (explicit) in the
[resolver](/concepts/resolver-precedence).

## Testing note (testbench only)

If you boot Filament inside orchestra/testbench, register Filament's
`SupportServiceProvider` **before** `LivewireServiceProvider` — Filament
rebinds Livewire's `DataStore`, and the wrong order fails every Livewire test
with `ViewErrorBag::put(): ... null given`. Real apps are unaffected
(package discovery orders providers correctly).
