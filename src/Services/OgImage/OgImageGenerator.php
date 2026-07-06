<?php

declare(strict_types=1);

namespace Rankbeam\Seo\Services\OgImage;

use Composer\InstalledVersions;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\View;
use Rankbeam\Seo\Data\SEOData;
use Throwable;

/**
 * Turns a resolved {@see SEOData} into a stored 1200x630 OG image and the
 * public URL that points at it.
 *
 * The image is keyed by a hash of everything that affects its pixels — title,
 * site name, template, driver, dimensions, brand colors, a manual cache_version
 * AND the installed package version — so it is generated once and busts on both
 * a template/brand change and a package upgrade (dual-trigger invalidation).
 *
 * Two entry points with different costs:
 *  - urlFor()   is cheap and side-effect-free: it returns the URL only if the
 *               file already exists, so a web request never spawns a browser and
 *               a page never links a not-yet-generated (404) image.
 *  - generate() renders (via the configured driver), stores, and returns the
 *               URL. It is what the `seo:og-images` warm command and any
 *               model-update hook call. It fails open: any render error returns
 *               null so the caller falls back to the static default_og_image.
 */
class OgImageGenerator
{
    protected ?string $fontDataUri = null;

    public function __construct(protected OgImageManager $manager) {}

    public function enabled(): bool
    {
        return (bool) config('seo.og_image.enabled', false);
    }

    /**
     * The public URL for this content's generated image IF it already exists.
     * Cheap and side-effect-free — safe to call on every web request.
     */
    public function urlFor(SEOData $data): ?string
    {
        if (! $this->enabled() || ! $this->hasRenderableTitle($data)) {
            return null;
        }

        // Fail open: a misconfigured disk (unknown name, or a driver that can't
        // produce URLs) must never bubble a 500 into a page render — it just
        // means no generated card, so the static default_og_image stands.
        try {
            $disk = $this->disk();
            $path = $this->storagePath($data);

            return $disk->exists($path) ? $disk->url($path) : null;
        } catch (Throwable $e) {
            report($e);

            return null;
        }
    }

    /**
     * Render (if needed) and store the image, returning its public URL, or
     * null if generation is disabled, there is no title, or the render failed.
     *
     * @param  bool  $force  Re-render even if a cached file exists.
     * @param  bool  $throwOnError  Re-throw render failures (the warm command
     *                              wants them surfaced) instead of failing open.
     *
     * @throws OgImageRenderException Only when $throwOnError is true.
     */
    public function generate(SEOData $data, bool $force = false, bool $throwOnError = false): ?string
    {
        if (! $this->enabled() || ! $this->hasRenderableTitle($data)) {
            return null;
        }

        $disk = $this->disk();
        $path = $this->storagePath($data);

        if (! $force && $disk->exists($path)) {
            return $disk->url($path);
        }

        try {
            $bytes = $this->manager->driver()->render(
                $this->renderHtml($data),
                (int) config('seo.og_image.width', 1200),
                (int) config('seo.og_image.height', 630),
            );

            $disk->put($path, $bytes);
        } catch (Throwable $e) {
            if ($throwOnError) {
                throw $e instanceof OgImageRenderException
                    ? $e
                    : new OgImageRenderException($e->getMessage(), 0, $e);
            }

            // Fail open — the page keeps its static default_og_image.
            report($e);

            return null;
        }

        return $disk->url($path);
    }

    /**
     * Delete this content's stored image, if any. Used when regenerating.
     */
    public function forget(SEOData $data): void
    {
        $disk = $this->disk();
        $path = $this->storagePath($data);

        if ($disk->exists($path)) {
            $disk->delete($path);
        }
    }

    /**
     * The content hash the stored filename is derived from. Public so tests and
     * tooling can assert stability/independence of individual inputs.
     */
    public function cacheKey(SEOData $data): string
    {
        return hash('sha256', (string) json_encode([
            'title' => $this->title($data),
            'site' => $data->ogSiteName,
            'locale' => $data->locale,
            'template' => config('seo.og_image.template', 'seo::og.default'),
            'driver' => config('seo.og_image.driver', 'browsershot'),
            'width' => (int) config('seo.og_image.width', 1200),
            'height' => (int) config('seo.og_image.height', 630),
            'gradient_from' => config('seo.og_image.gradient_from'),
            'gradient_to' => config('seo.og_image.gradient_to'),
            'cache_version' => config('seo.og_image.cache_version', 1),
            'package' => $this->packageVersion(),
        ], JSON_UNESCAPED_UNICODE));
    }

    /**
     * Render the configured Blade template to a self-contained HTML string.
     */
    protected function renderHtml(SEOData $data): string
    {
        return View::make(
            (string) config('seo.og_image.template', 'seo::og.default'),
            [
                'title' => $this->title($data),
                'siteName' => $data->ogSiteName,
                'fontDataUri' => $this->fontDataUri(),
                'gradientFrom' => (string) config('seo.og_image.gradient_from', '#1e2a5a'),
                'gradientTo' => (string) config('seo.og_image.gradient_to', '#3D5AFE'),
                'width' => (int) config('seo.og_image.width', 1200),
                'height' => (int) config('seo.og_image.height', 630),
                'locale' => $data->locale,
            ]
        )->render();
    }

    /**
     * The og:image title — the OG title if set, else the page title.
     */
    protected function title(SEOData $data): ?string
    {
        return $data->ogTitle ?? $data->title;
    }

    protected function hasRenderableTitle(SEOData $data): bool
    {
        return filled($this->title($data));
    }

    protected function storagePath(SEOData $data): string
    {
        $prefix = trim((string) config('seo.og_image.path', 'og-images'), '/');

        return ($prefix !== '' ? $prefix.'/' : '').$this->cacheKey($data).'.png';
    }

    protected function disk(): Filesystem
    {
        return Storage::disk((string) config('seo.og_image.disk', 'public'));
    }

    /**
     * The bundled OFL font as a base64 data URI, so the rendered HTML is
     * self-contained for a headless browser. Cached for the request.
     */
    protected function fontDataUri(): string
    {
        if ($this->fontDataUri !== null) {
            return $this->fontDataUri;
        }

        $path = __DIR__.'/../../../resources/fonts/NotoSans-Bold.ttf';
        $bytes = is_file($path) ? @file_get_contents($path) : false;

        return $this->fontDataUri = $bytes === false || $bytes === ''
            ? '' // no bundled font -> the browser uses its own sans-serif
            : 'data:font/ttf;base64,'.base64_encode($bytes);
    }

    /**
     * The installed package version, folded into the cache key so an upgrade
     * busts every generated image. Falls back to 'dev' when unavailable.
     */
    protected function packageVersion(): string
    {
        if (class_exists(InstalledVersions::class)) {
            try {
                return (string) InstalledVersions::getPrettyVersion('rankbeam/laravel-seo');
            } catch (Throwable) {
                // package not registered with Composer runtime (e.g. path repo)
            }
        }

        return 'dev';
    }
}
