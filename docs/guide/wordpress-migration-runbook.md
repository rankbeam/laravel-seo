# WordPress → Rankbeam migration runbook

A step-by-step, **low-risk** procedure for replacing a legacy WordPress SEO
stack (Yoast or Rank Math) with Rankbeam on a real, live site. It is built so
that **nothing is destructive until you have explicitly verified the import** —
the importers only ever *fill empty* fields, dry-runs write nothing, and the old
WordPress database/table stays untouched until you choose to drop it.

This is the operational companion to [Migrating from WordPress](/guide/migrate-from-wordpress),
which documents the field mapping, template-token handling, and source keys in
detail. Read that for the *what*; read this for the *how, in order*.

::: tip What you'll need
- **Core** (`rankbeam/laravel-seo`) for the metadata import + `seo:audit`.
- **Pro** (`rankbeam/laravel-seo-pro`) only if you are also migrating
  **redirects** — the `seo_redirects` table is a Pro feature.
- Your content already modelled in Laravel (e.g. `App\Models\Post`), with the
  [`HasSEO`](/guide/quickstart) trait, and a way to match a WordPress slug to a
  model (the model route key, or a column you name with `--match-by`).
:::

## The shape of the migration

WordPress rows are keyed by **URL / post**; Rankbeam `seo_meta` rows are
**polymorphic** (attached to an Eloquent model). The import matches each
WordPress row to one of your models. Three outcomes are possible, and every run
reports the split:

| Outcome | Meaning | Action |
|---|---|---|
| **matched** | the row attached to a model — `seo_meta` was written | none |
| **url-only** | the row matched no model (or no `--model` was given) | decide whether that page needs a model, or a redirect |
| **unmapped** | the row held data with no Core 3 home (above all **author**) | re-home it (e.g. a `getSEOAuthor()` hook) |

---

## Step 0 — Coexist (no cutover yet)

Stand Rankbeam up **next to** the running site. Add the `HasSEO` trait to your
models and render tags through the facade/directive, but **do not** remove the
WordPress install or its SEO plugin yet. At this point nothing has been imported
and nothing is destructive — you are only proving the new stack boots.

If you serve the new Laravel app and the legacy WordPress site from the same
host during cutover, keep them on separate paths until Step 5.

## Step 1 — Import the metadata (dry run first)

Always start with `--dry-run`, which **writes nothing** and prints the full
verification report of what *would* happen.

```bash
# Yoast, from the live WordPress database (configure the connection first)
php artisan seo:import-from yoast \
  --connection=wordpress \
  --model="App\Models\Post" \
  --redirects-csv=storage/app/redirects.csv \
  --dry-run

# Rank Math is identical — just swap the source key
php artisan seo:import-from rank-math --connection=wordpress --model="App\Models\Post" --dry-run

# From a CSV export instead of the live DB
php artisan seo:import-from wordpress-csv --file=storage/app/wp-export.csv --model="App\Models\Post" --dry-run
```

Useful options (run `php artisan seo:import-from --help` for the full list):

| Option | Purpose |
|---|---|
| `--model=` | the target model FQCN (repeatable; WordPress importers attach **one** model per run — run once per content type) |
| `--match-by=` | the model column to match a slug against (default: the route key) |
| `--post-type=` | restrict the DB readers to these post types (default: `post` + `page`) |
| `--connection=` | the database connection the WordPress tables live on |
| `--table=` | the WordPress table **prefix** (default: `wp_`) |
| `--locale=` | the locale the `seo_meta` rows are written for |
| `--redirects-csv=` | also emit redirect candidates to this file for Step 3 |
| `--site-url=` | the old site URL, to derive paths from absolute URLs |
| `--overwrite` | replace existing non-empty `seo_meta` (default: **fill empty only**) |
| `--limit=` | cap the number of source rows (handy for a first pass) |
| `--json` | machine-readable report |

When the dry run looks right, drop `--dry-run` to apply it:

```bash
php artisan seo:import-from yoast --connection=wordpress --model="App\Models\Post" --redirects-csv=storage/app/redirects.csv
```

