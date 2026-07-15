---
description: "seo:explain shows which resolver layer set each SEO field and what it overrode — read-only, no network or license — so you can debug an unexpected title or robots tag."
---

# Explain the resolution (`seo:explain`)

Rankbeam resolves a page's SEO through a [layered precedence chain](/concepts/resolver-precedence) — config, database defaults (global / model-type / route), computed model values, then explicit `seo_meta` — followed by post-processing (title suffix, canonical, image absolutization) and the [indexing guard](/guide/indexing-guard). When the rendered `<title>` or `robots` tag isn't what you expected, **`seo:explain` shows you exactly which layer set each field, and what it overrode.**

It's read-only, needs no network or license, and never re-implements the merge: the attribution comes from the resolver's own layer contributions and the final values from the real resolver — so the explanation can't drift from what actually renders.

## Usage

```bash
# Explain a specific record
php artisan seo:explain "App\Models\Post" 42

# Explain the first record of a model
php artisan seo:explain "App\Models\Post"

# With a route-defaults layer and a locale
php artisan seo:explain "App\Models\Post" 42 --route=posts.show --locale=de

# Machine-readable
php artisan seo:explain "App\Models\Post" 42 --json
```

The model must use the [`HasSEO`](/guide/quickstart) trait.

## Reading the output

```
SEO resolution — Post #42  (locale: en, route: posts.show)
Layers, low → high: config · global · model-type · route · computed · explicit

 Field         Final value                    Set by            Overrode
 title         My Post | Acme                 computed          —
                 ↳ title suffix ' | Acme' appended
 description   A hand-written summary…        explicit          computed: "An auto excerpt…"
 canonical     https://acme.com/blog/my-post  post-processing   —
                 ↳ derived from model getUrlForSEO() (query string stripped)
 robots        noindex,nofollow               explicit          config: index,follow
                 ↳ indexing guard forced 'noindex,nofollow' (environment 'staging' …)
 og_image      https://acme.com/share.jpg     explicit          config: /default-og.jpg
                 ↳ absolutized from '/share.jpg'
```

- **Set by** — the winning layer (the highest-precedence layer that set a non-null value), or `post-processing` when no layer set the field but a value was *derived* (a canonical from the request/model URL, an og:url from the canonical, an absolutized image).
- **Overrode** — every lower-precedence layer that offered a value and lost, in order, so you can see what was shadowed.
- **↳ notes** — post-processing that changed the value after the layer merge: the title suffix, the canonical query-string strip, og:url derivation, image absolutization, and the indexing guard forcing `noindex` above every layer.

::: tip og:type and twitter:card
These two carry non-null framework defaults (`website` / `summary_large_image`), so the highest layer that sets them — normally `computed` — wins over `config`. A page with no stored `seo_meta` row contributes nothing for them, so a computed `og:type` like `article` is never shadowed by a bare `website`. That's faithful to how the merge actually resolves them.
:::

## Site-level resolution

Per the [amendment for the site-config ledger](/concepts/resolver-precedence), `seo:explain` also reports the site-wide values whose source is a frequent source of confusion — **which source set the canonical host, the site name, and the default locale**:

```
Site-level resolution
 Value           Resolved     Source
 Site name       Acme         env (APP_NAME)
 Default locale  en           config (app.locale)
 Canonical host  acme.com     programmatic (model getUrlForSEO())
```

The canonical host is the value most worth checking — a wrong host (a leaked `localhost`, an `http://` on an `https` site, the app URL not matching the model URL) is a classic cause of self-canonical bugs.

## JSON output

`--json` emits the full trace — `target`, per-field `winner` / `losers` / `final` / `notes`, and the `site_level` ledger — for tooling or CI:

```json
{
  "target": { "model": "App\\Models\\Post", "id": 42, "route": "posts.show", "locale": "en" },
  "fields": {
    "title": {
      "final": "My Post | Acme",
      "winner": { "layer": "computed", "value": "My Post" },
      "losers": [],
      "notes": ["title suffix ' | Acme' appended"]
    }
  },
  "site_level": {
    "canonical_host": { "value": "acme.com", "source": "programmatic (model getUrlForSEO())" }
  }
}
```

## See also

- [Resolver precedence](/concepts/resolver-precedence) — the full chain `seo:explain` traces.
- [Free SEO audit](/guide/audit) — `seo:audit` finds *what's wrong*; `seo:explain` shows *why a value is what it is*.
