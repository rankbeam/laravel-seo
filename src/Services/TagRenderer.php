<?php

declare(strict_types=1);

namespace Rankbeam\Seo\Services;

use Rankbeam\Seo\Data\SEOData;

/**
 * Converts SEOData into HTML meta tags or structured arrays.
 *
 * This service is responsible for the final rendering of SEO data into
 * different output formats depending on the frontend technology used.
 *
 * ## Output Formats
 *
 * **HTML (Blade templates):**
 * Use `render()` to get a complete HTML string including all meta tags.
 *
 * **Structured Array (Vue/React):**
 * Use `toArray()` for frontend frameworks that manage their own head tags.
 *
 * **Inertia.js:**
 * Use `toInertiaHead()` for Inertia's Head component format.
 *
 * ## Generated Tags
 *
 * - `<title>` - Page title
 * - `<meta name="description">` - Meta description
 * - `<meta name="robots">` - Robots directive
 * - `<link rel="canonical">` - Canonical URL
 * - `<meta property="og:*">` - Open Graph tags
 * - `<meta name="twitter:*">` - Twitter Card tags
 * - `<link rel="alternate" hreflang="*">` - Multilingual alternates
 * - `<script type="application/ld+json">` - JSON-LD schema
 *
 * ## Usage
 *
 * ```php
 * // In Blade
 * {!! $tagRenderer->render($seoData) !!}
 *
 * // For Vue/React via Inertia
 * return Inertia::render('Page', [
 *     'seo' => $tagRenderer->toArray($seoData),
 * ]);
 * ```
 *
 * @see \Rankbeam\Seo\Data\SEOData For the input data structure
 * @see \Rankbeam\Seo\Services\SEOResolver For resolving SEO data
 */
class TagRenderer
{
    /**
     * json_encode flags for JSON-LD output.
     *
     * The JSON-LD payload is emitted inside a <script> element, where
     * HTML entity escaping is not applied. JSON_HEX_TAG encodes `<` and
     * `>` as unicode escape sequences, so a value containing `</script>`
     * can never terminate the script element early (XSS). The other JSON_HEX_*
     * flags harden the payload for any attribute/HTML context it may
     * be echoed into by consumers of toArray().
     */
    protected const SCHEMA_JSON_FLAGS = JSON_UNESCAPED_SLASHES
        | JSON_UNESCAPED_UNICODE
        | JSON_HEX_TAG
        | JSON_HEX_AMP
        | JSON_HEX_APOS
        | JSON_HEX_QUOT;

    /**
     * Render SEO data as complete HTML meta tags.
     *
     * Returns a string containing all SEO-related HTML tags ready to be
     * inserted into the `<head>` section of your page.
     *
     * @param SEOData $seo The resolved SEO data
     * @return string HTML string with all meta tags
     *
     * @example
     * ```blade
     * <head>
     *     {!! $tagRenderer->render($seoData) !!}
     * </head>
     * ```
     *
     * @example Output:
     * ```html
     * <title>My Page Title | Site Name</title>
     * <meta name="description" content="Page description...">
     * <meta name="robots" content="index,follow">
     * <link rel="canonical" href="https://example.com/page">
     * <meta property="og:title" content="My Page Title">
     * <!-- ... more tags ... -->
     * ```
     */
    public function render(SEOData $seo): string
    {
        $tags = [];

        // Title tag
        if ($seo->title) {
            $tags[] = '<title>' . $this->escape($seo->title) . '</title>';
        }

        // Meta description
        if ($seo->description) {
            $tags[] = $this->metaName('description', $seo->description);
        }

        // Robots directive — emitted only when it deviates from the site
        // default (the contract forbids a redundant "index,follow").
        $robots = $this->robotsContent($seo);
        if ($robots !== null) {
            $tags[] = $this->metaName('robots', $robots);
        }

        // Canonical URL — never emit an empty tag.
        $canonical = $seo->canonical ?? $this->getCurrentUrl();
        if ($canonical !== '') {
            $tags[] = '<link rel="canonical" href="' . $this->escape($canonical) . '">';
        }

        // Open Graph meta tags
        $tags = array_merge($tags, $this->renderOpenGraph($seo));

        // Twitter Card meta tags
        $tags = array_merge($tags, $this->renderTwitterCard($seo));

        // Hreflang alternate links
        $tags = array_merge($tags, $this->renderAlternates($seo));

        // JSON-LD Schema
        $schemaTag = $this->renderSchema($seo);
        if ($schemaTag) {
            $tags[] = $schemaTag;
        }

        return implode("\n    ", $tags);
    }

