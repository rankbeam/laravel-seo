# Resolver precedence

Every effective SEO value — title, description, canonical, robots, images —
is produced by `SEOResolver` merging **six layers**. Higher layers win, and
`null` never overwrites a value from a lower layer, so every page always
renders something sensible.

## The six layers

From lowest (always present) to highest (always wins):

| # | Layer | Source | Typical use |
|---|---|---|---|
| 1 | **Site config** | `config/seo.php` (`site_name`, `title_suffix`, `default_og_image`, `default_robots`, …) | Brand-wide defaults |
| 2 | **Global DB defaults** | `seo_defaults` rows with no model type | Editable site-wide defaults without a deploy |
| 3 | **Model-type defaults** | `seo_defaults` rows scoped to a model class | "All products get this OG image" |
| 4 | **Route defaults** | `seo_defaults` rows scoped to a route name | Static pages (`home`, `contact`) with no model |
| 5 | **Computed values** | Derived from the model's own attributes | Fallback title from `title`, description from `excerpt`/`body`, … |
| 6 | **Explicit values** | The model's `seo_meta` row (`saveSEO()`) | What editors set by hand |

```php
use Rankbeam\Seo\Facades\SEO;

$seo = SEO::resolve($post);          // model page: layers 1-3 + 5-6
$seo = SEO::forRoute('contact');     // route page: layers 1-2 + 4
```

The result is an immutable `SEOData` value object consumed by every renderer
(Blade, array, Inertia).

## Computed fallbacks (layer 5)

When no explicit value exists, the resolver derives one from the model:

- **Title** — the model's `title`/`name` attribute.
- **Description** — the first attribute in
  `seo.computed.description_fields` (default chain: `excerpt`, `summary`,
  `description`, `intro`, `lead`, `teaser`, `content`, `body`, `text`,
  `article`) that contains meaningful text. HTML is stripped, entities are
  decoded, and the text is truncated at a word boundary
  (`seo.computed.description_max_length`, default 160 — no ellipsis).
- **Robots** — derives `noindex,nofollow` from a falsy `is_indexable`
  attribute when the model has one.
- **URL-derived values** — canonical and `og:url` from `getUrlForSEO()`.

## Policies applied after resolution

These run regardless of which layer produced the value:

- **Title suffix** — `title_suffix` is appended unless the resolved title
  already ends with it. If a route-default template already contains your
  brand, end the template with the suffix to avoid "Brand — X | Brand".
- **Canonical query-strip** — *derived* canonicals (model URL / current URL)
  get the query string stripped; *explicitly set* canonicals are preserved
  verbatim.
- **Absolute social images** — `og:image` and `twitter:image` are always
  emitted as absolute URLs (the Open Graph spec requires it), even when the
  stored value is a relative path.

## Inspecting which layer won

The [Filament package](/guide/filament) surfaces this per field (Manual /
Content fallback / Model-type default / Global default / Site config /
Derived from URL). In code, `SEOWarningEvaluator` exposes the same
manual-vs-fallback distinction for building your own admin indicators.
