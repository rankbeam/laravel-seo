<?php

declare(strict_types=1);

namespace Rankbeam\Seo\Services\Sitemap;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Spatie\Sitemap\Sitemap;
use Spatie\Sitemap\SitemapIndex;
use Spatie\Sitemap\Tags\Url;
use Rankbeam\Seo\Contracts\Sitemapable;
use Rankbeam\Seo\Traits\HasSEO;

/**
 * Service for generating XML sitemaps.
 *
 * Wraps spatie/laravel-sitemap with our configuration and auto-discovery.
 * Supports multiple sitemaps via SitemapIndex for large sites.
 *
 * ## Usage
 * ```php
 * $builder = app(SitemapBuilder::class);
 *
 * // Generate and write all sitemaps
 * $builder->generate();
 *
 * // Generate for specific model
 * $sitemap = $builder->generateForModel(Post::class);
 *
 * // Preview without writing
 * $sitemap = $builder->build();
 * ```
 *
 * ## Configuration
 * ```php
 * // config/seo.php
 * 'sitemap' => [
 *     'enabled' => true,
 *     'disk' => 'public',
 *     'path' => 'sitemap.xml',
 *     'models' => [
 *         App\Models\Post::class => [
 *             'priority' => 0.8,
 *             'changefreq' => 'weekly',
 *         ],
 *         App\Models\Page::class,
 *     ],
 *     'max_urls_per_sitemap' => 50000,
 *
 *     // Optional extensions (off by default), for HasSEO models:
 *     'images' => true,      // <image:image> from the resolved og/content image
 *     'alternates' => true,  // <xhtml:link rel="alternate"> from getSEOAlternates()
 * ]
 * ```
 */
class SitemapBuilder
{
    /**
     * Maximum URLs per sitemap file (Google limit is 50,000).
     */
    protected int $maxUrlsPerSitemap = 50000;

    /**
     * Registry of programmatically registered sitemap sources.
     */
    protected SitemapRegistry $registry;

    /**
     * Create a new sitemap builder.
     *
     * The container injects the shared SitemapRegistry singleton, so
     * sources registered via SEO::sitemaps()->register(...) are visible
     * to every builder instance.
     */
    public function __construct(?SitemapRegistry $registry = null)
    {
        $this->registry = $registry ?? new SitemapRegistry();
    }

    /**
     * Get the registry of named sitemap sources.
     */
    public function sitemaps(): SitemapRegistry
    {
        return $this->registry;
    }

    /**
     * Generate and write all sitemaps.
     */
    public function generate(): void
    {
        $sitemap = $this->build();

        if ($sitemap instanceof SitemapIndex) {
            $this->writeSitemapIndex($sitemap);
        } else {
            $this->writeSitemap($sitemap);
        }
    }

    /**
     * Generate sitemap for a single model type.
     *
     * @param class-string $modelClass
     */
    public function generateForModel(string $modelClass): Sitemap
    {
        $config = $this->getModelConfig($modelClass);

        return $this->buildModelSitemap($modelClass, $config);
    }

    /**
     * Build the complete sitemap without writing.
     *
     * Returns SitemapIndex if multiple sitemaps are needed,
     * otherwise returns a single Sitemap.
     */
    public function build(): SitemapIndex|Sitemap
    {
        $modelsConfig = $this->getModelsConfig();
        $registered = $this->registry->sources();
        $staticUrls = $this->staticUrls();

        if (empty($modelsConfig) && empty($registered) && $staticUrls === []) {
            return Sitemap::create();
        }

        // Only static URLs (no models, no registered sources): emit them in a
        // single sitemap rather than spinning up a one-entry index.
        if (empty($modelsConfig) && empty($registered)) {
            return $this->buildStaticSitemap($staticUrls);
        }

        $totalUrls = $this->countTotalUrls($modelsConfig);
        $maxUrls = $this->maxUrlsPerSitemap();

        // Use index if we have registered sources (each gets its own
        // sitemap-{name}.xml), static URLs (sitemap-static.xml), multiple
        // models, or too many URLs.
        if (! empty($registered) || $staticUrls !== [] || count($modelsConfig) > 1 || $totalUrls > $maxUrls) {
            return $this->buildSitemapIndex($modelsConfig);
        }

        // Single sitemap
        $modelClass = array_key_first($modelsConfig);
        $config = $modelsConfig[$modelClass];

        return $this->buildModelSitemap($modelClass, $config);
    }