    /**
     * Convert SEO data to structured array for Vue/React frontends.
     *
     * Returns an array structure that can be easily consumed by frontend
     * frameworks to build their own head management.
     *
     * @param SEOData $seo The resolved SEO data
     * @return array{
     *     title: string|null,
     *     meta: array<int, array{name?: string, property?: string, content: string}>,
     *     link: array<int, array{rel: string, href: string, hreflang?: string}>,
     *     script: array<int, array{type: string, innerHTML: string}>
     * }
     *
     * @example
     * ```php
     * $array = $tagRenderer->toArray($seoData);
     * // {
     * //     "title": "My Page",
     * //     "meta": [
     * //         {"name": "description", "content": "..."},
     * //         {"property": "og:title", "content": "My Page"}
     * //     ],
     * //     "link": [
     * //         {"rel": "canonical", "href": "https://..."}
     * //     ],
     * //     "script": [
     * //         {"type": "application/ld+json", "innerHTML": "{...}"}
     * //     ]
     * // }
     * ```
     */
    public function toArray(SEOData $seo): array
    {
        $canonical = $seo->canonical ?? $this->getCurrentUrl();

        $meta = [];
        $link = [];
        $script = [];

        // Meta description
        if ($seo->description) {
            $meta[] = ['name' => 'description', 'content' => $seo->description];
        }

        // Robots — only when it deviates from the site default.
        $robots = $this->robotsContent($seo);
        if ($robots !== null) {
            $meta[] = ['name' => 'robots', 'content' => $robots];
        }

        // Open Graph
        $meta[] = ['property' => 'og:title', 'content' => $seo->ogTitle ?? $seo->title];
        $meta[] = ['property' => 'og:description', 'content' => $seo->ogDescription ?? $seo->description];
        $meta[] = ['property' => 'og:url', 'content' => $seo->ogUrl ?? $canonical];
        $meta[] = ['property' => 'og:type', 'content' => $seo->ogType ?? 'website'];

        if ($seo->ogImage) {
            $meta[] = ['property' => 'og:image', 'content' => $seo->ogImage];
        }

        if ($seo->ogSiteName) {
            $meta[] = ['property' => 'og:site_name', 'content' => $seo->ogSiteName];
        }

        if ($seo->locale) {
            $meta[] = ['property' => 'og:locale', 'content' => str_replace('-', '_', $seo->locale)];
        }

        // Article-specific Open Graph
        if (($seo->ogType ?? 'website') === 'article') {
            if ($seo->publishedTime) {
                $meta[] = ['property' => 'article:published_time', 'content' => $seo->publishedTime->format('c')];
            }
            if ($seo->modifiedTime) {
                $meta[] = ['property' => 'article:modified_time', 'content' => $seo->modifiedTime->format('c')];
            }
            if ($seo->author) {
                $meta[] = ['property' => 'article:author', 'content' => $seo->author];
            }
            if ($seo->section) {
                $meta[] = ['property' => 'article:section', 'content' => $seo->section];
            }
            if ($seo->tags) {
                foreach ($seo->tags as $tag) {
                    $meta[] = ['property' => 'article:tag', 'content' => $tag];
                }
            }
        }

        // Twitter Card
        $meta[] = ['name' => 'twitter:card', 'content' => $seo->twitterCard ?? 'summary_large_image'];
        $meta[] = ['name' => 'twitter:title', 'content' => $seo->twitterTitle ?? $seo->ogTitle ?? $seo->title];
        $meta[] = ['name' => 'twitter:description', 'content' => $seo->twitterDescription ?? $seo->ogDescription ?? $seo->description];

        if ($seo->twitterImage ?? $seo->ogImage) {
            $meta[] = ['name' => 'twitter:image', 'content' => $seo->twitterImage ?? $seo->ogImage];
        }

        if ($seo->twitterSite) {
            $meta[] = ['name' => 'twitter:site', 'content' => $seo->twitterSite];
        }

        if ($seo->twitterCreator) {
            $meta[] = ['name' => 'twitter:creator', 'content' => $seo->twitterCreator];
        }

        // Filter out entries with empty content — the contract forbids
        // null/empty tags.
        $meta = array_filter($meta, fn ($item) => $item['content'] !== null && $item['content'] !== '');
        $meta = array_values($meta);

        // Canonical link — never emit an empty tag.
        if ($canonical !== '') {
            $link[] = ['rel' => 'canonical', 'href' => $canonical];
        }

        // Hreflang alternates — skip malformed entries (SEOData::fromArray
        // accepts unvalidated alternates, so a non-array entry or a missing
        // hreflang/href must never throw here).
        foreach ($this->normalizeAlternates($seo->alternates) as $alternate) {
            $link[] = [
                'rel' => 'alternate',
                'hreflang' => $alternate['hreflang'],
                'href' => $alternate['href'],
            ];
        }

        // JSON-LD Schema
        if ($seo->schemaJsonld) {
            $script[] = [
                'type' => 'application/ld+json',
                'innerHTML' => json_encode($seo->schemaJsonld, self::SCHEMA_JSON_FLAGS),
            ];
        }

        return [
            'title' => $seo->title,
            'meta' => $meta,
            'link' => $link,
            'script' => $script,
        ];
    }

