# Documentation translations

English is the source. Italian is the first batch: installation, quickstart,
resolver precedence, sitemaps and Filament, plus a language home. Native
technical review of this documentation is pending; the maintainer's earlier
Italian package-string credit is not approval of these pages.

`docs/.vitepress/localization.ts` defines active locales and navigation.
Translated pages keep the English path and explicit heading anchors, preserve
code examples verbatim, and identify English-only links. The language switch
keeps the current page. `[fallback].paths.ts` generates a notice and an English
link for untranslated routes. Those notices are `noindex,follow`, canonicalize
to English, and are excluded from search, sitemap URLs/alternates and llms.
Only real translations receive reciprocal hreflang in the head and sitemap.

`translation-manifest.json` records each translated page's English source and
SHA-256 over UTF-8 with LF line endings. Every build rejects stale source
hashes. Review the English diff, update the translation, then update its hash;
do not refresh hashes merely to make the build pass. Add the native reviewer,
date and reviewed revision to `native_review` when actual approval exists.

After a batch:

```bash
npm run docs:build
node docs/scripts/check-localization.mjs
```

Check language switching, localized search, English fallbacks and narrow
viewports in a browser too. Restart `docs:preview` after rebuilding: its static
asset inventory can retain old hashed asset names. A broken stale preview is
not production evidence.

Next: the other five Italian pages (audit, AI crawlers, multilingual/hreflang,
OG images, migration), then German, French, Spanish and Brazilian Portuguese.
The target remains ten scoped pages per language; this batch does not close R11.
