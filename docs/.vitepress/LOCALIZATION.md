# Documentation localization

All editions use the same Home component and navigation trees. Keep English sections, order, examples and design when translating. Existing guide translations are not final editorial approvals; page reviews are recorded in `translation-manifest.json`.

## Layout and navigation

`navigation.ts` and `home-content.ts` define the English structure. `layout-messages.json` contains complete English, Italian, German, French, Spanish and Brazilian Portuguese labels and homepage copy. `layout-localization.ts` maps destinations without changing the tree. Published localized website and Italian article links stay in-language; unavailable pages carry an explicit English label and use the existing fallback notice.

`layout-review.json` binds each locale's review to the normalized LF bytes of `navigation.ts`, `home-content.ts`, `theme/components/Home.vue`, then `JSON.stringify(layoutMessages.en)`, concatenated in that order and SHA-256 hashed. The build rejects missing keys/locales and stale source bindings. Update a review hash only after reviewing the English changes and corresponding translations. Codex editorial approval is separate from independent native review.

## Adding a translated guide

1. Translate the complete English Markdown into the matching locale path. Retain code and technical caveats.
2. Record its English source path/hash and actual review in `translation-manifest.json`.
3. Build. Navigation discovers translated files across the entire English inventory; the English cue and generated fallback disappear automatically. The page joins reciprocal alternates, sitemap, raw Markdown and the documentation index.
4. Check the page, its anchors, code copy, search, mobile navigation and language switches in a browser. Record editorial approval separately from build checks.

Local checks:

```sh
npm ci
npm run docs:build
# Node 24+ supports the TypeScript source imports used by these focused checks.
node --test docs/.vitepress/layout.test.mjs
```

The sidebar has separate core, Pro and reference trees in every locale. `llms.ts` flattens both default and locale sidebar maps and excludes fallback-only routes. The homepage is a shared Vue component; its raw Markdown source continues to contain `<Home />`, as in English.
