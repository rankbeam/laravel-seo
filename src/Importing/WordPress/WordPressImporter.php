<?php

declare(strict_types=1);

namespace Rankbeam\Seo\Importing\WordPress;

use Illuminate\Database\Eloquent\Model;
use Rankbeam\Seo\Importing\AbstractImporter;
use Rankbeam\Seo\Importing\ImportOptions;
use Rankbeam\Seo\Importing\ImportResult;

/**
 * Shared machinery for importing SEO data out of WordPress (a CSV export, or
 * the WordPress database directly).
 *
 * WordPress is **not** Laravel-morph data: a row is keyed by a URL or a post
 * ID, not by an Eloquent model. Core `seo_meta` rows, on the other hand, are
 * polymorphic — every row needs a real `seoable` model. So this base offers
 * two honest paths, and the report says which each row took:
 *
 *  - **Model-attached.** When `--model=` names a content model and a row's
 *    slug matches a record (by the model route key, or `--match-by=`), the SEO
 *    metadata is written onto that model's `seo_meta` row via the inherited
 *    {@see AbstractImporter::writeSeoMeta()} — fully idempotent and dry-run
 *    aware.
 *  - **URL-only.** A row with no `--model`, or one whose slug matches nothing,
 *    cannot become a `seo_meta` row (there is no model to attach it to). It is
 *    reported as skipped with a "url-only" reason — and, if `--redirects-csv=`
 *    is set, its canonical can still be emitted as a redirect candidate.
 *
 * **Redirects** never touch the database here. `seo_redirects` is a Pro table,
 * so the importer emits a CSV (via {@see RedirectCsvWriter}) the operator
 * imports into Pro — no core→Pro dependency.
 *
 * **Template tokens.** Yoast and Rank Math store titles/descriptions as
 * templates containing tokens (`%%title%%`, `%%sitename%%`, `%%sep%%`, …).
 * {@see self::resolveTokens()} resolves the ones we can derive and strips the
 * rest, so a stored value is never a raw `%%token%%` string.
 */
abstract class WordPressImporter extends AbstractImporter
{
    /**
     * Resolve the single target content model from `--model`, or null when the
     * run has no model scope (everything is then URL-only). WordPress posts and
     * pages usually map to different Laravel models, so the importer attaches
     * one model class per run; pass `--post-type=` to scope the rows to match.
     *
     * @return class-string<Model>|null
     */
    protected function targetModelClass(ImportOptions $options, ImportResult $result): ?string
    {
        if (! $options->hasModelFilter()) {
            return null;
        }

        $requested = $options->models[0];
        $class = $this->resolveModelClass($requested);

        if ($class === null) {
            $result->warn(sprintf(
                'Could not resolve --model [%s] to an Eloquent model; rows will be treated as URL-only.',
                $requested,
            ));
        }

        if (count($options->models) > 1) {
            $result->warn(
                'Multiple --model values were given; WordPress importers attach a single model per run. '
                .'Using the first and ignoring the rest — run once per content type (with --post-type).',
            );
        }

        return $class;
    }

    /**
     * Find the model a WordPress slug belongs to, or null when there is no
     * target class or nothing matches.
     *
     * @param  class-string<Model>|null  $class
     */
    protected function matchModel(?string $class, ?string $matchBy, ?string $slug): ?Model
    {
        if ($class === null || $slug === null || $slug === '') {
            return null;
        }

        $column = $matchBy ?? (new $class)->getRouteKeyName();

        /** @var Model|null $model */
        $model = $class::query()->where($column, $slug)->first();

        return $model;
    }

    /**
     * Resolve the WordPress template tokens we can derive and strip the rest,
     * so we never store a raw `%%token%%`/`%token%` string. Both the Yoast
     * (`%%token%%`) and Rank Math (`%token%`) syntaxes are handled.
     *
     * @param  array<string, string|null>  $context  Derivable values: title, sitename, sitedesc, excerpt, sep.
     */
    protected function resolveTokens(?string $value, array $context = []): ?string
    {
        if ($value === null) {
            return null;
        }

        if (! str_contains($value, '%')) {
            return $value;
        }

        $separator = $context['sep'] ?? '-';

        $known = [
            'title' => $context['title'] ?? '',
            'sitename' => $context['sitename'] ?? '',
            'sitedesc' => $context['sitedesc'] ?? '',
            'sep' => $separator,
            'excerpt' => $context['excerpt'] ?? '',
            'excerpt_only' => $context['excerpt'] ?? '',
            'name' => $context['title'] ?? '',
        ];

        $resolved = preg_replace_callback(
            '/%%?([a-z0-9_\-]+)%%?/i',
            static fn (array $m): string => $known[strtolower($m[1])] ?? '',
            $value,
        ) ?? $value;

        $resolved = $this->tidySeparators($resolved, $separator);

        return $resolved === '' ? null : $resolved;
    }

