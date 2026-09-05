---
description: "Rankbeam's audit findings, editor warnings and Filament labels follow the app locale. Publish the language files to override a string, or contribute a language."
---

# Translations

Every user-facing string the packages emit — audit findings, the live editor warnings under
the Filament fields, labels, previews, reports — is a Laravel language line. The packages
follow `app()->getLocale()`: a panel running in Italian shows Italian, with nothing to configure.

Issue and warning **codes** (`missing_title`, `title_too_long`, …) never change and are never
translated. Only the human sentence attached to a code is.

Shipped languages: English, Italian (reviewed), and first passes in German, French, Spanish,
Brazilian Portuguese, Dutch, Turkish, Russian and Polish. The exact status per locale is in
[TRANSLATING.md](https://github.com/rankbeam/laravel-seo/blob/master/TRANSLATING.md); a
native review is what turns a first pass into a supported language.

## Override a string

```bash
php artisan vendor:publish --tag=seo-lang            # core (3.13+)
php artisan vendor:publish --tag=seo-filament-lang   # Filament fields (1.6+)
php artisan vendor:publish --tag=seo-pro-lang        # Pro (2.31+)
```

Then edit `lang/vendor/seo/{locale}/seo.php` (and the sibling folders for the other
packages). Keys you keep are overridden; the rest fall back to the package file, then to
English.

## Contribute a language

Copy the `en` file to your locale, translate the values, keep every `:placeholder`, run the
suite (a parity test fails on any missing key, orphan key, empty value or lost placeholder)
and open a pull request. The full rules and the glossary are in
[TRANSLATING.md](https://github.com/rankbeam/laravel-seo/blob/master/TRANSLATING.md).

## What is not translated on purpose

- CLI chrome (`seo:audit` table headings, `seo:explain` output): English, like Artisan itself.
- Rendered HTML (`<meta>`, JSON-LD): your content's language, never the package's.
- Issue codes and the `--json` output of every command: stable identifiers.
