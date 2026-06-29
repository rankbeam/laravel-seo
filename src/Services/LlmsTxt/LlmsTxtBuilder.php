<?php

declare(strict_types=1);

namespace Rankbeam\Seo\Services\LlmsTxt;

use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Rankbeam\Seo\Contracts\Sitemapable;
use Rankbeam\Seo\Services\Sitemap\SitemapRegistry;
use Rankbeam\Seo\Traits\HasSEO;
use Spatie\Sitemap\Tags\Url;

/**
 * Service for generating an llms.txt index.
 *
 * llms.txt (https://llmstxt.org) is a markdown index of a site's key content
 * that AI crawlers (GPTBot, ClaudeBot, PerplexityBot, Google-Extended) read to
 * understand a site the way robots.txt + sitemap.xml serve search crawlers.
 *
 * Rather than build a parallel source system, this REUSES the sitemap's content
 * sources: the same SitemapRegistry named sources behind
 * `SEO::sitemaps()->register($name, $source)` plus the models configured under
 * `seo.sitemap.models`. Each source becomes one `## Section` of bullets, and
 * each entry's title / description / absolute URL is derived the same way the
 * sitemap derives a model's URL (getUrlForSEO() + the resolved SEOData), so the
 * two artifacts never disagree about what's on the site.
 *
 * ## Usage
 * ```php
 * $builder = app(LlmsTxtBuilder::class);
 *
 * // Build the markdown without writing
 * $markdown = $builder->build();
 *
 * // Generate and write public/llms.txt
 * $builder->generate();
 * ```
 *
 * ## Output shape (the llms.txt convention)
 * ```markdown
 * # Acme Blog
 *
 * > The latest from the Acme engineering team.
 *
 * ## Posts
 *
 * - [Hello World](https://acme.test/posts/hello): Our first post.
 * - [Shipping Fast](https://acme.test/posts/shipping)
 * ```
 *
 * ## Configuration
 * ```php
 * // config/seo.php
 * 'llms_txt' => [
 *     'enabled' => true,
 *     'disk' => 'public',
 *     'path' => 'llms.txt',
 *     'title' => 'Acme Blog',
 *     'description' => 'The latest from the Acme engineering team.',
 *     'sources' => [],   // [] = all sitemap sources; or a whitelist of names
 *     'max_entries_per_section' => 100,
 * ]
 * ```
 *
 * @see \Rankbeam\Seo\Services\Sitemap\SitemapRegistry The reused source registry
 * @see \Rankbeam\Seo\Services\Sitemap\SitemapBuilder The XML counterpart
 */
class LlmsTxtBuilder
{
    /**
     * Default cap on bullets emitted per section.
     */
    protected int $maxEntriesPerSection = 100;

    /**
     * The shared registry of named sitemap sources, reused as llms.txt sources.
     */
    protected SitemapRegistry $registry;

    /**
     * Create a new llms.txt builder.
     *
     * The container injects the shared SitemapRegistry singleton, so the named
     * sources registered via SEO::sitemaps()->register(...) — the same ones the
     * sitemap renders — are visible here too.
     */
    public function __construct(?SitemapRegistry $registry = null)
    {
        $this->registry = $registry ?? new SitemapRegistry();
    }

    /**
     * Get the registry of named sources (shared with the sitemap subsystem).
     */
    public function sitemaps(): SitemapRegistry
    {
        return $this->registry;
    }

    /**
     * Generate and write the llms.txt file to the configured disk.
     */
    public function generate(): void
    {
        $this->getStorage()->put($this->getPath(), $this->build());
    }

    /**
     * Build the complete llms.txt markdown without writing.
     */
    public function build(): string
    {
        $lines = [];

        $lines[] = '# ' . $this->title();

        $summary = $this->summary();

        if ($summary !== null && $summary !== '') {
            $lines[] = '';
            $lines[] = '> ' . $this->oneLine($summary);
        }

        foreach ($this->sections() as $section) {
            if ($section['entries'] === []) {
                continue;
            }

            $lines[] = '';
            $lines[] = '## ' . $section['heading'];
            $lines[] = '';

            foreach ($section['entries'] as $entry) {
                $lines[] = $this->renderEntry($entry);
            }
        }

        return implode("\n", $lines) . "\n";
    }