    /**
     * The configured static URLs, normalized to Spatie Url tags.
     *
     * Each entry is the array shape documented under seo.sitemap.static_urls
     * (['url' => '/about', 'priority' => 0.8, 'changefreq' => 'monthly']) or a
     * bare path/URL string. Relative paths are made absolute; malformed entries
     * are skipped so one bad row never breaks generation.
     *
     * @return array<int, Url>
     */
    protected function staticUrls(): array
    {
        $configured = config('seo.sitemap.static_urls', []);

        if (! is_array($configured) || $configured === []) {
            return [];
        }

        $urls = [];

        foreach ($configured as $entry) {
            $url = $this->normalizeSourceItem($entry);

            if ($url) {
                $urls[] = $url;
            }
        }

        return $urls;
    }

    /**
     * Build a sitemap from the configured static URLs.
     *
     * @param array<int, Url> $staticUrls
     */
    protected function buildStaticSitemap(array $staticUrls): Sitemap
    {
        $sitemap = Sitemap::create();

        foreach ($staticUrls as $url) {
            $sitemap->add($url);
        }

        return $sitemap;
    }

    /**
     * Get sitemap models configuration.
     *
     * @return array<class-string, array<string, mixed>>
     */
    protected function getModelsConfig(): array
    {
        $configured = config('seo.sitemap.models', []);

        if (! empty($configured)) {
            return $this->normalizeModelsConfig($configured);
        }

        // Registering named sources is an explicit choice of sitemap
        // contents — auto-discovery would duplicate those models under a
        // second filename (sitemap-post.xml vs sitemap-posts.xml).
        if (! empty($this->registry->sources())) {
            return [];
        }

        // Auto-discover models with HasSEO or Sitemapable
        return $this->discoverModels();
    }

    /**
     * Normalize models config to associative array.
     *
     * @param array<int|string, mixed> $models
     * @return array<class-string, array<string, mixed>>
     */
    protected function normalizeModelsConfig(array $models): array
    {
        $normalized = [];

        foreach ($models as $key => $value) {
            if (is_int($key)) {
                // Simple format: [Model::class]
                $normalized[$value] = [];
            } else {
                // Full format: [Model::class => ['priority' => 0.8]]
                $normalized[$key] = is_array($value) ? $value : [];
            }
        }

        return $normalized;
    }

    /**
     * Auto-discover sitemapable models.
     *
     * @return array<class-string, array<string, mixed>>
     */
    protected function discoverModels(): array
    {
        $models = [];
        $modelPath = app_path('Models');

        if (! File::isDirectory($modelPath)) {
            return $models;
        }

        $files = File::allFiles($modelPath);

        foreach ($files as $file) {
            $class = 'App\\Models\\' . str_replace(
                ['/', '.php'],
                ['\\', ''],
                $file->getRelativePathname()
            );

            if (
                class_exists($class)
                && is_subclass_of($class, Model::class)
                && $this->isSitemapable($class)
            ) {
                $models[$class] = [];
            }
        }

        return $models;
    }

    /**
     * Check if a model class is sitemapable.
     *
     * @param class-string $class
     */
    protected function isSitemapable(string $class): bool
    {
        $traits = class_uses_recursive($class);
        $interfaces = class_implements($class);

        return in_array(HasSEO::class, $traits, true)
            || in_array(Sitemapable::class, $interfaces, true);
    }