    /**
     * Convert to Inertia Head component format.
     *
     * Returns an array optimized for Inertia.js's Head component.
     * Similar to toArray() but without the script section (handle
     * JSON-LD separately in Inertia — see the Inertia guide) and with a
     * stable `head-key` on every meta/link entry.
     *
     * Inertia dedupes head elements by their `head-key` attribute: a page
     * `<Head>` tag with the same `head-key` as a layout tag *replaces* it
     * instead of stacking a duplicate. Without it, page meta and layout meta
     * both persist across client-side visits. The key is `name ?? property`
     * for meta and `rel` for links; repeatable tags (e.g. `article:tag`,
     * hreflang `alternate`) are disambiguated so each stays uniquely keyed.
     * Bind it as `:head-key` (NOT Vue's `:key`, which is unrelated).
     *
     * @param SEOData $seo The resolved SEO data
     * @return array{title: string|null, meta: array<int, array<string, string>>, link: array<int, array<string, string>>}
     *
     * @example
     * ```php
     * // In Laravel controller
     * return Inertia::render('Blog/Post', [
     *     'post' => $post,
     *     'seo' => $tagRenderer->toInertiaHead($seoData),
     * ]);
     *
     * // In Vue component
     * <Head :title="seo.title">
     *     <meta v-for="m in seo.meta" :key="m['head-key']" :head-key="m['head-key']"
     *           :name="m.name" :property="m.property" :content="m.content" />
     *     <link v-for="l in seo.link" :key="l['head-key']" :head-key="l['head-key']"
     *           :rel="l.rel" :hreflang="l.hreflang" :href="l.href" />
     * </Head>
     * ```
     */
    public function toInertiaHead(SEOData $seo): array
    {
        $data = $this->toArray($seo);

        return [
            'title' => $data['title'],
            'meta' => $this->withHeadKeys(
                $data['meta'],
                static fn (array $m): string => $m['name'] ?? $m['property'] ?? 'meta',
            ),
            'link' => $this->withHeadKeys(
                $data['link'],
                static function (array $l): string {
                    if (($l['rel'] ?? null) === 'alternate' && isset($l['hreflang'])) {
                        return 'alternate:' . $l['hreflang'];
                    }

                    return $l['rel'] ?? 'link';
                },
            ),
        ];
    }

    /**
     * Stamp a stable, unique `head-key` onto each tag in a list.
     *
     * The base key comes from $keyFor(); identical base keys (repeatable
     * tags such as `article:tag`) are disambiguated with an incrementing
     * suffix so every entry stays uniquely keyed for Inertia's head dedup.
     *
     * @param array<int, array<string, string>> $items
     * @param callable(array<string, string>): string $keyFor
     * @return array<int, array<string, string>>
     */
    protected function withHeadKeys(array $items, callable $keyFor): array
    {
        $seen = [];

        foreach ($items as $i => $item) {
            $base = $keyFor($item);

            if (isset($seen[$base])) {
                $seen[$base]++;
                $items[$i]['head-key'] = $base . ':' . $seen[$base];
            } else {
                $seen[$base] = 0;
                $items[$i]['head-key'] = $base;
            }
        }

        return $items;
    }

