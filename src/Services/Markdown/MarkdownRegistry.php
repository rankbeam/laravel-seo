<?php

declare(strict_types=1);

namespace Rankbeam\Seo\Services\Markdown;

use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Rankbeam\Seo\Traits\HasSEO;

/**
 * Resolves the markdown representation of a request, for the markdown-for-bots
 * content negotiation.
 *
 * Resolution order for the current request:
 *
 *  1. A source registered by route name via {@see register()} — a closure
 *     `fn (Request $request): ?string` or a literal markdown string.
 *  2. A route-bound model that provides its own markdown via a
 *     `toSeoMarkdown(): ?string` method ({@see \Rankbeam\Seo\Contracts\ProvidesSeoMarkdown}).
 *  3. A built fallback for a route-bound HasSEO model — the resolved title and
 *     description plus the model's `getContentForSEO()` — when
 *     `seo.markdown_for_bots.build_from_content` is on.
 *
 * When nothing resolves, the middleware leaves the normal (HTML) response
 * untouched, so the feature is a pure no-op wherever there is no markdown.
 */
class MarkdownRegistry
{
    /**
     * Registered markdown sources, keyed by route name.
     *
     * @var array<string, Closure|string>
     */
    protected array $sources = [];

    /**
     * Register a markdown source for a named route.
     *
     * ```php
     * SEO::markdown()->register('posts.show', fn (Request $r) => $r->route('post')->body_md);
     * SEO::markdown()->register('about', "# About us\n\nWe build things.");
     * ```
     */
    public function register(string $routeName, Closure|string $source): static
    {
        $this->sources[$routeName] = $source;

        return $this;
    }

    public function has(string $routeName): bool
    {
        return isset($this->sources[$routeName]);
    }

    public function forget(string $routeName): static
    {
        unset($this->sources[$routeName]);

        return $this;
    }

    public function flush(): static
    {
        $this->sources = [];

        return $this;
    }

    /**
     * The markdown for the current request, or null when none is available.
     */
    public function resolveForRequest(Request $request): ?string
    {
        $route = $request->route();

        if ($route === null) {
            return null;
        }

        $name = $route->getName();

        if (is_string($name) && isset($this->sources[$name])) {
            $markdown = $this->callSource($this->sources[$name], $request);

            if (is_string($markdown) && trim($markdown) !== '') {
                return $markdown;
            }
        }

        foreach ($route->parameters() as $parameter) {
            if ($parameter instanceof Model) {
                $markdown = $this->forModel($parameter);

                if ($markdown !== null && trim($markdown) !== '') {
                    return $markdown;
                }
            }
        }

        return null;
    }

    /**
     * Resolve a registered source to a markdown string.
     */
    protected function callSource(Closure|string $source, Request $request): ?string
    {
        if ($source instanceof Closure) {
            $result = $source($request);

            return is_string($result) ? $result : null;
        }

        return $source;
    }

    /**
     * Markdown for a single model: its explicit `toSeoMarkdown()` (authoritative
     * when present) or the built fallback for a HasSEO model.
     */
    protected function forModel(Model $model): ?string
    {
        if (method_exists($model, 'toSeoMarkdown')) {
            $markdown = $model->toSeoMarkdown();

            // An explicit hook is authoritative — including a null/blank "no
            // markdown for this record" answer; we never fall back over it.
            return is_string($markdown) && trim($markdown) !== '' ? $markdown : null;
        }

        if (! config('seo.markdown_for_bots.build_from_content', true)) {
            return null;
        }

        return $this->buildFromModel($model);
    }

    /**
     * Build a basic markdown document from a HasSEO model: the resolved title as
     * an H1, the description, and the model's content.
     *
     * The content is taken from `getContentForSEO()` verbatim — if your content
     * is HTML rather than markdown, implement `toSeoMarkdown()` for a clean
     * conversion instead.
     */
    protected function buildFromModel(Model $model): ?string
    {
        if (! in_array(HasSEO::class, class_uses_recursive($model), true)) {
            return null;
        }

        $content = method_exists($model, 'getContentForSEO') ? trim((string) $model->getContentForSEO()) : '';

        if ($content === '') {
            return null;
        }

        $title = method_exists($model, 'getSEOTitle') ? $model->getSEOTitle() : null;
        $description = null;

        if (method_exists($model, 'seoData')) {
            try {
                $seo = $model->seoData();
                $title = $title ?: $seo->title;
                $description = $seo->description;
            } catch (\Throwable) {
                // Degrade to whatever title we already have.
            }
        }

        $lines = [];

        if (is_string($title) && $title !== '') {
            $lines[] = '# '.$this->oneLine($title);
        }

        if (is_string($description) && $description !== '') {
            $lines[] = '';
            $lines[] = $this->oneLine($description);
        }

        $lines[] = '';
        $lines[] = $content;

        return implode("\n", $lines)."\n";
    }

    protected function oneLine(string $value): string
    {
        return trim((string) preg_replace('/\s+/', ' ', $value));
    }
}
