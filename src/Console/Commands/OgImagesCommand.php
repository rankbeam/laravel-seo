<?php

declare(strict_types=1);

namespace Rankbeam\Seo\Console\Commands;

use Illuminate\Support\Facades\Storage;
use Rankbeam\Seo\Console\LocalizedCommand as Command;
use Rankbeam\Seo\Data\SEOData;
use Rankbeam\Seo\I18n\Script;
use Rankbeam\Seo\Services\OgImage\FontProbe;
use Rankbeam\Seo\Services\OgImage\OgImageGenerator;
use Rankbeam\Seo\Services\OgImage\OgImageManager;
use Rankbeam\Seo\Services\OgImage\OgImageRenderException;
use Rankbeam\Seo\Services\SEOResolver;

/**
 * Pre-generates Open Graph images for your models so the resolver can serve
 * them without ever rendering a browser on a web request.
 *
 * Reuses the sitemap's content sources (seo.sitemap.models) by default — the
 * same "share the sitemap sources" posture as seo:llms-txt — or a dedicated
 * seo.og_image.models list, or an explicit --model. Because images are keyed by
 * a content hash, changing a title yields a NEW image (the page falls back to
 * the static default until re-warmed); --prune deletes the now-orphaned files.
 *
 * ## Usage
 * ```bash
 * php artisan seo:og-images                         # warm configured models
 * php artisan seo:og-images --model="App\Models\Post"
 * php artisan seo:og-images --force                 # re-render existing images
 * php artisan seo:og-images --prune                 # + delete orphaned images
 * ```
 *
 * ## Scheduling (keeps images fresh as content changes)
 * ```php
 * Schedule::command('seo:og-images --prune')->daily();
 * ```
 */
