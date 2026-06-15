# Migrating from other Laravel SEO packages

Already using another SEO package? Switching to Rankbeam is meant to be a
day's work, not a rewrite. This guide maps each common package's API and
storage onto Rankbeam's two primitives — the [`HasSEO`](/guide/quickstart)
trait and `saveSEO()` — and ships a one-command importer for the package that
stores SEO data per model.

::: tip Coming from WordPress?
If you're migrating a content site off WordPress (Yoast or Rank Math), see the
dedicated [**Migrating from WordPress**](/guide/migrate-from-wordpress) guide —
it covers the CSV importer and the live-database readers.
:::

| Coming from | Stores data in | Migration path |
|---|---|---|
| [`ralphjsmit/laravel-seo`](#from-ralphjsmit-laravel-seo) | a `seo` morph table | **`php artisan seo:import-from ralphjsmit`** + trait swap |
| [`artesaos/seotools`](#from-artesaos-seotools) | nothing (runtime + config) | code swap — set values via `saveSEO()` / computed getters |
| [`spatie/*`](#from-spatie-packages) | nothing (schema-org / sitemap builders) | keep what's complementary, move the rest into Rankbeam |

Only **ralphjsmit** persists SEO data in a database table, so it's the only one
with data to bulk-import. The others are runtime tag builders — there's no
table to read; you replace their per-request calls with stored `seo_meta`.

---

## From `ralphjsmit/laravel-seo`

`ralphjsmit/laravel-seo` (~533k installs) stores one polymorphic row per model
in a `seo` table whose shape is close to Rankbeam's `seo_meta`. That makes a
clean, idempotent bulk import possible.

### 1. Install Rankbeam alongside it

```bash
composer require rankbeam/laravel-seo
php artisan vendor:publish --tag=seo-config
php artisan vendor:publish --tag=seo-migrations
php artisan migrate
```

Both packages can coexist while you migrate — they use different tables (`seo`
vs `seo_meta`) and different trait namespaces.

::: warning One config file, not two
If a published `config/seo.php` from `ralphjsmit/laravel-seo` is still in your
app, it will shadow Rankbeam's config (they share the `seo` config key). Back
it up, delete it, and re-publish Rankbeam's: `php artisan vendor:publish
--tag=seo-config`.
:::

### 2. Run the importer

```bash
# Preview first — writes nothing
php artisan seo:import-from ralphjsmit --dry-run

# Then import for real
php artisan seo:import-from ralphjsmit
```

The importer reads ralphjsmit's `seo` table, resolves each row to its real
Eloquent model, and writes the data into `seo_meta`.

| Option | Effect |
|---|---|
| `--dry-run` | Report what would be imported; write nothing. |
| `--model="App\Models\Post"` | Restrict to one or more model classes (repeatable). |
| `--locale=fr` | Write the imported rows for this locale (default: app locale). |
| `--table=legacy_seo` | Read a renamed source table. |
| `--connection=legacy` | Read the source table from another database connection. |
| `--limit=100` | Import at most N rows (handy for a staged migration). |
| `--overwrite` | Replace existing non-empty values (default: only fill empty fields). |
| `--json` | Machine-readable report. |
| `--force` | Skip the confirmation prompt (for scripts/CI). |

It is **idempotent**: re-running updates the same rows and never creates
duplicates, and by default it only ever *fills* empty fields — it will not
overwrite SEO data you've already set in Rankbeam. Pass `--overwrite` if you
want the imported values to replace your existing ones instead.

### 3. Swap the trait on your models

Replace the ralphjsmit trait with Rankbeam's. The method names differ slightly;
the table the trait reads is now `seo_meta`.

```php
// Before
use RalphJSmit\Laravel\SEO\Support\HasSEO;

// After
use Rankbeam\Seo\Traits\HasSEO;
```

If you customised SEO data with ralphjsmit's `getDynamicSEOData()`, move that
logic to Rankbeam's per-field computed getters (`getSEOTitle()`,
`getSEODescription()`, `getSEOImage()`, `getUrlForSEO()`, `getSEOAlternates()`)
— see [Quickstart](/guide/quickstart). Stored overrides go through
`saveSEO()`:

```php
$post->saveSEO([
    'title' => 'A hand-written SEO title',
    'description' => 'A hand-written meta description.',
    'canonical' => 'https://example.com/posts/my-post',
    'robots' => 'noindex, nofollow',
    'og_image' => 'https://example.com/og/my-post.jpg',
]);
```

### Field mapping

The importer maps fields **explicitly** — it never blindly copies a column the
Core 3 schema doesn't have.

| ralphjsmit `seo` | Rankbeam `seo_meta` | Notes |
|---|---|---|
| `model_type` / `model_id` | `seoable_type` / `seoable_id` | **Re-resolved** from the live model (see below), not copied verbatim. |
| `title` | `title` | Trimmed to 70 chars (the `seo_meta` column length); over-length values are reported. |
| `description` | `description` | Trimmed to 160 chars; over-length values are reported. |
| `canonical_url` | `canonical` | |
| `robots` | `robots` | Trimmed to 50 chars. |
| `image` | `og_image` | `twitter:image` inherits it automatically via the resolver. |
| `author` | *(not imported)* | Core 3's `seo_meta` has no author column — the article author is a resolver-level concern, not stored social meta. Rows with an author are **counted and reported** so you can decide where it should live (e.g. a `getSEOData`-style computed value). |
| `id`, `created_at`, `updated_at` | *(not imported)* | Structural. |

**Why the morph type is re-resolved.** Each source row is resolved to its real
model, and the `seoable` keys are taken from that model's own
`getMorphClass()`. This keeps the relation correct under your app's *current*
[morph map](https://laravel.com/docs/eloquent-relationships#custom-polymorphic-types)
even if ralphjsmit stored a different convention, and it lets the importer
skip rows whose model has since been deleted (reported as skipped, never
written as orphans).

### What the report tells you

A non-`--json` run prints an outcome table plus three review sections:

- **Truncated** — values shortened to fit a `seo_meta` column. Review these.
- **Not imported** — source columns (e.g. `author`) that held data but have no
  home in Core 3.
- **Skipped rows by reason** — empty source rows, deleted models, unresolved
  model types.

### Verify

```bash
php artisan seo:audit            # confirm the imported metadata looks right
```

Once you're satisfied, remove `ralphjsmit/laravel-seo` and drop its `seo`
table.

---

## From `artesaos/seotools`

`artesaos/seotools` is a **runtime** tag builder: you set values per request
through the `SEOMeta`, `OpenGraph`, `TwitterCard`, and `JsonLd` facades (often
in a controller), backed by `config/seotools.php` defaults. Nothing is stored
per model, so there's no table to import — you move the per-request calls to
stored or computed values.

| artesaos/seotools call | Rankbeam equivalent |
|---|---|
| `SEOMeta::setTitle($t)` | `saveSEO(['title' => $t])` or `getSEOTitle()` |
| `SEOMeta::setDescription($d)` | `saveSEO(['description' => $d])` or `getSEODescription()` |
| `SEOMeta::setCanonical($u)` | `saveSEO(['canonical' => $u])` or `getUrlForSEO()` |
| `SEOMeta::addKeyword(...)` | `saveSEO(['focus_keywords' => [...]])` (see [audit](/guide/audit)) |
| `OpenGraph::setTitle / setDescription / addImage` | `saveSEO(['og_title' => …, 'og_description' => …, 'og_image' => …])` |
| `TwitterCard::setType / setTitle / setImage` | `saveSEO(['twitter_card' => …, 'twitter_title' => …, 'twitter_image' => …])` |
| `JsonLd::setType(...)` / `JsonLdMulti` | the [JSON-LD schema graph](/guide/schema) |
| `config/seotools.php` defaults | `config/seo.php` site defaults + [resolver precedence](/concepts/resolver-precedence) |
| `{!! SEO::generate() !!}` in the layout | `@seo($model)` (see [Blade](/guide/blade)) |

The shift is conceptual: instead of imperatively setting tags in each
controller, you store SEO data once (per model, in `seo_meta`) and Rankbeam's
resolver renders it. Site-wide fallbacks that lived in `config/seotools.php`
become Rankbeam's [config defaults](/reference/configuration); per-route static
pages use `@seoForRoute()`.

---

## From Spatie packages

There is no `spatie/laravel-seo` meta-storage package, so there's nothing to
import. The Spatie packages people pair with SEO are **complementary builders**,
and you can keep or replace them piecemeal:

- **`spatie/schema-org`** — a fluent JSON-LD builder. Rankbeam has its own
  [schema graph](/guide/schema) with typed `Article`, `FAQPage`, `Product`,
  `BreadcrumbList`, `LocalBusiness`, and `Organization` builders that store into
  `seo_meta.schema_jsonld` and render deduplicated. If you have hand-built
  `spatie/schema-org` objects, pass their `->toArray()` output to
  `saveSEO(['schema_jsonld' => $array])`, or re-express them with Rankbeam's
  builders.
- **`spatie/laravel-sitemap`** — a sitemap generator. Rankbeam's
  [sitemap registry](/guide/sitemaps) builds on it; you can register your
  models as sources and let Rankbeam emit a combined sitemap, or keep your
  existing Spatie sitemap and disable Rankbeam's route.

(If you were using [`romanzipp/laravel-seo`](https://github.com/romanzipp/Laravel-SEO),
another runtime/struct-based meta builder, follow the same pattern as
artesaos: move per-request `setTitle`/`addMeta` calls into `saveSEO()` or
computed getters.)

---

## Extending the importer

The `seo:import-from` command is backed by a small registry of
`Rankbeam\Seo\Importing\Contracts\Importer` implementations, so new sources
slot in without touching the command. Built-in sources today: `ralphjsmit`,
and the WordPress importers (`wordpress-csv`, `yoast`, `rank-math` — see
[Migrating from WordPress](/guide/migrate-from-wordpress)). Register your own in
a service provider:

```php
use Rankbeam\Seo\Importing\ImporterRegistry;

$this->app->afterResolving(ImporterRegistry::class, function (ImporterRegistry $registry) {
    $registry->register('my-source', \App\Seo\MyImporter::class);
});
```