    /**
     * Build sitemap index with individual model sitemaps.
     *
     * Each model is sharded into as many numbered parts as it needs so no
     * file exceeds max_urls_per_sitemap, and every part is listed in the
     * index. The filenames here MUST match the ones writeSitemapIndex()
     * actually writes — both derive them from sitemapPartFilenames() (for
     * configured models) and registeredSourceFilenames() (for registered
     * sources, whose model-class sources are sharded the same way).
     *
     * @param array<class-string, array<string, mixed>> $modelsConfig
     */
    protected function buildSitemapIndex(array $modelsConfig): SitemapIndex
    {
        $index = SitemapIndex::create();

        foreach ($modelsConfig as $modelClass => $config) {
            $lastMod = $this->getLastModifiedForModel($modelClass);

            foreach ($this->sitemapPartFilenames($modelClass) as $filename) {
                $index->add(url($filename), $lastMod);
            }
        }

        // Registered named sources. A model-class source is sharded into the
        // same numbered parts as a configured model so it can never truncate
        // after the first file; closure/iterable sources stay a single file.
        foreach ($this->registry->names() as $name) {
            foreach ($this->registeredSourceFilenames($name) as $filename) {
                $index->add(url($filename));
            }
        }

        // Static URLs (config-driven, not backed by a model)
        if ($this->staticUrls() !== []) {
            $index->add(url('sitemap-static.xml'));
        }

        return $index;
    }

    /**
     * The ordered list of filenames a registered named source will produce.
     *
     * A model-class source shares the configured-model sharding: a single
     * sitemap-{name}.xml when it fits the cap, or numbered
     * sitemap-{name}-1.xml … parts when it does not. Closure/iterable sources
     * always produce a single sitemap-{name}.xml (their cap is enforced in a
     * single pass by buildSourceSitemap()).
     *
     * @return array<int, string>
     */
    protected function registeredSourceFilenames(string $name): array
    {
        $source = $this->registry->get($name);

        if ($this->isModelClassSource($source)) {
            /** @var class-string $source */
            return $this->sitemapPartFilenames($source, $name);
        }

        return ["sitemap-{$name}.xml"];
    }

    /**
     * Whether a registered source is an Eloquent model class (vs a closure or
     * a plain iterable of URL items).
     */
    protected function isModelClassSource(mixed $source): bool
    {
        return is_string($source)
            && class_exists($source)
            && is_subclass_of($source, Model::class);
    }

    /**
     * The ordered list of sitemap part filenames a model will produce.
     *
     * A model with up to max_urls_per_sitemap URLs writes a single
     * sitemap-{slug}.xml (the historical, un-numbered filename — kept so
     * small sites are unchanged). A model with more URLs is split into
     * sitemap-{slug}-1.xml, sitemap-{slug}-2.xml, … numbered parts.
     *
     * The slug defaults to the model class basename (configured models) but a
     * registered model-class source passes its registered name instead so its
     * parts are sitemap-{name}-N.xml — keeping the historical filename for the
     * single-file case while still sharding overflow.
     *
     * @param class-string $modelClass
     * @return array<int, string>
     */
    protected function sitemapPartFilenames(string $modelClass, ?string $slug = null): array
    {
        $slug ??= $this->getModelSlug($modelClass);
        $parts = max(1, $this->countSitemapParts($modelClass));

        if ($parts === 1) {
            return ["sitemap-{$slug}.xml"];
        }

        $filenames = [];

        for ($i = 1; $i <= $parts; $i++) {
            $filenames[] = "sitemap-{$slug}-{$i}.xml";
        }

        return $filenames;
    }

    /**
     * How many sitemap files a model needs given its includable URL count and
     * the configured per-file URL cap.
     *
     * Derived from the same keyset boundary walk the shards are built from
     * (sitemapPartBoundaries()), so the part count, the index entries, and the
     * written files can never disagree — even if rows are inserted/deleted
     * concurrently — because every one of them reads the snapshot of primary
     * keys captured by that single ordered walk.
     *
     * @param class-string $modelClass
     */
    protected function countSitemapParts(string $modelClass): int
    {
        return count($this->sitemapPartBoundaries($modelClass));
    }

