<?php

declare(strict_types=1);

namespace Rankbeam\Seo\Services\Sitemap;

use Closure;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

/**
 * Runtime registry of named sitemap sources.
 *
 * Lets applications register sitemap sources programmatically instead of
 * (or in addition to) the `seo.sitemap.models` config array. Each
 * registered source is rendered to its own `sitemap-{name}.xml` file and
 * referenced from the `sitemap.xml` index.
 *
 * ## Usage
 *
 * ```php
 * use Rankbeam\Seo\Facades\SEO;
 *
 * // An Eloquent model class (same handling as seo.sitemap.models)
 * SEO::sitemaps()->register('posts', Post::class);
 *
 * // A closure returning URLs (strings, arrays, or Spatie Url tags)
 * SEO::sitemaps()->register('pages', fn () => [
 *     '/about',
 *     ['url' => '/contact', 'priority' => 0.5, 'changefreq' => 'monthly'],
 *     \Spatie\Sitemap\Tags\Url::create(url('/pricing')),
 * ]);
 *
 * // A plain iterable
 * SEO::sitemaps()->register('legal', ['/privacy', '/terms']);
 * ```
 *
 * Typically called from a service provider's boot() method.
 *
 * @see \Rankbeam\Seo\Services\Sitemap\SitemapBuilder Consumes the registry
 */
class SitemapRegistry
{
    /**
     * Registered sources, keyed by sitemap name.
     *
     * @var array<string, class-string<Model>|Closure|iterable<mixed>>
     */
    protected array $sources = [];

    /**
     * Register a sitemap source under a name.
     *
     * The name becomes part of the sitemap filename (`sitemap-{name}.xml`)
     * and must match the route constraint for sitemap serving: lowercase
     * letters, digits, and dashes.
     *
     * Registering the same name twice replaces the previous source.
     *
     * @param string $name Sitemap name (e.g. 'posts', 'static-pages')
     * @param class-string<Model>|Closure|iterable<mixed> $source An Eloquent
     *        model class, a closure returning an iterable of URL items, or
     *        an iterable of URL items (string|array{url: string}|Url)
     *
     * @throws InvalidArgumentException For invalid names or string sources
     *         that are not Eloquent model classes
     */
    public function register(string $name, string|Closure|iterable $source): static
    {
        if (! preg_match('/^[a-z0-9\-]+$/', $name)) {
            throw new InvalidArgumentException(
                "Invalid sitemap name [{$name}]: only lowercase letters, digits and dashes are allowed."
            );
        }

        if (is_string($source) && ! (class_exists($source) && is_subclass_of($source, Model::class))) {
            throw new InvalidArgumentException(
                "Invalid sitemap source for [{$name}]: string sources must be an Eloquent model class."
            );
        }

        $this->sources[$name] = $source;

        return $this;
    }

    /**
     * Check whether a source is registered under the given name.
     */
    public function has(string $name): bool
    {
        return array_key_exists($name, $this->sources);
    }

    /**
     * Get the source registered under the given name.
     *
     * @return class-string<Model>|Closure|iterable<mixed>
     *
     * @throws InvalidArgumentException When the name is not registered
     */
    public function get(string $name): string|Closure|iterable
    {
        if (! $this->has($name)) {
            throw new InvalidArgumentException("No sitemap source registered under [{$name}].");
        }

        return $this->sources[$name];
    }

    /**
     * All registered sources, keyed by name.
     *
     * @return array<string, class-string<Model>|Closure|iterable<mixed>>
     */
    public function sources(): array
    {
        return $this->sources;
    }

    /**
     * All registered sitemap names.
     *
     * @return array<int, string>
     */
    public function names(): array
    {
        return array_keys($this->sources);
    }

    /**
     * Remove a registered source.
     */
    public function forget(string $name): static
    {
        unset($this->sources[$name]);

        return $this;
    }

    /**
     * Remove all registered sources.
     */
    public function flush(): static
    {
        $this->sources = [];

        return $this;
    }
}
