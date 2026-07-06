# Indexing guard (non-production safety net)

A staging or local copy of your site leaking into Google is one of the most
common — and most damaging — SEO mistakes: duplicate content competing with
your real pages, a private environment sitting in the index, and weeks of
cleanup with the URL-removal tool. The classic cause is a `noindex` that lives
only in a `.env` you forgot to set, or a robots rule the deploy overwrote.

The **indexing guard** makes it structurally hard. Tie indexability to the
Laravel *environment* instead of to a flag someone has to remember: when the app
runs anywhere that isn't on your allow-list, every page is forced to
`noindex,nofollow`, the managed `robots.txt` blocks all crawlers, and
`seo:audit` says so out loud.

It is a free, core feature.

## What it does when active

When `app()->environment()` is **not** in `seo.indexing_guard.allowed_environments`
(and the guard is enabled), three things happen automatically:

1. **The resolver forces `noindex,nofollow` on every page.** This is applied
   *above* the whole [precedence chain](/concepts/resolver-precedence) — it
   overrides even an explicit per-page `robots` value stored in `seo_meta`.
2. **`SEO::robotsTxt()->build()` emits a disallow-all `robots.txt`** (and
   `ai.txt`) — a plain `User-agent: *` / `Disallow: /`. This covers both the
   `seo:robots-txt` command and the optional [dynamic route](/guide/ai-crawlers).
3. **`seo:audit` prints a prominent banner**, so the "everything is noindex"
   state is never a surprise when you read a report.

On production the guard is completely **inert** — zero changed output,
byte-identical rendering.

## Why it overrides an explicit robots value

Everywhere else in Rankbeam, an explicit stored value wins — that is the whole
point of the precedence chain. The guard is the one deliberate exception, and
it sits *above* the explicit layer, because the risk here is one-directional:

- A staging database is usually a clone of production, so a page that stored
  `index,follow` would carry that directive into staging and ask to be indexed.
- **Wrongly indexing staging is a disaster; wrongly `noindex`-ing it is a
  no-op.** So the guard is a floor the stored value cannot punch through, in
  exactly the environments where you never want indexing anyway.

## Enabling it

The guard ships **off**, so installing or upgrading the package never changes
what a non-production environment renders without your say-so (the same
byte-identical-until-opt-in policy as the resolver's
[`blank_is_unset`](/concepts/resolver-precedence) and generated OG images). Arm
it in one line:

```dotenv
SEO_INDEXING_GUARD=true
```

Because the guard is inert on production, it is safe to commit this enabled — it
only ever acts on the environments you didn't mean to index. It is **strongly
recommended**, and a candidate to default on in Core 4.

Disable it with the same one line:

```dotenv
SEO_INDEXING_GUARD=false
```

## Choosing which environments may index

By default only `production` is allowed. Override the list with a
comma-separated env var:

```dotenv
# Let a public preview environment index too
SEO_INDEXING_GUARD_ALLOWED="production,prod-eu"
```

Or in `config/seo.php`:

```php
'indexing_guard' => [
    'enabled' => env('SEO_INDEXING_GUARD', false),
    'allowed_environments' => ['production', 'prod-eu'],
],
```

Entries are matched with `Str::is()`, so **wildcards** work — `'prod*'` matches
`production` and `prod-eu`:

```php
'allowed_environments' => ['prod*'],
```

An **empty** list means *no* environment may index — the guard is active
everywhere (the fail-safe direction). Note that an empty or blank
`SEO_INDEXING_GUARD_ALLOWED` env value falls back to `['production']` so a typo
can never silently un-index production; write an explicit `[]` in config if you
truly want "everywhere".

## Verifying it

`seo:audit` shows the banner and, in `--json`, carries the machine-readable
state:

```bash
php artisan seo:audit --json
```

```json
{
    "indexing_guard": {
        "active": true,
        "environment": "staging",
        "allowed_environments": ["production"],
        "directive": "noindex,nofollow"
    },
    "pages": [ /* ... */ ]
}
```

And the served/generated `robots.txt` on a guarded environment:

```
# robots.txt — managed by Rankbeam
# Indexing guard ACTIVE: this app is running in the "staging" environment,
# which is not in seo.indexing_guard.allowed_environments. Every crawler is
# disallowed so this non-production site stays out of search results.
# https://rankbeam.dev/guide/indexing-guard

User-agent: *
Disallow: /
```

## Scope

The guard controls **indexing directives** — the `robots` meta tag and
`robots.txt`. It does not touch your titles, descriptions, canonicals, or
schema, and it is independent of the [robots rendering policy](/concepts/resolver-precedence)
(`seo.robots.emit_default`): because `noindex,nofollow` deviates from the site
default, it is always emitted as a tag.