    /**
     * Capture an immutable keyset boundary for each shard of a model.
     *
     * Walks the model's primary keys in ascending order (with the sitemapable
     * scope applied, matching the build queries) and chunks them into windows
     * of at most max_urls_per_sitemap keys. Each window is returned as
     * ['first' => firstKey, 'last' => lastKey] — the inclusive primary-key
     * range buildModelSitemapPart() then re-queries.
     *
     * Why keyset instead of OFFSET/LIMIT (finding F6): the part count and each
     * shard were previously SEPARATE queries over a mutable ordering, so a
     * concurrent insert/delete/updated_at change between them could shift rows
     * across page boundaries → a row in two parts, in none, or an
     * index/file-count mismatch. Pinning each shard to an immutable
     * primary-key range removes that race: a row's key never changes, so it
     * always falls in exactly the one window that contains it. Rows inserted
     * after the walk simply fall outside the last captured boundary (excluded
     * from this run, never duplicated); rows deleted within a window just make
     * that part emit fewer URLs (never a gap that drops another row). Keyset
     * is also far cheaper than large OFFSETs.
     *
     * Boundaries count keys, not post-shouldInclude() URLs (an exact
     * post-filter count would require materialising every model), so a window
     * may emit fewer URLs than its width — or, for a trailing window, be
     * empty. An empty part is valid XML and never drops a URL. A model with no
     * rows still yields a single empty window so it keeps its historical
     * single, un-numbered file.
     *
     * @param class-string $modelClass
     * @return array<int, array{first: mixed, last: mixed}>
     */
    protected function sitemapPartBoundaries(string $modelClass): array
    {
        $maxUrls = $this->maxUrlsPerSitemap();

        $instance = new $modelClass();
        $keyName = $instance->getKeyName();
        $qualifiedKey = $instance->getQualifiedKeyName();

        $query = $modelClass::query();

        if (method_exists($modelClass, 'scopeSitemapable')) {
            $query->sitemapable();
        }

        // Stream only the primary keys, ascending, in one ordered pass. Each
        // window's first/last key becomes the immutable shard boundary.
        $boundaries = [];
        $first = null;
        $last = null;
        $count = 0;

        foreach ($query->orderBy($qualifiedKey)->cursor() as $model) {
            $key = $model->getAttribute($keyName);

            if ($count === 0) {
                $first = $key;
            }

            $last = $key;
            $count++;

            if ($count >= $maxUrls) {
                $boundaries[] = ['first' => $first, 'last' => $last];
                $first = null;
                $last = null;
                $count = 0;
            }
        }

        // Flush a partial final window.
        if ($count > 0) {
            $boundaries[] = ['first' => $first, 'last' => $last];
        }

        // An empty model still produces one (empty) part so its filename and
        // index entry stay the historical single, un-numbered file.
        if ($boundaries === []) {
            $boundaries[] = ['first' => null, 'last' => null];
        }

        return $boundaries;
    }

    /**
     * The configured per-file URL cap (Google's hard limit is 50,000).
     */
    protected function maxUrlsPerSitemap(): int
    {
        return max(1, (int) config('seo.sitemap.max_urls_per_sitemap', $this->maxUrlsPerSitemap));
    }

    /**
     * Build a sitemap for a registered named source.
     *
     * A model-class source is sharded exactly like a configured model
     * (finding F1): $part selects which numbered window to build, so a source
     * over the cap is written across sitemap-{name}-1.xml, …-2.xml, … rather
     * than truncating after the first file. Closure/iterable sources are a
     * single pass capped at max_urls_per_sitemap — but if a source exceeds the
     * cap, the dropped count is logged so the omission is never silent.
     *
     * @param int $part 1-based shard index, used only for model-class sources.
     *
     * @throws \InvalidArgumentException When the name is not registered
     */
    public function buildSourceSitemap(string $name, int $part = 1): Sitemap
    {
        $source = $this->registry->get($name);

        // Eloquent model class: reuse the sharded model pipeline (publish
        // checks, noindex exclusion, priority/changefreq, numbered parts) so a
        // large registered source is never truncated after part 1.
        if ($this->isModelClassSource($source)) {
            return $this->buildModelSitemapPart($source, [], $part);
        }

        if ($source instanceof \Closure) {
            $source = $source();
        }

        $sitemap = Sitemap::create();
        $maxUrls = $this->maxUrlsPerSitemap();
        $count = 0;
        $dropped = 0;

        foreach ($source as $item) {
            $url = $this->normalizeSourceItem($item);

            if (! $url) {
                continue;
            }

            // Past the cap: keep counting so the log reports the true number of
            // URLs a single closure/iterable file could not hold.
            if ($count >= $maxUrls) {
                $dropped++;

                continue;
            }

            $sitemap->add($url);
            $count++;
        }

        if ($dropped > 0) {
            Log::warning(
                "Sitemap source [{$name}] exceeded max_urls_per_sitemap ({$maxUrls}); "
                . "dropped {$dropped} URL(s). Register it as an Eloquent model class to shard "
                . 'across numbered files, or split it into smaller named sources.'
            );
        }

        return $sitemap;
    }

