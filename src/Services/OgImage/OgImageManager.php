<?php

declare(strict_types=1);

namespace Rankbeam\Seo\Services\OgImage;

use Closure;
use Illuminate\Contracts\Container\Container;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;
use Rankbeam\Seo\Contracts\OgImageRenderer;

/**
 * Resolves the configured OG-image renderer and lets applications register
 * their own. Mirrors the ImporterRegistry pattern: built-in drivers are keyed
 * by name and resolved lazily from the container; custom drivers are added
 * with extend().
 */
class OgImageManager
{
    /** @var array<string, Closure(Container): OgImageRenderer> */
    protected array $drivers = [];

    /** @var array<string, OgImageRenderer> */
    protected array $resolved = [];

    public function __construct(protected Container $app)
    {
        $this->drivers['browsershot'] = fn (Container $app) => $app->make(BrowsershotRenderer::class);
    }

    /**
     * Register (or override) a renderer driver.
     *
     * @param  Closure(Container): OgImageRenderer  $factory
     */
    public function extend(string $name, Closure $factory): void
    {
        $this->drivers[$name] = $factory;
        unset($this->resolved[$name]);
    }

    /**
     * Resolve a renderer by name, defaulting to the configured driver.
     */
    public function driver(?string $name = null): OgImageRenderer
    {
        $name ??= (string) config('seo.og_image.driver', 'browsershot');

        if (! isset($this->drivers[$name])) {
            throw new InvalidArgumentException("OG-image driver [{$name}] is not registered.");
        }

        return $this->resolved[$name] ??= ($this->drivers[$name])($this->app);
    }

    /**
     * The names of every registered driver.
     *
     * @return array<int, string>
     */
    public function available(): array
    {
        return array_keys($this->drivers);
    }

    /**
     * The template a model should render its OG card with.
     *
     * Precedence: the model's own getOgImageTemplate() hook (return a view name
     * to override per-instance), then the seo.og_image.templates class-name
     * map, then the configured seo.og_image.template default.
     */
    public function templateFor(?Model $model = null): string
    {
        $default = (string) config('seo.og_image.template', 'seo::og.default');

        if ($model === null) {
            return $default;
        }

        if (method_exists($model, 'getOgImageTemplate')) {
            $template = $model->getOgImageTemplate();
            if (is_string($template) && $template !== '') {
                return $template;
            }
        }

        $map = (array) config('seo.og_image.templates', []);

        return isset($map[$model::class]) ? (string) $map[$model::class] : $default;
    }
}