    /**
     * Build the ordered sections from the reused sitemap sources.
     *
     * The contents mirror the sitemap registry exactly: each configured
     * `seo.sitemap.models` entry and each registered named source becomes one
     * section. When `seo.llms_txt.sources` is a non-empty list it acts as a
     * whitelist of named sources (config models are always included).
     *
     * @return array<int, array{heading: string, entries: array<int, array{title: string, url: string, description: ?string}>}>
     */
    protected function sections(): array
    {
        $sections = [];
        $allowed = $this->allowedSourceNames();

        // Configured models (seo.sitemap.models) — same source list the sitemap
        // builds from, normalized to a class => config map.
        foreach ($this->modelsConfig() as $modelClass => $config) {
            $sections[] = [
                'heading' => $this->headingForModel($modelClass),
                'entries' => $this->entriesForModel($modelClass),
            ];
        }

        // Registered named sources (SEO::sitemaps()->register(...)).
        foreach ($this->registry->names() as $name) {
            if ($allowed !== null && ! in_array($name, $allowed, true)) {
                continue;
            }

            $sections[] = [
                'heading' => $this->headingForName($name),
                'entries' => $this->entriesForSource($name),
            ];
        }

        return $sections;
    }

    /**
     * Entries for a configured Eloquent model class.
     *
     * Mirrors the sitemap's model pipeline: the same includability filter
     * (publish / noindex via shouldInclude()) and the same URL derivation
     * (getUrlForSEO()), bounded by max_entries_per_section.
     *
     * @param  class-string  $modelClass
     * @return array<int, array{title: string, url: string, description: ?string}>
     */
    protected function entriesForModel(string $modelClass): array
    {
        $entries = [];
        $max = $this->maxEntriesPerSection();

        $query = $modelClass::query();

        if (method_exists($modelClass, 'scopeSitemapable')) {
            $query->sitemapable();
        }

        foreach ($query->cursor() as $model) {
            if (! $this->shouldInclude($model)) {
                continue;
            }

            $entry = $this->entryForModel($model);

            if ($entry === null) {
                continue;
            }

            $entries[] = $entry;

            if (count($entries) >= $max) {
                break;
            }
        }

        return $entries;
    }

    /**
     * Entries for a registered named source.
     *
     * An Eloquent model-class source runs the same model pipeline as a
     * configured model; a closure/iterable source yields URL strings, arrays,
     * Spatie Url tags, or models — normalized exactly like the sitemap registry.
     *
     * @return array<int, array{title: string, url: string, description: ?string}>
     */
    protected function entriesForSource(string $name): array
    {
        $source = $this->registry->get($name);

        if ($this->isModelClassSource($source)) {
            /** @var class-string $source */
            return $this->entriesForModel($source);
        }

        if ($source instanceof Closure) {
            $source = $source();
        }

        $entries = [];
        $max = $this->maxEntriesPerSection();

        foreach ($source as $item) {
            $entry = $this->entryForItem($item);

            if ($entry === null) {
                continue;
            }

            $entries[] = $entry;

            if (count($entries) >= $max) {
                break;
            }
        }

        return $entries;
    }

    /**
     * Normalize a single non-model source item to an entry.
     *
     * Accepts the same shapes the sitemap registry accepts: a Spatie Url tag,
     * an Eloquent model, a URL string (relative paths are made absolute), or an
     * array with a 'url' key plus an optional 'title'/'description'.
     *
     * @return array{title: string, url: string, description: ?string}|null
     */
    protected function entryForItem(mixed $item): ?array
    {
        if ($item instanceof Url) {
            $url = $item->url;

            return $url === '' ? null : [
                'title' => $this->fallbackTitle($url),
                'url' => $url,
                'description' => null,
            ];
        }

        if ($item instanceof Model) {
            return $this->shouldInclude($item) ? $this->entryForModel($item) : null;
        }

        if (is_string($item) && $item !== '') {
            $url = $this->absoluteUrl($item);

            return [
                'title' => $this->fallbackTitle($url),
                'url' => $url,
                'description' => null,
            ];
        }

        if (is_array($item) && ! empty($item['url']) && is_string($item['url'])) {
            $url = $this->absoluteUrl($item['url']);
            $title = isset($item['title']) && is_string($item['title']) && $item['title'] !== ''
                ? $item['title']
                : $this->fallbackTitle($url);
            $description = isset($item['description']) && is_string($item['description']) && $item['description'] !== ''
                ? $item['description']
                : null;

            return [
                'title' => $title,
                'url' => $url,
                'description' => $description,
            ];
        }

        return null;
    }