    /**
     * Normalize a single source item to a Url tag.
     *
     * Accepts Spatie Url tags, Eloquent models (run through the standard
     * model URL pipeline), URL strings (relative paths are made absolute),
     * and arrays with a 'url' key plus optional lastmod/priority/changefreq.
     */
    protected function normalizeSourceItem(mixed $item): ?Url
    {
        if ($item instanceof Url) {
            return $item;
        }

        if ($item instanceof Model) {
            return $this->shouldInclude($item) ? $this->buildUrl($item, []) : null;
        }

        if (is_string($item) && $item !== '') {
            return Url::create($this->absoluteUrl($item));
        }

        if (is_array($item) && ! empty($item['url'])) {
            $item['url'] = $this->absoluteUrl($item['url']);

            return $this->createUrlFromArray($item, []);
        }

        return null;
    }

    /**
     * Turn a relative path into an absolute URL; pass absolute URLs through.
     */
    protected function absoluteUrl(string $url): string
    {
        if (str_starts_with($url, 'http://') || str_starts_with($url, 'https://')) {
            return $url;
        }

        return url($url);
    }

    /**
     * Build the first (or only) sitemap for a single model type.
     *
     * Kept as the historical single-file entry point — it builds part 1, which
     * for any model with <= max_urls_per_sitemap URLs is the whole sitemap. For
     * larger models, callers iterate every part via buildModelSitemapPart().
     *
     * @param class-string $modelClass
     * @param array<string, mixed> $config
     */
    protected function buildModelSitemap(string $modelClass, array $config): Sitemap
    {
        return $this->buildModelSitemapPart($modelClass, $config, 1);
    }

    /**
     * Build one numbered shard of a model's sitemap.
     *
     * Part N (1-based) covers the immutable primary-key window captured by
     * sitemapPartBoundaries() — [first, last] inclusive — rather than an
     * OFFSET/LIMIT slice of a mutable ordering (finding F6). Because the
     * boundary is a key range and keys never change, a row always belongs to
     * exactly the one part that contains its key: concurrent inserts/deletes
     * between the boundary walk and this query can neither duplicate nor drop
     * a URL across shards. shouldInclude() still filters within the window, so
     * a part may emit fewer URLs than the window width (or, for an empty model
     * or a fully-deleted window, be empty); that is valid and never drops a
     * URL.
     *
     * @param class-string $modelClass
     * @param array<string, mixed> $config
     */
    protected function buildModelSitemapPart(string $modelClass, array $config, int $part): Sitemap
    {
        $sitemap = Sitemap::create();

        $boundaries = $this->sitemapPartBoundaries($modelClass);
        $boundary = $boundaries[$part - 1] ?? null;

        // No such part, or the model is empty (a single null-bounded window):
        // an empty, well-formed sitemap.
        if ($boundary === null || $boundary['first'] === null) {
            return $sitemap;
        }

        $instance = new $modelClass();
        $qualifiedKey = $instance->getQualifiedKeyName();

        $query = $modelClass::query();

        // Apply scope if model has one — same shape as the boundary walk.
        if (method_exists($modelClass, 'scopeSitemapable')) {
            $query->sitemapable();
        }

        // Re-query exactly the captured key range, ascending by primary key so
        // the window is stable and deterministic. whereBetween is an INCLUSIVE
        // [first, last] range over the immutable primary key. cursor() streams
        // the rows lazily without buffering.
        $query->whereBetween($qualifiedKey, [$boundary['first'], $boundary['last']])
            ->orderBy($qualifiedKey);

        foreach ($query->cursor() as $model) {
            if (! $this->shouldInclude($model)) {
                continue;
            }

            $url = $this->buildUrl($model, $config);

            if ($url) {
                $sitemap->add($url);
            }
        }

        return $sitemap;
    }

