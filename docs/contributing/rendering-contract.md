# The Rendering Contract

This is the **single canonical checklist** every front-end stack's `<head>`
must satisfy when it renders Rankbeam SEO data. It is the source of truth for:

- the renderer-shape unit tests in core
  (`tests/Unit/Services/RenderingContractTest.php`) — the fast, framework-free
  leg that runs on every push;
- the per-stack reference apps in `rankbeam-examples` (Blade, Inertia + Vue /
  React / Svelte, Livewire), whose browser + SSR tests verify the same
  assertions in a real DOM;
- the framework guides (Blade, Inertia & JSON, Livewire), which must never
  document a recipe that violates it.

If a stack cannot meet a clause, that is a **defect or a documented
limitation** — not a reason to weaken the contract. The data layer
(`SEOResolver` → immutable `SEOData` → `TagRenderer`) is framework-agnostic;
only *how the resolved data reaches the DOM, survives client navigation, and
stays crawler-visible* varies per stack, and that is exactly what this contract
pins.

> This spec was hardened by an independent design review (Codex, 2026-06-14).
> Re-review only if it changes materially.

---

## 1. Values — what a compliant `<head>` contains

### Title, description, canonical

- **Exactly one `<title>`**, carrying the *resolved* title — never
  double-suffixed (the resolver appends `seo.title_suffix` once, guarding
  against a title that already ends with it).
- **One meta description**, only when a description resolved (no empty tag).
- **One `<link rel="canonical">`**.

### Robots

- Emit `<meta name="robots">` **only when the directive deviates from the site
  default**. A redundant `index,follow` is noise, and its *absence* is exactly
  what a crawler treats as `index,follow`. The comparison is whitespace-
  insensitive (`index, follow` ≡ `index,follow`); a deviating directive is
  emitted **verbatim**. `seo.robots.emit_default = true` forces the tag.
- Support deterministic **advanced directives**: `noindex`, `nofollow`,
  `noarchive`, `nosnippet`, `max-snippet`, `max-image-preview`,
  `max-video-preview`, `notranslate`, `unavailable_after`. These are resolved
  string values; their **precedence is the resolver chain**
  (global → route → model → explicit). Same inputs ⇒ same output.

### Open Graph

- `og:title`, `og:description`, `og:type`, `og:url`, `og:site_name`,
  `og:locale`.
- `article:*` (`published_time`, `modified_time`, `author`, `section`, `tag`)
  **only when `og:type === 'article'` and the value is real** — never
  fabricated, never on a non-article page.
- `og:image` with `og:image:width` / `og:image:height` / `og:image:alt` and
  `og:image:type` **when known**. Multiple images are **grouped** — each
  `og:image` is immediately followed by its own dimension/alt/type properties.

### Twitter Cards

- `twitter:card`, `twitter:title`, `twitter:description`, `twitter:image`, and
  `twitter:image:alt` (when an image alt is known).
- `twitter:site` and `twitter:creator` are **optional and independent** — one
  may be present without the other, and neither is fabricated from the other.

### hreflang & locale

- hreflang alternates, when present: **absolute, normalized, unique by lang**,
  reciprocal where the data is complete. `x-default` only when configured.
- `og:locale:alternate` mirrors **only** locales that have a real social
  variant (map `en-US` → `en_US`; compare on the mapped form, do not demand
  literal equality).
- `<html lang>` parity with the resolved locale (this clause lives in the
  contract even though the *app* emits the `<html>` element).

### Per-page JSON-LD

- Parseable and `</script>`-safe (the payload is `JSON_HEX_TAG`-encoded so no
  value can terminate the script element early — a stored-XSS guard).
- **Multiple `<script>` blocks OR a combined `@graph`** are both acceptable.
- A stable `@id` is used **only where entities actually link** (Organization ↔
  WebSite ↔ WebPage); a stable `@id` is *not* mandatory on standalone nodes.

---

## 2. Normalization & invariants

- **Absolute `http(s)` URLs** for `canonical`, `og:url`, `og:image`,
  `twitter:image`. **No empty or null tags** ever reach the DOM.
- **`canonical` and `og:url` MUST resolve to the same normalized URL.**
  Disagreement is a **HARD failure**, not a warning.
- The **canonical normalization policy is consistent** across the surface:
  scheme / host / port / path-case / query-allowlist / trailing-slash handled
  the same way every time. Indexable pages are **self-referencing**; a
  `noindex` page does **not** inherit another page's canonical strategy.
- **Escaping is per sink**: HTML-attribute vs. text vs. JSON each use the
  correct encoder. Assertions compare **decoded semantic values, not bytes**.
- **Cross-renderer parity is semantic, not byte-for-byte.** `render()` (HTML)
  ≡ `toArray()` ≡ `toInertiaHead()` *after normalization* — the three
  representations legitimately differ in tag order and form. Singleton vs.
  repeatable property rules are explicit (one `og:title`; many `article:tag`).
- **Tag ownership**: a client renderer replaces *package-owned* tags (keyed,
  see §4) without deleting unrelated app-owned tags.

---

## 3. Behaviour — client-side navigation

After every Inertia visit or Livewire `wire:navigate`:

- there is **exactly one of each singleton** (`<title>`, description,
  canonical, each `og:*`/`twitter:*`), **none stale**;
