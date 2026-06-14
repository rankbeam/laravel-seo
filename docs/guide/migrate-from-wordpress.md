# Migrating from WordPress

Moving a content site off WordPress? Rankbeam can bring the SEO metadata your
team hand-wrote in Yoast or Rank Math — titles, descriptions, canonicals,
robots directives, focus keywords, social overrides — across to your Laravel
models, so you don't lose years of optimisation in the switch.

There are two paths, both driven by the same `seo:import-from` command:

| Path | Source | Best for |
|---|---|---|
| [**CSV**](#1-csv-import) `wordpress-csv` | a spreadsheet you export from WordPress | most agency migrations; you control the exact URLs |
| [**Database**](#2-database-import-yoast-rank-math) `yoast` / `rank-math` | the live WordPress database | full fidelity, incl. OpenGraph/Twitter overrides and Rank Math redirects |

Both are **idempotent** (re-running updates the same rows, never duplicates),
support **`--dry-run`**, and only ever *fill* empty fields — they never clear
SEO data you've already set in Rankbeam.

## How WordPress rows become `seo_meta` rows

WordPress is not Laravel-morph data: a WordPress row is keyed by a **URL** or a
**post ID**, while Rankbeam's `seo_meta` is polymorphic — every row attaches to
a real Eloquent model. So the importer matches each WordPress row to one of
your models and is honest in the report about which rows attached and which
were URL-only:

- **Model-attached.** You name the target model with `--model="App\Models\Post"`.
  Each row's **slug** (the last path segment of the URL, or the WordPress
  `post_name`) is matched against that model — on its route key by default, or a
  column you choose with `--match-by=`. Matched rows are written to `seo_meta`.
- **URL-only.** A row that matches no model (or a run with no `--model`) can't
  become a `seo_meta` row — there's no model to attach it to. It's reported as
  skipped with a `url-only` reason. Its canonical can still become a
  [redirect candidate](#redirects).

WordPress posts and pages usually map to *different* Laravel models, so run the
importer once per content type and scope the rows:

```bash
php artisan seo:import-from yoast --model="App\Models\Post" --post-type=post
php artisan seo:import-from yoast --model="App\Models\Page" --post-type=page
```

---

## 1. CSV import

The CSV path covers most agency migrations. Export one row per URL with this
header (columns may be in any order; unrecognised columns are ignored and
reported):

```csv
url,title,description,canonical,robots,focus_keyword
https://oldsite.com/blog/my-post/,"My SEO Title","My meta description.",https://newsite.com/blog/my-post,"index, follow","laravel seo"
```

Run it:

```bash
# Preview first — writes nothing
php artisan seo:import-from wordpress-csv \
  --file=storage/migrations/seo-export.csv \
  --model="App\Models\Post" \
  --dry-run

# Then import for real
php artisan seo:import-from wordpress-csv \
  --file=storage/migrations/seo-export.csv \
  --model="App\Models\Post"
```

| Column | Maps to `seo_meta` | Notes |
|---|---|---|
| `url` | *(matching key)* | The slug (last path segment) is matched to the model. Required. |
| `title` | `title` | Trimmed to 70 chars; over-length values reported. |
| `description` | `description` | Trimmed to 160 chars. |
| `canonical` | `canonical` | Also drives [redirect candidates](#redirects). |
| `robots` | `robots` | Stored verbatim (e.g. `noindex, nofollow`); trimmed to 50 chars. |
| `focus_keyword` | `focus_keywords` | Comma-separated; the first keyword is primary. |

Malformed rows are skipped (and counted): a row with no `url`, or one whose
column count doesn't match the header.

---

## 2. Database import (Yoast / Rank Math)

If you still have the WordPress database, the importer can read the SEO meta
directly — including the OpenGraph/Twitter overrides and (for Rank Math) the
redirects, which a CSV export usually drops.

### Point a connection at WordPress

Add the WordPress database as a connection in `config/database.php`:

```php
'connections' => [
    // ...
    'wordpress' => [
        'driver' => 'mysql',
        'host' => env('WP_DB_HOST', '127.0.0.1'),
        'database' => env('WP_DB_DATABASE', 'wordpress'),
        'username' => env('WP_DB_USERNAME'),
        'password' => env('WP_DB_PASSWORD'),
        'prefix' => '', // the table prefix is passed with --table=, see below
    ],
],
```

Then import (the table prefix defaults to `wp_`; override it with `--table=`):

```bash
# Yoast SEO
php artisan seo:import-from yoast \
  --connection=wordpress --model="App\Models\Post" --dry-run

# Rank Math
php artisan seo:import-from rank-math \
  --connection=wordpress --model="App\Models\Post" --table=wp_
```

The reader walks `{prefix}posts` (published posts/pages) and pulls each post's
plugin metadata from `{prefix}postmeta`, matching each post's `post_name` slug
to your model.

### Field mapping

Both importers map fields **explicitly** — a key with no Core 3 column is
reported as *unmapped*, never invented.

| Yoast meta key | Rank Math meta key | `seo_meta` |
|---|---|---|
| `_yoast_wpseo_title` | `rank_math_title` | `title` |
| `_yoast_wpseo_metadesc` | `rank_math_description` | `description` |
| `_yoast_wpseo_canonical` | `rank_math_canonical_url` | `canonical` |
| `_yoast_wpseo_meta-robots-*` | `rank_math_robots` | `robots` |
| `_yoast_wpseo_focuskw` | `rank_math_focus_keyword` | `focus_keywords` |
| `_yoast_wpseo_opengraph-title` | `rank_math_facebook_title` | `og_title` |
| `_yoast_wpseo_opengraph-description` | `rank_math_facebook_description` | `og_description` |
| `_yoast_wpseo_opengraph-image` | `rank_math_facebook_image` | `og_image` |
| `_yoast_wpseo_twitter-title` | `rank_math_twitter_title` | `twitter_title` |
| `_yoast_wpseo_twitter-description` | `rank_math_twitter_description` | `twitter_description` |
| `_yoast_wpseo_twitter-image` | `rank_math_twitter_image` | `twitter_image` |
| — | `rank_math_twitter_card_type` | `twitter_card` |

**Robots.** Only deviations from the WordPress defaults are stored, so an
ordinary indexable page leaves `robots` null and inherits your site default.
Yoast's separate `noindex` / `nofollow` / advanced (`noarchive`, `nosnippet`,
`noimageindex`) flags are composed into one string; Rank Math's serialized
`robots` array is read the same way, dropping the `index` / `follow` defaults.

**Unmapped keys** (reported, never copied): attachment image IDs
(`*-image-id`), keyword/SEO scores (`linkdex`, `content_score`,
`rank_math_seo_score`), primary-category choices, and Rank Math's rich-snippet
schema markers — the [schema graph](/guide/schema) is a richer, typed
replacement for those.

### Template tokens

Yoast and Rank Math store titles and descriptions as **templates** with tokens
— Yoast uses `%%title%%`, Rank Math uses `%title%`. The importer **resolves the
tokens it can derive** and **strips the rest**, so a stored value is never a raw
`%%token%%` string:

| Token | Resolved to |
|---|---|
| `%%title%%` / `%title%` | the WordPress post title |
| `%%sitename%%` / `%sitename%` | the blog name from `wp_options` (database import) |
| `%%sep%%` / `%sep%` | `-` |
| `%%page%%`, `%%primary_category%%`, … | *stripped* (left empty, surrounding separators tidied) |

A run that resolved any token says so in the report — **review the imported
titles** to confirm they read the way you want, and adjust the few that relied
on tokens we couldn't derive.

---

## Redirects

`seo_redirects` is a [Rankbeam **Pro**](/pro/installation) feature, so a core
importer never writes that table directly. Instead, pass `--redirects-csv=` and
the importer **emits a CSV** with the same columns as the Pro redirects table —
`source_path,target_url,status_code,note` — which you import into Pro.

```bash
php artisan seo:import-from rank-math \
  --connection=wordpress --model="App\Models\Post" \
  --redirects-csv=storage/migrations/redirects.csv
```

Where redirect candidates come from:

- **CSV import** — a row whose `canonical` points to a **different path** than
  its own `url` becomes a `301` from the old path to the canonical. A
  self-canonical (same path) is *not* emitted (it would be a loop).
- **Rank Math database** — active rules in the `{prefix}rank_math_redirections`
  table. Only **exact-match** rules are emitted; regex/contains/start/end rules
  are reported as skipped, since they don't map to a single path.
- **Yoast (free)** has no redirect table — only Yoast Premium does, and its
  schema isn't part of the free package. Use the CSV path for Yoast redirects.

The candidates are **advisory** — review the CSV, then import it into Pro.

---

## What the report tells you

A non-`--json` run prints an outcome table (created / updated / unchanged /
skipped / scanned) plus review sections:

- **Truncated** — values shortened to fit a `seo_meta` column.
- **Not imported** — source keys that held data but have no Core 3 home.
- **Redirect candidates** — how many were written, and to which file.
- **Skipped rows by reason** — url-only rows, posts with no SEO meta, non-exact
  redirect rules.
- **Warnings** — e.g. that template tokens were resolved.

Add `--json` for a machine-readable version of all of the above.

### Verify

```bash
php artisan seo:audit   # confirm the imported metadata looks right
```

See [Free SEO audit](/guide/audit). Once you're satisfied, decommission
WordPress.

---

Coming from a **Laravel** SEO package instead (ralphjsmit, artesaos, Spatie)?
See [Migrating from other Laravel packages](/guide/migrate-from-other-packages).