    /**
     * Check if a model should be included in sitemap.
     */
    protected function shouldInclude(Model $model): bool
    {
        // Check Sitemapable interface
        if ($model instanceof Sitemapable) {
            if (! $model->shouldIncludeInSitemap()) {
                return false;
            }
        }

        // Check HasSEO trait method
        if (method_exists($model, 'shouldIncludeInSitemap')) {
            if (! $model->shouldIncludeInSitemap()) {
                return false;
            }
        }

        // Check for noindex in the RESOLVED robots directive — so a noindex
        // coming from global / model-type / route / computed defaults (not just
        // a stored seo_meta.robots) excludes the URL, matching the documented
        // "resolved robots control inclusion" contract.
        if (method_exists($model, 'seoData')) {
            try {
                if (str_contains(strtolower($model->seoData()->robots ?? ''), 'noindex')) {
                    return false;
                }
            } catch (\Throwable) {
                // Resolving touches user getters + the DB; if one record blows
                // up, fall through to the stored-meta check below rather than
                // aborting the whole sitemap.
            }
        }

        // Fallback for models without resolved SEO data (no HasSEO trait):
        // honour an explicit noindex on the stored seo_meta row.
        if (! method_exists($model, 'seoData') && method_exists($model, 'seoMeta')) {
            $seoMeta = $model->seoMeta;
            if ($seoMeta && str_contains(strtolower($seoMeta->robots ?? ''), 'noindex')) {
                return false;
            }
        }

        // Check common published patterns
        if (isset($model->is_published) && ! $model->is_published) {
            return false;
        }

        if (isset($model->status) && ! in_array($model->status, ['published', 'active'], true)) {
            return false;
        }

        return true;
    }

    /**
     * Build a URL tag for a model.
     */
    protected function buildUrl(Model $model, array $config): ?Url
    {
        // Use model's toSitemapTag if available
        if ($model instanceof Sitemapable) {
            $tag = $model->toSitemapTag();

            if ($tag instanceof Url) {
                // The model hand-built its own Url tag (the manual Spatie
                // escape hatch — see docs/guide/sitemaps.md). Respect it
                // verbatim, including any images/alternates/videos it set.
                return $tag;
            }

            if (is_string($tag)) {
                $tag = ['url' => $tag];
            }

            $url = $this->createUrlFromArray($tag, $config);

            $this->applySeoExtensions($url, $model);

            return $url;
        }

        // Build from model properties
        $urlString = method_exists($model, 'getUrlForSEO')
            ? $model->getUrlForSEO()
            : null;

        if (! $urlString) {
            return null;
        }

        $url = Url::create($urlString);

        // Set last modification
        if ($model->updated_at) {
            $url->setLastModificationDate($model->updated_at);
        }

        // Apply config overrides
        if (isset($config['priority'])) {
            $url->setPriority((float) $config['priority']);
        }

        if (isset($config['changefreq'])) {
            $url->setChangeFrequency($config['changefreq']);
        }

        $this->applySeoExtensions($url, $model);

        return $url;
    }

