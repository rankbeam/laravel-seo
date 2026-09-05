# Translating Rankbeam

Rankbeam ships its user-facing strings as Laravel language files, in every package:

| Package | Namespace | Publish tag | File |
|---|---|---|---|
| `rankbeam/laravel-seo` (core) | `seo` | `seo-lang` | `resources/lang/{locale}/seo.php` |
| `rankbeam/laravel-seo-filament` | `seo-filament` | `seo-filament-lang` | `resources/lang/{locale}/seo-filament.php` |
| `rankbeam/laravel-seo-pro` | `seo-pro` | `seo-pro-lang` | `resources/lang/{locale}/seo-pro.php` |

The package follows the application locale (`app()->getLocale()`), so a Filament panel
running in Italian shows Italian labels, counters and audit findings with no configuration.

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

### Credits

- English — Rankbeam