The import is **idempotent** and **fill-empty-only** by default, so it is safe
to re-run and can never clobber metadata you have already edited in Rankbeam.

## Step 2 — Read (and archive) the verification report

Every run prints a **Verification report** — the numbers you sign off on before
removing anything. Capture it as a durable artifact:

```bash
php artisan seo:import-from yoast --connection=wordpress --model="App\Models\Post" --json > storage/app/import-report.json
```

What to check:

- **matched** should equal the number of pages you expect to carry SEO metadata.
- **url-only** is your worklist of pages that matched no model — decide whether
  each needs a model, a redirect (Step 3), or nothing.
- **truncated** lists fields shortened to fit a `seo_meta` column — review those
  titles/descriptions.
- **unmapped** lists source data with no Core 3 column, **with every distinct
  `author` value** spelled out. Authors are not stored as a column (they are a
  `getSEOAuthor()` concern); the report exists so you re-home them deliberately
  rather than discover the loss months later.

## Step 3 — Import the redirects into Pro

The core importer **never writes `seo_redirects`** (that is a Pro table); it
hands you a CSV in a fixed, versioned shape (**redirect CSV format v1**:
`source_path,target_url,status_code,note`). Import it into Pro with a dry run
first:

```bash
php artisan seo-pro:redirects-import storage/app/redirects.csv --dry-run
php artisan seo-pro:redirects-import storage/app/redirects.csv
```

Every row is validated exactly as the Filament redirect form is: malformed
rows, invalid status codes, **unsafe external targets**, **duplicate sources**,
and rules that would form a **redirect loop** are skipped with a reason — never
silently written. The dry run validates the whole file (loops and duplicates
included) and writes nothing; pass `--overwrite` to replace an existing rule's
target.

## Step 4 — Verify with `seo:audit --strict`

Gate the migration on the free, in-process audit. `--strict` exits non-zero if
**any** page has an issue, so it works as a CI/cutover gate:

```bash
php artisan seo:audit --model="App\Models\Post" --strict
```

The audit covers model + resolver checks (title/description presence and length,
OG image, robots conflicts, canonical format). The rendered-HTML and
live-canonical checks — and the 0–100 score — are part of the
[Pro scan](/pro/scan-issues); run that too if you have Pro. See
[Free SEO audit](/guide/audit).

Then spot-check a handful of real pages in the browser: view source and confirm
the `<title>`, `<meta name="description">`, canonical, robots, and OpenGraph
tags render the imported values.

## Step 5 — Verify BEFORE removing the legacy package/table

Do **not** drop the WordPress database, delete the SEO plugin, or remove the
legacy package until **all** of the following hold:

- [ ] The import was run for **every** content type (one `--model` per run).
- [ ] The archived verification report shows the expected **matched** count and
      no surprising **url-only** rows.
- [ ] Every **unmapped author** value you care about has been re-homed.
- [ ] Redirects imported into Pro (`seo-pro:redirects-import`) and a few old
      URLs actually 301 to the new ones.
- [ ] `php artisan seo:audit --strict` exits `0`.
- [ ] (Pro) `php artisan seo:doctor` reports no leftover legacy `seo` table and
      no `config/seo.php` collision.
- [ ] Rendered pages spot-checked in the browser.

Because the importers are fill-empty-only and idempotent, you can re-run Step 1
at any time before this gate without harm — the old data is still in WordPress.

## Step 6 — Decommission

Only once the Step 5 checklist passes: take the WordPress site offline, then
remove its database/tables and the legacy SEO package. Keep a database backup
until you are confident the new stack is serving correctly in production.

::: tip Rollback
Nothing in Steps 1–4 is destructive: `seo_meta` is additive, redirects are
validated and reversible (delete the rules), and the WordPress data is
untouched. Your rollback before Step 6 is simply *"keep serving WordPress"*;
after Step 6 it is *"restore the WordPress backup."*
:::

---

Coming from a **Laravel** SEO package instead (ralphjsmit, artesaos, Spatie)?
See [Migrating from other Laravel packages](/guide/migrate-from-other-packages).