- **JSON-LD does not accumulate** — schema from a previous page is removed, not
  layered on top (Livewire treats `<script>` as a non-removable asset, so
  schema scripts are tagged `data-seo-schema` + a per-URL id and the prior
  page's are removed on `livewire:navigated` — see the Livewire guide);
- navigating from a **metadata-rich page to a bare one tears the extra tags
  down** (the bare page does not keep the rich page's description/og/schema);
- **zero hydration warnings**, and the meta is semantically identical
  pre- and post-hydration.

---

## 4. Inertia head-keys (tag ownership)

`toInertiaHead()` stamps a stable **`head-key`** on every meta/link entry.
Inertia dedupes head elements by this attribute: a page `<Head>` tag with the
same `head-key` as a layout tag *replaces* it instead of stacking a duplicate.

- Base key = `name ?? property` for meta, `rel` for links.
- **Repeatable tags are disambiguated** so each stays uniquely keyed:
  `article:tag` → `article:tag`, `article:tag:1`, …; hreflang →
  `alternate:en-US`, `alternate:fr-FR`.

Bind it in templates as **`:head-key`** — *not* Vue's `:key` (which is the
unrelated `v-for` reconciliation key and does nothing for Inertia's head dedup).

---

## 5. Crawler visibility (explicit modes)

- **SSR / prerender** MUST emit the full contract in the **raw HTTP HTML** —
  this is tested separately from the hydrated DOM (JS disabled).
- **CSR-only cannot claim crawler compliance.** Default (no-SSR) Inertia injects
  meta *client-side*: the initial HTML a crawler fetches has no SEO meta. This
  is documented, not hidden — **crawler-visible meta requires Inertia SSR or
  prerendering** (and JSON-LD for crawlers should be server-rendered).

---

## 6. Out of scope / non-goals

- **App concerns, not the renderer's**: `charset`, `viewport`, favicons. (Note:
  `<meta charset>` must precede any non-ASCII metadata, so the app owns
  ordering of those head elements.)
- **The e2e asserts emitted output only.** It does **not** assert Google
  indexing, canonical *selection*, rich-result eligibility, or ranking; and it
  does **not** assert remote-image MIME/availability. Those belong in optional
  integration/HTTP tests, never the browser matrix.

---

## 7. Conformance status

What proves each clause today. **Unit** = `RenderingContractTest` (core, every
push). **Browser/SSR** = `rankbeam-examples` (RT5, scheduled matrix). **App** =
the host application owns it. **Planned** = in the contract as the target, but
the data is not yet modelled by `SEOData`, so the renderer emits the safe
subset.

| Clause | Status |
|---|---|
| Exactly one resolved `<title>`, no double suffix | **Unit** + Browser |
| Meta description only when present | **Unit** + Browser |
| One `<link rel="canonical">`, never empty | **Unit** + Browser |
| Robots emitted only when deviating from default; verbatim; `emit_default` toggle | **Unit** + Browser |
| Advanced robots directives via resolver precedence | **Unit** (resolver) |
| `og:title/description/type/url/site_name/locale`; locale `en-US`→`en_US` | **Unit** + Browser |
| `article:*` only when `og:type=article` and real | **Unit** + Browser |
| `og:image` present + absolute | **Unit** + Browser |
| `og:image:width/height/alt`, `og:image:type`, multiple-image grouping | **Planned** — `SEOData` carries a single `ogImage` string; no dimensions/alt/type modelled yet. Renderer emits one absolute `og:image`. |
| `twitter:card/title/description/image`; `site`/`creator` independent | **Unit** + Browser |
| `twitter:image:alt` | **Planned** — no image-alt field modelled yet. |
| hreflang absolute, unique by lang | **Unit** + Browser |
| hreflang reciprocity, `x-default` when configured | Browser (data-dependent) |
| `og:locale:alternate` mirrors real social variants | **Planned** — no per-locale social-variant map modelled yet. |
| `<html lang>` parity | **App** (+ Browser asserts it) |
| JSON-LD parseable + `</script>`-safe | **Unit** + Browser |
| Multiple scripts OR `@graph`; stable `@id` where entities link | **Unit** (merchant graph) + Browser |
| Absolute URLs; no empty/null tags | **Unit** + Browser |
| `canonical` ≡ `og:url` (hard failure on disagreement) | **Unit** + Browser |
| Consistent canonical normalization; self-referencing; noindex isolation | Browser (RT5) |
| Per-sink escaping; decoded semantic parity | **Unit** |
| Cross-renderer semantic parity (`render()` ≡ `toArray()` ≡ `toInertiaHead()`) | **Unit** |
| Inertia `head-key` stable + repeatables disambiguated | **Unit** + Browser |
| Client-nav: one singleton, none stale, JSON-LD doesn't accumulate, teardown | Browser (RT5) — renderer ships the `data-seo-schema` hooks the cleanup needs |
| Zero hydration warnings; pre/post-hydration parity | Browser (RT5) |
| SSR emits full contract in raw HTML; CSR-only documented as non-compliant | Browser (RT5) + docs |

**Planned clauses** are deliberate, documented gaps — the contract is the
durable target and these are additive, backward-compatible extensions for a
future thread (they require new `SEOData` fields / columns, a SemVer-minor).
The renderer emits the safe subset today; it never fabricates a value it does
not have.