    /**
     * Build an entry for an Eloquent model.
     *
     * The URL is the model's getUrlForSEO() (the same value the sitemap uses).
     * Title and description come from the model's RESOLVED SEOData when it uses
     * the HasSEO trait, so the bullet matches the page's real <title> and meta
     * description; a model without resolved SEO degrades to a URL-derived title.
     * Resolving touches user getters and the database, so any failure degrades
     * gracefully to the URL-only entry rather than aborting the whole file.
     *
     * @return array{title: string, url: string, description: ?string}|null
     */
    protected function entryForModel(Model $model): ?array
    {
        $url = method_exists($model, 'getUrlForSEO') ? $model->getUrlForSEO() : null;

        if (! is_string($url) || $url === '') {
            return null;
        }

        $title = null;
        $description = null;

        if (method_exists($model, 'seoData')) {
            try {
                $seo = $model->seoData();
                $title = $this->nonEmpty($seo->title);
                $description = $this->nonEmpty($seo->description);
            } catch (\Throwable) {
                // Degrade to a URL-derived title rather than failing the file.
            }
        }

        return [
            'title' => $title ?? $this->fallbackTitle($url),
            'url' => $url,
            'description' => $description,
        ];
    }

    /**
     * Render one entry as a markdown bullet.
     *
     * `- [title](url): description` — the description (and its colon) is omitted
     * when absent. Markdown-significant characters in the title and the
     * descriptions are neutralized so the line never breaks the list shape.
     *
     * @param  array{title: string, url: string, description: ?string}  $entry
     */
    protected function renderEntry(array $entry): string
    {
        $line = '- [' . $this->escapeTitle($entry['title']) . '](' . $entry['url'] . ')';

        if ($entry['description'] !== null && $entry['description'] !== '') {
            $line .= ': ' . $this->oneLine($entry['description']);
        }

        return $line;
    }

    /**
     * The configured models, normalized to a class => config map.
     *
     * Reads the same `seo.sitemap.models` key the sitemap reads and normalizes
     * the simple (`[Model::class]`) and full (`[Model::class => [...]]`) forms
     * identically, so both artifacts cover the same set of models.
     *
     * @return array<class-string, array<string, mixed>>
     */
    protected function modelsConfig(): array
    {
        $configured = config('seo.sitemap.models', []);

        if (! is_array($configured) || $configured === []) {
            return [];
        }

        $normalized = [];

        foreach ($configured as $key => $value) {
            if (is_int($key)) {
                $normalized[$value] = [];
            } else {
                $normalized[$key] = is_array($value) ? $value : [];
            }
        }

        return $normalized;
    }

    /**
     * The whitelist of registered source names to include, or null for all.
     *
     * @return array<int, string>|null
     */
    protected function allowedSourceNames(): ?array
    {
        $sources = config('seo.llms_txt.sources', []);

        if (! is_array($sources) || $sources === []) {
            return null;
        }

        return array_values(array_filter($sources, 'is_string'));
    }

    /**
     * Whether a registered source is an Eloquent model class.
     */
    protected function isModelClassSource(mixed $source): bool
    {
        return is_string($source)
            && class_exists($source)
            && is_subclass_of($source, Model::class);
    }

    /**
     * Whether a model should be included — same contract as the sitemap.
     *
     * Honours the Sitemapable / HasSEO shouldIncludeInSitemap() hook, excludes a
     * resolved-noindex page, and skips the common unpublished/draft patterns, so
     * the llms.txt index never lists a URL the sitemap omits.
     */
    protected function shouldInclude(Model $model): bool
    {
        if ($model instanceof Sitemapable && ! $model->shouldIncludeInSitemap()) {
            return false;
        }

        if (method_exists($model, 'shouldIncludeInSitemap') && ! $model->shouldIncludeInSitemap()) {
            return false;
        }

        // Resolved robots: a noindex coming from any layer (global / route /
        // computed / stored) excludes the URL, matching the sitemap.
        if (method_exists($model, 'seoData')) {
            try {
                if (str_contains(strtolower($model->seoData()->robots ?? ''), 'noindex')) {
                    return false;
                }
            } catch (\Throwable) {
                // Fall through to the cheaper checks below.
            }
        }

        if (! method_exists($model, 'seoData') && method_exists($model, 'seoMeta')) {
            $seoMeta = $model->seoMeta;

            if ($seoMeta && str_contains(strtolower($seoMeta->robots ?? ''), 'noindex')) {
                return false;
            }
        }

        if (isset($model->is_published) && ! $model->is_published) {
            return false;
        }

        if (isset($model->status) && ! in_array($model->status, ['published', 'active'], true)) {
            return false;
        }

        return true;
    }