class OgImagesCommand extends Command
{
    protected $signature = 'seo:og-images
                            {--model=* : Model class(es) to warm (defaults to seo.og_image.models or seo.sitemap.models)}
                            {--force : Re-render images that already exist}
                            {--prune : Delete stored images that no longer match any model\'s current content}';

    protected $description = 'Pre-generate Open Graph images for your models';

    /**
     * Scripts already checked against the host's fonts this run.
     *
     * @var array<string, bool>
     */
    protected array $fontChecked = [];

    public function handle(SEOResolver $resolver, OgImageGenerator $generator, OgImageManager $manager, FontProbe $fonts): int
    {
        if (! config('seo.og_image.enabled', false)) {
            $this->error('OG-image generation is disabled. Set seo.og_image.enabled=true (and install spatie/browsershot).');

            return self::FAILURE;
        }

        $models = $this->modelClasses();

        if ($models === []) {
            $this->warn('No models configured. Set seo.og_image.models or seo.sitemap.models, or pass --model="App\\Models\\Post".');

            return self::FAILURE;
        }

        $force = (bool) $this->option('force');
        $generated = 0;
        $skipped = 0;
        $failed = 0;
        $keep = [];
        $start = microtime(true);

        foreach ($models as $modelClass) {
            if (! class_exists($modelClass)) {
                $this->warn("Skipping unknown model [{$modelClass}].");

                continue;
            }

            $this->line("Warming <info>{$modelClass}</info> ...");

            foreach ($modelClass::query()->cursor() as $model) {
                // Resolve through the model's own seoData() when it has one:
                // a HasSEO model may default to ITS locale (a translation row
                // that is Italian whatever the console locale is), and the
                // card's filename is a hash that includes the locale — warmed
                // under the wrong one, the page never finds it and falls back
                // to the static default. Identical to resolve($model) for a
                // model that does not override the default.
                $data = method_exists($model, 'seoData') ? $model->seoData() : $resolver->resolve($model);
                $template = $manager->templateFor($model);
                $keep[$this->relativePath($generator, $data, $template)] = true;

                $this->warnOnMissingFont($fonts, $data);

                try {
                    $url = $generator->generate($data, $template, $force, throwOnError: true);
                    $url === null ? $skipped++ : $generated++;
                } catch (OgImageRenderException $e) {
                    $failed++;
                    $key = method_exists($model, 'getKey') ? $model->getKey() : '?';
                    $this->warn("  failed {$modelClass}#{$key}: {$e->getMessage()}");
                }
            }
        }

        $pruned = null;
        if ($this->option('prune')) {
            if ($this->modelOptionProvided()) {
                // A scoped run's keep-set only covers the given models, so
                // pruning would delete every OTHER model's cards. Refuse it.
                $this->warn('--prune ignored with --model (it would delete other models\' cards). Run without --model to prune.');
            } else {
                $pruned = $this->prune(array_keys($keep));
            }
        }

        $duration = round(microtime(true) - $start, 2);
        $this->newLine();
        $summary = "✓ OG images: {$generated} generated, {$skipped} skipped (no title), {$failed} failed";
        if ($pruned !== null) {
            $summary .= ", {$pruned} pruned";
        }
        $this->info($summary.'.');
        $this->line("  Time: {$duration}s");

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }

    /**
     * Warn once per script when the host has no font for the title's script —
     * the render would succeed and ship a card full of boxes. Silent where
     * the host cannot say (no fontconfig) and for the bundled scripts.
     */
    protected function warnOnMissingFont(FontProbe $fonts, SEOData $data): void
    {
        foreach (Script::present(($data->ogTitle ?? $data->title ?? '').' '.($data->ogSiteName ?? '').' '.($data->ogDescription ?? $data->description ?? '')) as $script) {
            if (isset($this->fontChecked[$script])) {
                continue;
            }
            $this->fontChecked[$script] = true;
            if ($fonts->covers($script) === false) {
                $this->warn("  No installed font covers {$script} text — its cards may render as boxes. Install one: {$fonts->installHint($script)}");
            }
        }
    }

    /**
     * Whether an explicit --model was passed (a scoped run).
     */
    protected function modelOptionProvided(): bool
    {
        return array_values(array_filter((array) $this->option('model'))) !== [];
    }

    /**
     * The model classes to warm: --model wins, then seo.og_image.models, then
     * the sitemap's models. Accepts both the list and the map config shapes.
     *
     * @return array<int, class-string>
     */
    protected function modelClasses(): array
    {
        if ($this->modelOptionProvided()) {
            return array_values(array_filter((array) $this->option('model')));
        }

        $configured = config('seo.og_image.models');

        if (empty($configured)) {
            $configured = config('seo.sitemap.models', []);
        }

        $classes = [];
        foreach ((array) $configured as $key => $value) {
            $classes[] = is_int($key) ? $value : $key;
        }

        return array_values(array_unique(array_filter($classes)));
    }

    protected function relativePath(OgImageGenerator $generator, SEOData $data, ?string $template = null): string
    {
        $prefix = trim((string) config('seo.og_image.path', 'og-images'), '/');

        return ($prefix !== '' ? $prefix.'/' : '').$generator->cacheKey($data, $template).'.png';
    }

    /**
     * Delete stored images under the configured path that don't correspond to
     * any current model's content hash.
     *
     * @param  array<int, string>  $keep  Relative paths to preserve.
     */
    protected function prune(array $keep): int
    {
        $disk = Storage::disk((string) config('seo.og_image.disk', 'public'));
        $prefix = trim((string) config('seo.og_image.path', 'og-images'), '/');
        $keep = array_flip($keep);
        $deleted = 0;

        foreach ($disk->files($prefix) as $file) {
            // Only ever delete files that look like our own generated cards (a
            // sha256 hash + .png), never a user's other assets that happen to
            // share the directory.
            if (! isset($keep[$file]) && preg_match('/^[0-9a-f]{64}\.png$/', basename($file))) {
                $disk->delete($file);
                $deleted++;
            }
        }

        return $deleted;
    }
}