    /**
     * Add optional image and hreflang-alternate entries to a model's URL tag.
     *
     * Both extensions are opt-in (seo.sitemap.images / seo.sitemap.alternates,
     * both off by default) and only apply to models exposing fully resolved
     * SEO data (the HasSEO trait's seoData()):
     *
     * - Images add an <image:image><image:loc> entry derived from the resolved
     *   og/content image — the same value rendered as og:image, so the sitemap
     *   never disagrees with the page meta. This can be the site-wide
     *   default_og_image when a model has no image of its own.
     * - Alternates add <xhtml:link rel="alternate" hreflang="..."> entries from
     *   the model's getSEOAlternates() hreflang links.
     *
     * Models that hand-build their own Url tag opt out entirely; this only
     * enriches tags the builder itself constructs, and never clobbers entries
     * already present on the tag.
     */
    protected function applySeoExtensions(Url $url, Model $model): void
    {
        $wantImages = (bool) config('seo.sitemap.images', false);
        $wantAlternates = (bool) config('seo.sitemap.alternates', false);

        if (! $wantImages && ! $wantAlternates) {
            return;
        }

        // Resolved SEO data is only available on HasSEO models.
        if (! method_exists($model, 'seoData')) {
            return;
        }

        // Resolving SEO data runs the full precedence chain, which touches
        // user-defined getters (getSEOImage/getSEOAlternates) and the database.
        // One bad record must never abort generation of the whole sitemap, so
        // degrade to the plain URL on any failure (the same graceful-degradation
        // stance the resolver itself takes).
        try {
            $seo = $model->seoData();

            if ($wantImages && ! empty($seo->ogImage) && empty($url->images)) {
                $url->addImage($seo->ogImage);
            }

            if ($wantAlternates && ! empty($seo->alternates) && empty($url->alternates)) {
                foreach ($seo->alternates as $alternate) {
                    // Tolerate a malformed alternates shape (a non-array entry
                    // would throw on string-offset access below).
                    if (! is_array($alternate)) {
                        continue;
                    }

                    $hreflang = $alternate['hreflang'] ?? null;
                    $href = $alternate['href'] ?? null;

                    if (is_string($hreflang) && $hreflang !== '' && is_string($href) && $href !== '') {
                        $url->addAlternate($href, $hreflang);
                    }
                }
            }
        } catch (\Throwable) {
            // Leave the URL without extensions rather than failing the sitemap.
        }
    }

    /**
     * Create URL from array data.
     *
     * @param array<string, mixed> $data
     * @param array<string, mixed> $config
     */
    protected function createUrlFromArray(array $data, array $config): Url
    {
        $url = Url::create($data['url']);

        if (isset($data['lastmod'])) {
            $url->setLastModificationDate($data['lastmod']);
        }

        $priority = $data['priority'] ?? $config['priority'] ?? null;
        if ($priority !== null) {
            $url->setPriority((float) $priority);
        }

        $changefreq = $data['changefreq'] ?? $config['changefreq'] ?? null;
        if ($changefreq) {
            $url->setChangeFrequency($changefreq);
        }

        return $url;
    }

    /**
     * Count total URLs across all models.
     *
     * @param array<class-string, array<string, mixed>> $modelsConfig
     */
    protected function countTotalUrls(array $modelsConfig): int
    {
        $total = 0;

        foreach (array_keys($modelsConfig) as $modelClass) {
            $query = $modelClass::query();

            if (method_exists($modelClass, 'scopeSitemapable')) {
                $query->sitemapable();
            }

            $total += $query->count();
        }

        return $total;
    }

    /**
     * Get model slug for sitemap filename.
     *
     * @param class-string $modelClass
     */
    protected function getModelSlug(string $modelClass): string
    {
        $className = class_basename($modelClass);

        return strtolower(preg_replace('/(?<!^)[A-Z]/', '-$0', $className) ?? $className);
    }

    /**
     * Get last modified date for a model type.
     *
     * @param class-string $modelClass
     */
    protected function getLastModifiedForModel(string $modelClass): ?\DateTime
    {
        $latest = $modelClass::query()
            ->orderBy('updated_at', 'desc')
            ->first();

        return $latest?->updated_at?->toDateTime();
    }

    /**
     * Get model config.
     *
     * @param class-string $modelClass
     * @return array<string, mixed>
     */
    protected function getModelConfig(string $modelClass): array
    {
        $modelsConfig = $this->getModelsConfig();

        return $modelsConfig[$modelClass] ?? [];
    }

