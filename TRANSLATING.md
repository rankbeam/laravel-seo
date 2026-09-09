# Translating Rankbeam

Rankbeam ships its user-facing strings as Laravel language files, in every package:

| Package | Namespace | Publish tag | File |
|---|---|---|---|
| `rankbeam/laravel-seo` (core) | `seo` | `seo-lang` | `resources/lang/{locale}/seo.php` |
| `rankbeam/laravel-seo-filament` | `seo-filament` | `seo-filament-lang` | `resources/lang/{locale}/seo-filament.php` |
| `rankbeam/laravel-seo-pro` | `seo-pro` | `seo-pro-lang` | `resources/lang/{locale}/seo-pro.php` |

Web UI messages follow the application's translator locale, so a Filament panel
running in Italian shows Italian labels, counters and audit findings.

CLI presentation defaults to English. Set `seo.cli_locale` / `SEO_CLI_LOCALE`, or
pass `--display-locale=it`. This changes translated messages, not the application
content locale or machine identifiers. The audit summary is extracted in `cli.audit`;
command help, maintenance diagnostics, URLs, PASS/WARN/FAIL and issue codes remain
stable. Pro has its own `seo-pro.cli_locale` setting and translated checklist/report
summaries. Human labels in JSON may translate; consume keys and status codes instead.

`Rankbeam\Seo\I18n\DisplayLocale::run($locale, $callback)` scopes only the translator
and restores its previous locale even on exceptions. `ModelLocale::run()` separately
scopes content reads and restores both locale states. Do not return lazy work from
either callback that depends on a temporary locale.

## Override a string in your app

```bash
php artisan vendor:publish --tag=seo-lang
```

Edit `lang/vendor/seo/{locale}/seo.php`. Only the keys you keep are overridden; the rest fall
back to the package file, then to English.

## Contribute a language

1. Copy `resources/lang/en/seo.php` to `resources/lang/{locale}/seo.php` and translate the
   **values**. Never rename a key: keys are issue and warning codes, and the Pro scan, the MCP
   tools and CI reports identify findings by them.
2. Keep every placeholder (`:length`, `:max`, `:count`, …) exactly as it is. Move it in the
   sentence if the language needs it; do not translate or drop it.
3. Run the suite. `tests/Unit/Lang/TranslationParityTest.php` fails when a language file is
   missing a key, has a key `en` does not have, has an empty value, or has lost a placeholder.
4. Open a pull request titled `lang: add {language}` and add yourself to the credits below.

### Glossary — keep these as the term a developer in that language actually uses

| English | Rule |
|---|---|
| canonical, canonical URL | keep "canonical"; translate "URL" only if your language does |
| robots, noindex, nofollow, index, follow | never translate — they are directive names |
| Open Graph, og:image, Twitter Card | never translate |
| sitemap, JSON-LD, schema, structured data | keep the technical term; "structured data" may be translated |
| meta description, title tag | translate the noun ("description", "title"), keep "meta" |
| hreflang, llms.txt, robots.txt | never translate |
| crawler, bot | translate if your language has a standard word; otherwise keep |
| Google | never translate |

Machine-translated first passes are welcome as long as the PR says so; a native review is what
turns them into a supported language.

### Status

| Locale | Language | Status |
|---|---|---|
| `en` | English | source |
| `it` | Italian | reviewed by the maintainer |
| `de` | German | first pass, native review wanted |
| `fr` | French | first pass, native review wanted |
| `es` | Spanish | first pass, native review wanted |
| `pt_BR` | Portuguese (Brazil) | first pass, native review wanted |
| `nl` | Dutch | first pass, native review wanted |
| `tr` | Turkish | first pass, native review wanted |
| `ru` | Russian | first pass, native review wanted |
| `pl` | Polish | first pass, native review wanted |
| `ja` | Japanese | first pass, native review wanted |
| `zh_CN` | Chinese (Simplified) | first pass, native review wanted |
| `zh_TW` | Chinese (Traditional, Taiwan) | first pass, native review wanted |
| `ko` | Korean | first pass, native review wanted |
| `el` | Greek | first pass, native review wanted |
| `uk` | Ukrainian | first pass, native review wanted |
| `cs` | Czech | first pass, native review wanted |

Tier 1 (the first ten) and Tier 2 (the seven added in September 2026) are complete in all
three packages. Chinese ships as the two Laravel locales `zh_CN` and `zh_TW`; an app whose locale
is a bare `zh` should set `zh_CN` or `zh_TW` (Laravel resolves one locale plus the fallback, so a
`zh` app would fall through to English). Suggested next: Indonesian, Vietnamese, Swedish,
European Portuguese — cheap once a reviewer volunteers. Arabic and Hebrew need right-to-left
layout work in the editor, the OG templates and the reports and are not planned yet.

### Credits

- English — Rankbeam
- Italian — Valentin Goxhaj (review); first passes for the other languages by Claude, 2026-09-05
  (Tier 1) and 2026-09-07 (Tier 2)