    /**
     * Render only the JSON-LD schema script tag.
     *
     * Useful when you want to output the schema separately from
     * other meta tags, or when using partial rendering.
     *
     * @param SEOData $seo The resolved SEO data
     * @return string|null The script tag or null if no schema
     *
     * @example
     * ```blade
     * {{-- In body, not head --}}
     * {!! $tagRenderer->renderSchema($seoData) !!}
     * ```
     */
    public function renderSchema(SEOData $seo): ?string
    {
        if (! $seo->schemaJsonld) {
            return null;
        }

        $json = json_encode(
            $seo->schemaJsonld,
            self::SCHEMA_JSON_FLAGS | JSON_PRETTY_PRINT
        );

        // Tag the script so client-side navigation (Livewire wire:navigate)
        // can find and remove stale schema. Livewire treats <script> as a
        // non-removable asset, so without a marker + per-URL id the JSON-LD
        // from every visited page accumulates in the head. See the Livewire
        // guide for the livewire:navigated cleanup snippet.
        $attributes = 'type="application/ld+json" data-seo-schema';

        $url = $seo->canonical ?? $this->getCurrentUrl();
        if ($url !== '') {
            $attributes .= ' data-seo-url="' . $this->escape($url) . '"';
        }

        return '<script ' . $attributes . '>' . $json . '</script>';
    }

    /**
     * Resolve the robots directive to emit, or null to suppress it.
     *
     * The contract forbids a redundant `index,follow`: the robots meta is
     * emitted only when the resolved directive deviates from the site
     * default (`seo.default_robots`). Comparison ignores formatting
     * (whitespace around commas), but a deviating directive is emitted
     * verbatim so an admin-entered `noindex, follow` renders exactly as
     * typed. Set `seo.robots.emit_default = true` to always emit.
     *
     * Advanced directives (noindex, nofollow, noarchive, nosnippet,
     * max-snippet, max-image-preview, max-video-preview, notranslate,
     * unavailable_after) are supported as resolved string values; their
     * precedence (global → route → model → explicit) is the resolver chain.
     *
     * @param SEOData $seo The resolved SEO data
     * @return string|null The directive to emit, or null to suppress it
     */
    protected function robotsContent(SEOData $seo): ?string
    {
        $default = (string) config('seo.default_robots', 'index,follow');
        $value = $seo->robots;

        // No directive resolved at any layer ⇒ the page carries the site
        // default, which we suppress unless explicitly asked to emit it.
        if ($value === null || $value === '') {
            $value = $default;
        }

        if ((bool) config('seo.robots.emit_default', false)) {
            return $value;
        }

        if ($this->normalizeRobots($value) === $this->normalizeRobots($default)) {
            return null;
        }

        return $value;
    }

    /**
     * Normalize a robots directive for comparison only.
     *
     * Trims each comma-separated token and drops empties so semantically
     * identical directives (`index, follow` vs `index,follow`) compare
     * equal. Order and case are preserved — only the comparison is
     * normalized; the emitted value is always the original verbatim string.
     *
     * @param string $robots The robots directive
     * @return string Normalized form for equality comparison
     */
    protected function normalizeRobots(string $robots): string
    {
        $parts = array_filter(
            array_map('trim', explode(',', $robots)),
            static fn (string $p): bool => $p !== '',
        );

        return implode(',', $parts);
    }

    /**
     * Render Open Graph meta tags.
     *
     * @param SEOData $seo The resolved SEO data
     * @return array<int, string> Array of HTML meta tag strings
     */
    protected function renderOpenGraph(SEOData $seo): array
    {
        $tags = [];
        $canonical = $seo->canonical ?? $this->getCurrentUrl();

        // Core Open Graph properties
        $properties = [
            'og:title' => $seo->ogTitle ?? $seo->title,
            'og:description' => $seo->ogDescription ?? $seo->description,
            'og:image' => $seo->ogImage,
            'og:url' => $seo->ogUrl ?? $canonical,
            'og:type' => $seo->ogType ?? 'website',
            'og:site_name' => $seo->ogSiteName,
            'og:locale' => $seo->locale ? str_replace('-', '_', $seo->locale) : null,
        ];

        // Article-specific properties
        if (($seo->ogType ?? 'website') === 'article') {
            if ($seo->publishedTime) {
                $properties['article:published_time'] = $seo->publishedTime->format('c');
            }
            if ($seo->modifiedTime) {
                $properties['article:modified_time'] = $seo->modifiedTime->format('c');
            }
            if ($seo->author) {
                $properties['article:author'] = $seo->author;
            }
            if ($seo->section) {
                $properties['article:section'] = $seo->section;
            }

            // Article tags (multiple values)
            if ($seo->tags) {
                foreach ($seo->tags as $tag) {
                    $tags[] = $this->metaProperty('article:tag', $tag);
                }
            }
        }

        // Render all non-null properties
        foreach ($properties as $property => $content) {
            if ($content !== null && $content !== '') {
                $tags[] = $this->metaProperty($property, (string) $content);
            }
        }

        return $tags;
    }