    /**
     * Collapse whitespace and trim a now-dangling separator from either end
     * (e.g. after `%%sitename%%` resolved to nothing in "Title %%sep%% %%sitename%%").
     */
    protected function tidySeparators(string $value, string $separator): string
    {
        $value = trim((string) preg_replace('/\s+/', ' ', $value));

        if ($separator !== '') {
            $quoted = preg_quote($separator, '/');
            $value = (string) preg_replace('/^(?:'.$quoted.'\s*)+/u', '', $value);
            $value = (string) preg_replace('/(?:\s*'.$quoted.')+$/u', '', $value);
        }

        return trim($value);
    }

    /**
     * Build the structured `focus_keywords` value core expects
     * (`[['keyword' => …, 'is_primary' => bool], …]`) from a raw, possibly
     * comma-separated keyword string. The first keyword is the primary one.
     *
     * @return array<int, array{keyword: string, is_primary: bool}>|null
     */
    protected function focusKeywords(?string $raw): ?array
    {
        if ($raw === null) {
            return null;
        }

        $parts = array_values(array_filter(
            array_map('trim', explode(',', $raw)),
            static fn (string $part): bool => $part !== '',
        ));

        if ($parts === []) {
            return null;
        }

        $keywords = [];

        foreach ($parts as $index => $keyword) {
            $keywords[] = ['keyword' => $keyword, 'is_primary' => $index === 0];
        }

        return $keywords;
    }

    /**
     * The path portion of a URL (or a bare path), normalised: leading slash,
     * no trailing slash (except root). Host and query string are dropped, so it
     * matches what the Pro redirect middleware compares a request path against.
     */
    protected function pathFromUrl(?string $url): ?string
    {
        if ($url === null || $url === '') {
            return null;
        }

        $path = parse_url(trim($url), PHP_URL_PATH);

        if (! is_string($path) || $path === '') {
            // A bare host ("https://old.test") canonicalises to root.
            return str_contains($url, '://') ? '/' : $this->normalizePath($url);
        }

        return $this->normalizePath($path);
    }

    /**
     * The last path segment of a URL — the WordPress post slug we match models
     * against.
     */
    protected function slugFromUrl(?string $url): ?string
    {
        $path = $this->pathFromUrl($url);

        if ($path === null || $path === '/') {
            return null;
        }

        $segments = array_values(array_filter(
            explode('/', $path),
            static fn (string $segment): bool => $segment !== '',
        ));

        return $segments === [] ? null : end($segments);
    }

    /**
     * Emit a redirect candidate from a page's URL to its declared canonical,
     * but only when the canonical points to a *different* path — a self-canonical
     * (same path) would be a redirect loop, so it is skipped.
     */
    protected function maybeEmitCanonicalRedirect(
        RedirectCsvWriter $writer,
        ImportResult $result,
        ?string $url,
        ?string $canonical,
        string $note,
    ): void {
        if (! $writer->enabled() || $url === null || $canonical === null) {
            return;
        }

        $sourcePath = $this->pathFromUrl($url);

        if ($sourcePath === null) {
            return;
        }

        $canonicalPath = $this->pathFromUrl($canonical);

        if ($canonicalPath !== null && $canonicalPath === $sourcePath) {
            return;
        }

        $this->emitRedirect($writer, $result, $sourcePath, $canonical, 301, $note);
    }

    /**
     * Record a redirect candidate: write the CSV row (unless dry-run/disabled),
     * tally it, and expose the destination file on the result.
     */
    protected function emitRedirect(
        RedirectCsvWriter $writer,
        ImportResult $result,
        string $sourcePath,
        string $targetUrl,
        int $statusCode,
        string $note,
    ): void {
        if (! $writer->enabled()) {
            return;
        }

        $writer->write($sourcePath, $targetUrl, $statusCode, $note);
        $result->redirectEmitted();
        $result->redirectsFile = $writer->path;
    }

    private function normalizePath(string $path): string
    {
        $path = '/'.ltrim(trim($path), '/');

        if ($path !== '/') {
            $path = rtrim($path, '/');
        }

        return $path;
    }
}