    /**
     * Write sitemap to storage.
     */
    protected function writeSitemap(Sitemap $sitemap): void
    {
        $path = $this->getSitemapPath();
        $storage = $this->getStorage();

        $storage->put($path, $sitemap->render());
    }

    /**
     * Write sitemap index and individual sitemaps to storage.
     */
    protected function writeSitemapIndex(SitemapIndex $index): void
    {
        $storage = $this->getStorage();

        // Write individual model sitemaps, sharding large models into the same
        // numbered part filenames the index references (sitemapPartFilenames()).
        $modelsConfig = $this->getModelsConfig();

        foreach ($modelsConfig as $modelClass => $config) {
            $part = 0;

            foreach ($this->sitemapPartFilenames($modelClass) as $filename) {
                $part++;
                $sitemap = $this->buildModelSitemapPart($modelClass, $config, $part);

                $storage->put($filename, $sitemap->render());
            }
        }

        // Write registered named sources. A model-class source is sharded into
        // the same numbered part filenames the index references
        // (registeredSourceFilenames()), so a large registered source can never
        // truncate after the first file. Closure/iterable sources are a single
        // file.
        foreach ($this->registry->names() as $name) {
            $part = 0;

            foreach ($this->registeredSourceFilenames($name) as $filename) {
                $part++;

                $storage->put(
                    $filename,
                    $this->buildSourceSitemap($name, $part)->render()
                );
            }
        }

        // Write static URLs as their own file when configured
        $staticUrls = $this->staticUrls();

        if ($staticUrls !== []) {
            $storage->put('sitemap-static.xml', $this->buildStaticSitemap($staticUrls)->render());
        }

        // Write index
        $indexPath = $this->getSitemapPath('index');
        $storage->put($indexPath, $index->render());
    }

    /**
     * Get the sitemap path.
     */
    protected function getSitemapPath(string $type = 'main'): string
    {
        if ($type === 'index') {
            return config('seo.sitemap.path', 'sitemap.xml');
        }

        return config('seo.sitemap.path', 'sitemap.xml');
    }

    /**
     * Get the storage disk.
     */
    protected function getStorage(): FilesystemAdapter
    {
        $disk = config('seo.sitemap.disk', 'public');

        return Storage::disk($disk);
    }

    /**
     * Get the public URL for the sitemap.
     */
    public function getSitemapUrl(): string
    {
        $path = $this->getSitemapPath();

        return url($path);
    }

    /**
     * Check if sitemap exists.
     */
    public function exists(): bool
    {
        $path = $this->getSitemapPath();

        return $this->getStorage()->exists($path);
    }

    /**
     * Get sitemap last modified time.
     */
    public function getLastModified(): ?\DateTime
    {
        $path = $this->getSitemapPath();
        $storage = $this->getStorage();

        if (! $storage->exists($path)) {
            return null;
        }

        $timestamp = $storage->lastModified($path);

        return (new \DateTime())->setTimestamp($timestamp);
    }

    /**
     * Delete all sitemap files.
     */
    public function delete(): void
    {
        $storage = $this->getStorage();

        // Delete main/index
        $mainPath = $this->getSitemapPath();
        if ($storage->exists($mainPath)) {
            $storage->delete($mainPath);
        }

        // Delete model sitemaps, including every numbered shard.
        $modelsConfig = $this->getModelsConfig();

        foreach (array_keys($modelsConfig) as $modelClass) {
            foreach ($this->sitemapPartFilenames($modelClass) as $filename) {
                if ($storage->exists($filename)) {
                    $storage->delete($filename);
                }
            }
        }

        // Delete registered source sitemaps, including every numbered shard a
        // model-class source produces (registeredSourceFilenames()).
        foreach ($this->registry->names() as $name) {
            foreach ($this->registeredSourceFilenames($name) as $filename) {
                if ($storage->exists($filename)) {
                    $storage->delete($filename);
                }
            }
        }

        // Delete the static-URL sitemap
        if ($storage->exists('sitemap-static.xml')) {
            $storage->delete('sitemap-static.xml');
        }
    }
}
