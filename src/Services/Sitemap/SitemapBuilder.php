<?php

declare(strict_types=1);

namespace Fibonoir\LaravelSEO\Services\Sitemap;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Spatie\Sitemap\Sitemap;
use Spatie\Sitemap\SitemapIndex;
use Spatie\Sitemap\Tags\Url;
use Fibonoir\LaravelSEO\Contracts\Sitemapable;
use Fibonoir\LaravelSEO\Traits\HasSEO;

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

        if (empty($modelsConfig) && empty($registered)) {
            return Sitemap::create();
        }

        $totalUrls = $this->countTotalUrls($modelsConfig);
        $maxUrls = config('seo.sitemap.max_urls_per_sitemap', $this->maxUrlsPerSitemap);

        // Use index if we have registered sources (each gets its own
        // sitemap-{name}.xml), multiple models, or too many URLs.
        if (! empty($registered) || count($modelsConfig) > 1 || $totalUrls > $maxUrls) {
            return $this->buildSitemapIndex($modelsConfig);
        }

        // Single sitemap
        $modelClass = array_key_first($modelsConfig);
        $config = $modelsConfig[$modelClass];

        return $this->buildModelSitemap($modelClass, $config);
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
     * @param array<class-string, array<string, mixed>> $modelsConfig
     */
    protected function buildSitemapIndex(array $modelsConfig): SitemapIndex
    {
        $index = SitemapIndex::create();

        foreach ($modelsConfig as $modelClass => $config) {
            $slug = $this->getModelSlug($modelClass);
            $filename = "sitemap-{$slug}.xml";
            $url = url($filename);

            // Get last modified from model
            $lastMod = $this->getLastModifiedForModel($modelClass);

            $index->add($url, $lastMod);
        }

        // Registered named sources
        foreach ($this->registry->names() as $name) {
            $index->add(url("sitemap-{$name}.xml"));
        }

        return $index;
    }

    /**
     * Build a sitemap for a registered named source.
     *
     * @throws \InvalidArgumentException When the name is not registered
     */
    public function buildSourceSitemap(string $name): Sitemap
    {
        $source = $this->registry->get($name);

        // Eloquent model class: reuse the model pipeline (publish checks,
        // noindex exclusion, priority/changefreq, chunking).
        if (is_string($source)) {
            return $this->buildModelSitemap($source, []);
        }

        if ($source instanceof \Closure) {
            $source = $source();
        }

        $sitemap = Sitemap::create();
        $maxUrls = config('seo.sitemap.max_urls_per_sitemap', $this->maxUrlsPerSitemap);
        $count = 0;

        foreach ($source as $item) {
            if ($count >= $maxUrls) {
                break;
            }

            $url = $this->normalizeSourceItem($item);

            if ($url) {
                $sitemap->add($url);
                $count++;
            }
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
     * Build sitemap for a single model type.
     *
     * @param class-string $modelClass
     * @param array<string, mixed> $config
     */
    protected function buildModelSitemap(string $modelClass, array $config): Sitemap
    {
        $sitemap = Sitemap::create();
        $maxUrls = config('seo.sitemap.max_urls_per_sitemap', $this->maxUrlsPerSitemap);

        // Query the model
        $query = $modelClass::query();

        // Apply scope if model has one
        if (method_exists($modelClass, 'scopeSitemapable')) {
            $query->sitemapable();
        }

        // Order by updated_at for consistent output
        if (in_array('updated_at', (new $modelClass())->getFillable()) || (new $modelClass())->timestamps) {
            $query->orderBy('updated_at', 'desc');
        }

        $count = 0;

        $query->chunk(500, function (Collection $models) use ($sitemap, $config, &$count, $maxUrls) {
            foreach ($models as $model) {
                if ($count >= $maxUrls) {
                    return false; // Stop chunking
                }

                if (! $this->shouldInclude($model)) {
                    continue;
                }

                $url = $this->buildUrl($model, $config);

                if ($url) {
                    $sitemap->add($url);
                    $count++;
                }
            }

            return true;
        });

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

        // Check for noindex in SEO meta
        if (method_exists($model, 'seoMeta')) {
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
                return $tag;
            }

            if (is_string($tag)) {
                $tag = ['url' => $tag];
            }

            return $this->createUrlFromArray($tag, $config);
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

        return $url;
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

        // Write individual model sitemaps
        $modelsConfig = $this->getModelsConfig();

        foreach ($modelsConfig as $modelClass => $config) {
            $sitemap = $this->buildModelSitemap($modelClass, $config);
            $slug = $this->getModelSlug($modelClass);
            $filename = "sitemap-{$slug}.xml";

            $storage->put($filename, $sitemap->render());
        }

        // Write registered named sources
        foreach ($this->registry->names() as $name) {
            $storage->put(
                "sitemap-{$name}.xml",
                $this->buildSourceSitemap($name)->render()
            );
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

        // Delete model sitemaps
        $modelsConfig = $this->getModelsConfig();

        foreach (array_keys($modelsConfig) as $modelClass) {
            $slug = $this->getModelSlug($modelClass);
            $filename = "sitemap-{$slug}.xml";

            if ($storage->exists($filename)) {
                $storage->delete($filename);
            }
        }

        // Delete registered source sitemaps
        foreach ($this->registry->names() as $name) {
            $filename = "sitemap-{$name}.xml";

            if ($storage->exists($filename)) {
                $storage->delete($filename);
            }
        }
    }
}
