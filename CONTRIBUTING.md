# Contributing

Thanks for considering a contribution to **`rankbeam/laravel-seo`**, the free
MIT core of [Rankbeam](https://rankbeam.dev). This guide covers where a change
belongs, how to run the suite, and the standards a pull request is held to.

## Which repository?

Rankbeam is open-core, split across packages. Send a change to the repository
that owns the code:

| You want to change… | Repository | License |
|---|---|---|
| Meta resolution, rendering, JSON-LD, sitemaps, crawler controls, importers, the free `seo:audit` | **`rankbeam/laravel-seo`** (this repo) | MIT |
| Filament admin form fields and previews | [`rankbeam/laravel-seo-filament`](https://github.com/rankbeam/laravel-seo-filament) | MIT |
| Queued scans, the 0–100 score, redirect manager, 404 monitor, Search Console, reports, MCP | `rankbeam/laravel-seo-pro` (commercial) | Commercial |

This package **never** depends on the Filament or Pro packages. A change that
would pull either into the free core does not belong here.

## Reporting bugs and requesting features

- **Bugs / features:** open a [GitHub issue](https://github.com/rankbeam/laravel-seo/issues).
  A minimal reproduction (a failing test, or a small app + steps) gets a fix far
  faster than a description alone.
- **Security vulnerabilities:** do **not** open a public issue — follow
  [SECURITY.md](SECURITY.md).
- **Questions:** [hello@rankbeam.dev](mailto:hello@rankbeam.dev) or the
  [docs](https://docs.rankbeam.dev).

## Development setup

Requirements: PHP 8.2+ (the suite runs on 8.4), Composer.

```bash
git clone https://github.com/rankbeam/laravel-seo.git
cd laravel-seo
composer install
vendor/bin/pest        # run the test suite
vendor/bin/pint        # format to the project style
```

The suite uses [Pest](https://pestphp.com) on top of
[Orchestra Testbench](https://github.com/orchestral/testbench), so no external
database or services are needed. CI runs the 8-cell PHP × Laravel matrix
(PHP 8.2–8.4 × Laravel 11–13, excluding Laravel 13 on PHP 8.2, which it doesn't
support) on pushes to `master` and on every pull request
([`.github/workflows/tests.yml`](.github/workflows/tests.yml)).

## The rendering contract

The renderer's output shape is governed by the
[Rendering Contract](docs/contributing/rendering-contract.md): exactly-one-title,
`canonical ≡ og:url`, the robots emit-only-when-deviating policy, the OG /
Twitter / hreflang / JSON-LD value rules, and cross-renderer parity
(`render()` ≡ `toArray()` ≡ `toInertiaHead()`).

A change to `TagRenderer` must uphold that contract. It is pinned two ways: the
fast unit proof in `tests/Unit/Services/RenderingContractTest.php` (every push),
and the real-browser/SSR matrix in the
[`rankbeam-examples`](https://github.com/rankbeam/rankbeam-examples) reference
apps (blade, Inertia, Livewire). If you change rendered output, update or add
tests on both sides.

## Standards for a pull request

- **Tests.** New behaviour needs a test; a bug fix needs a regression test. Keep
  the suite green.
- **Formatting.** Run `vendor/bin/pint` before you push; CI expects it clean.
- **`declare(strict_types=1)`** at the top of every PHP file, as in the rest of
  the codebase.
- **Scope.** One focused change per PR. Describe the behaviour before and after,
  and link the issue it closes.
- **Docs.** If you change public behaviour, update the relevant page under
  [`docs/`](docs) and add a [CHANGELOG.md](CHANGELOG.md) entry under
  *Unreleased*.
- **No new runtime dependencies** without discussion — the core deliberately
  keeps a small footprint (Illuminate components, with `spatie/laravel-sitemap`
  and `spatie/browsershot` only as suggested extras).

## Versioning and backward compatibility

This package follows [Semantic Versioning](https://semver.org):

- **Patch** (`3.x.Y`) — backward-compatible bug and security fixes.
- **Minor** (`3.Y.0`) — backward-compatible additions.
- **Major** (`X.0.0`) — breaking changes, documented in
  [UPGRADING.md](UPGRADING.md).

Public API that we intend to remove is **deprecated in a minor release** (kept
working, marked in the CHANGELOG) and removed no earlier than the **next
major**. Every release is recorded in [CHANGELOG.md](CHANGELOG.md).

## License

By contributing, you agree that your contributions are licensed under the
project's [MIT license](LICENSE.md).