    /**
     * Render Twitter Card meta tags.
     *
     * @param SEOData $seo The resolved SEO data
     * @return array<int, string> Array of HTML meta tag strings
     */
    protected function renderTwitterCard(SEOData $seo): array
    {
        $tags = [];

        $names = [
            'twitter:card' => $seo->twitterCard ?? 'summary_large_image',
            'twitter:title' => $seo->twitterTitle ?? $seo->ogTitle ?? $seo->title,
            'twitter:description' => $seo->twitterDescription ?? $seo->ogDescription ?? $seo->description,
            'twitter:image' => $seo->twitterImage ?? $seo->ogImage,
            'twitter:site' => $seo->twitterSite,
            'twitter:creator' => $seo->twitterCreator,
        ];

        foreach ($names as $name => $content) {
            if ($content !== null && $content !== '') {
                $tags[] = $this->metaName($name, $content);
            }
        }

        return $tags;
    }

    /**
     * Render hreflang alternate link tags.
     *
     * @param SEOData $seo The resolved SEO data
     * @return array<int, string> Array of HTML link tag strings
     */
    protected function renderAlternates(SEOData $seo): array
    {
        $tags = [];

        foreach ($this->normalizeAlternates($seo->alternates) as $alternate) {
            $hreflang = $this->escape($alternate['hreflang']);
            $href = $this->escape($alternate['href']);
            $tags[] = '<link rel="alternate" hreflang="' . $hreflang . '" href="' . $href . '">';
        }

        return $tags;
    }

    /**
     * Normalize the hreflang alternates list, dropping any malformed entry.
     *
     * SEOData::fromArray() accepts an arbitrary alternates value, so the
     * renderers must tolerate a non-array list, non-array entries, and entries
     * missing a string hreflang/href instead of throwing a TypeError on
     * string-offset access. Mirrors the normalize-and-skip guard the sitemap
     * builder applies to the same data.
     *
     * @param  mixed  $alternates
     * @return array<int, array{hreflang: string, href: string}>
     */
    protected function normalizeAlternates(mixed $alternates): array
    {
        if (! is_array($alternates) || $alternates === []) {
            return [];
        }

        $valid = [];

        foreach ($alternates as $alternate) {
            if (! is_array($alternate)) {
                continue;
            }

            $hreflang = $alternate['hreflang'] ?? null;
            $href = $alternate['href'] ?? null;

            if (is_string($hreflang) && $hreflang !== '' && is_string($href) && $href !== '') {
                $valid[] = ['hreflang' => $hreflang, 'href' => $href];
            }
        }

        return $valid;
    }

    /**
     * Create a meta tag with name attribute.
     *
     * @param string $name The meta name
     * @param string $content The meta content
     * @return string HTML meta tag
     */
    protected function metaName(string $name, string $content): string
    {
        return '<meta name="' . $name . '" content="' . $this->escape($content) . '">';
    }

    /**
     * Create a meta tag with property attribute.
     *
     * @param string $property The meta property (og:*, article:*, etc.)
     * @param string $content The meta content
     * @return string HTML meta tag
     */
    protected function metaProperty(string $property, string $content): string
    {
        return '<meta property="' . $property . '" content="' . $this->escape($content) . '">';
    }

    /**
     * Escape HTML entities for safe output.
     *
     * @param string $value The value to escape
     * @return string Escaped value
     */
    protected function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }

    /**
     * Get the current request URL.
     *
     * @return string Current URL or empty string if not available
     */
    protected function getCurrentUrl(): string
    {
        try {
            return url()->current();
        } catch (\Exception) {
            return '';
        }
    }

    /**
     * Get the current locale.
     *
     * @return string Current locale
     */
    protected function getCurrentLocale(): string
    {
        try {
            return app()->getLocale();
        } catch (\Exception) {
            return 'en';
        }
    }
}
