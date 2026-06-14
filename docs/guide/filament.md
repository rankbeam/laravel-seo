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
- **Focus keywords** — a tags input. You type plain keywords; they persist in
  the core's structured `[{keyword, is_primary}]` shape (the first is primary),
  so `getPrimaryKeyword()` and `SEOData` read them unchanged. Enable
  `seo.keywords.enabled` to have the [`seo:audit`](/guide/audit) command and the
  Pro scan flag pages that still lack a keyword (off by default — one gate, see
  [Configuration](/reference/configuration#focus-keywords)).
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

Accepts any subset of `title`, `description`, `focus_keywords`, `canonical`,
`robots`, `og_image`.

Without the trait, `SEOFields::make(?array $only)` returns the same section
directly.

## How values persist

The section binds to a `seo_meta` state group and saves through the core
`seoMeta()` relationship (update-or-create) — no columns on your own tables,
and the values immediately become layer 6 (explicit) in the
[resolver](/concepts/resolver-precedence).

## Structured data (schema.org)

An optional **Structured data** section lets editors attach JSON-LD rich-result
schema without touching code. Add it alongside the SEO section:

```php
public static function form(Schema $schema): Schema
{
    return $schema->components([
        // ... your fields ...
        static::seoSection(),
        static::seoSchemaSection(),     // optional
    ]);
}
```

(or `SEOSchemaFields::make()` directly, without the trait).

It writes into the core `seo_meta.schema_jsonld` column — the same value the
[schema renderer](/guide/schema) emits — and is **pure UI binding**: every
document is produced by a core schema builder and validated by the core
`SchemaValidator` before it can be saved. It adds no schema logic of its own.

The section offers:

- **Automatic breadcrumb** — a single toggle, led with as the zero-config win.
  Derives a `BreadcrumbList` from the record's parent chain via
  `BreadcrumbSchema::fromModelAncestors()`. Nothing to fill in — it follows the
  model's ancestors.
- **Schema blocks** — a repeater. Each block is either an **FAQ** (question /
  answer pairs → `FAQPage`) or a **Product** (name, description, image, brand,
  SKU, price + currency, availability → `Product`), built by the core
  `FAQSchema` / `ProductSchema` builders.

### Validation

A block that would build malformed JSON-LD is **rejected on save** with the core
validator's message — e.g. an FAQ entry with no answer, or a Product with no
image or offer (Google requires an offer for Product rich results). Blocks left
empty are simply ignored.

### What it stores

`schema_jsonld` holds the built document(s): a single object when there is one,
a JSON array when there are several (breadcrumb first, then your blocks). Both
are valid JSON-LD and render unchanged through `@seo` / `renderSchema()`.

### Schema it doesn't manage

Schema you authored in code that this editor cannot represent — a hand-authored
`@graph`, an exotic `@type`, or a Product carrying fields the form doesn't expose
(reviews, ratings, GTIN/MPN) — is **preserved verbatim**. Opening and saving the
form never clobbers it.

## Testing note (testbench only)

If you boot Filament inside orchestra/testbench, register Filament's
`SupportServiceProvider` **before** `LivewireServiceProvider` — Filament
rebinds Livewire's `DataStore`, and the wrong order fails every Livewire test
with `ViewErrorBag::put(): ... null given`. Real apps are unaffected
(package discovery orders providers correctly).