    /**
     * The configured H1 site title (falls back to the site name / app name).
     */
    protected function title(): string
    {
        $title = config('seo.llms_txt.title');

        if (is_string($title) && $title !== '') {
            return $this->oneLine($title);
        }

        $fallback = config('seo.site_name', config('app.name', 'Site'));

        return $this->oneLine(is_string($fallback) && $fallback !== '' ? $fallback : 'Site');
    }

    /**
     * The optional blockquote summary, or null when none is configured.
     */
    protected function summary(): ?string
    {
        $description = config('seo.llms_txt.description');

        return is_string($description) && $description !== '' ? $description : null;
    }

    /**
     * The section heading for a configured model class (its plural basename).
     *
     * @param  class-string  $modelClass
     */
    protected function headingForModel(string $modelClass): string
    {
        return Str::headline(Str::plural(class_basename($modelClass)));
    }

    /**
     * The section heading for a registered named source.
     */
    protected function headingForName(string $name): string
    {
        return Str::headline($name);
    }

    /**
     * Derive a readable title from a URL's last path segment.
     */
    protected function fallbackTitle(string $url): string
    {
        $path = parse_url($url, PHP_URL_PATH);

        if (! is_string($path) || trim($path, '/') === '') {
            return 'Home';
        }

        $segment = basename(rtrim($path, '/'));

        return Str::headline($segment);
    }

    /**
     * Turn a relative path into an absolute URL; pass absolute URLs through.
     *
     * Mirrors SitemapBuilder::absoluteUrl() so both artifacts emit identical
     * absolute URLs for the same source item.
     */
    protected function absoluteUrl(string $url): string
    {
        if (str_starts_with($url, 'http://') || str_starts_with($url, 'https://')) {
            return $url;
        }

        return url($url);
    }

    /**
     * Collapse a value to a single trimmed line (newlines become spaces).
     */
    protected function oneLine(string $value): string
    {
        return trim((string) preg_replace('/\s+/', ' ', $value));
    }

    /**
     * Neutralize the markdown-significant characters in a link title so the
     * `[title](url)` shape can never be broken by a stray bracket or newline.
     */
    protected function escapeTitle(string $title): string
    {
        $title = $this->oneLine($title);

        return str_replace(['[', ']'], ['\[', '\]'], $title);
    }

    /**
     * Return the value when it is a non-empty string after trimming, else null.
     */
    protected function nonEmpty(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $trimmed = trim($value);

        return $trimmed === '' ? null : $value;
    }

    /**
     * The configured per-section entry cap (at least 1).
     */
    protected function maxEntriesPerSection(): int
    {
        return max(1, (int) config('seo.llms_txt.max_entries_per_section', $this->maxEntriesPerSection));
    }

    /**
     * The llms.txt path on the disk (relative to the disk root).
     */
    protected function getPath(): string
    {
        $path = config('seo.llms_txt.path', 'llms.txt');

        return is_string($path) && $path !== '' ? $path : 'llms.txt';
    }

    /**
     * The storage disk the file is written to.
     */
    protected function getStorage(): FilesystemAdapter
    {
        $disk = config('seo.llms_txt.disk', config('seo.sitemap.disk', 'public'));

        return Storage::disk($disk);
    }

    /**
     * The public URL of the generated llms.txt.
     */
    public function getLlmsTxtUrl(): string
    {
        return url($this->getPath());
    }

    /**
     * Whether the llms.txt file exists on the configured disk.
     */
    public function exists(): bool
    {
        return $this->getStorage()->exists($this->getPath());
    }

    /**
     * Delete the generated llms.txt file.
     */
    public function delete(): void
    {
        $storage = $this->getStorage();
        $path = $this->getPath();

        if ($storage->exists($path)) {
            $storage->delete($path);
        }
    }
}
