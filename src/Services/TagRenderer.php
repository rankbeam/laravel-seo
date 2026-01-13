<?php

declare(strict_types=1);

namespace Fibonoir\LaravelSEO\Services;

use Fibonoir\LaravelSEO\Data\SEOData;

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
 * @see \Fibonoir\LaravelSEO\Data\SEOData For the input data structure
 * @see \Fibonoir\LaravelSEO\Services\SEOResolver For resolving SEO data
 */
class TagRenderer
{
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

        // Robots directive
        $robots = $seo->robots ?? 'index,follow';
        $tags[] = $this->metaName('robots', $robots);

        // Canonical URL
        $canonical = $seo->canonical ?? $this->getCurrentUrl();
        $tags[] = '<link rel="canonical" href="' . $this->escape($canonical) . '">';

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

        // Robots
        $meta[] = ['name' => 'robots', 'content' => $seo->robots ?? 'index,follow'];

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

        // Filter out entries with null content
        $meta = array_filter($meta, fn ($item) => $item['content'] !== null);
        $meta = array_values($meta);

        // Canonical link
        $link[] = ['rel' => 'canonical', 'href' => $canonical];

        // Hreflang alternates
        if ($seo->alternates) {
            foreach ($seo->alternates as $alternate) {
                $link[] = [
                    'rel' => 'alternate',
                    'hreflang' => $alternate['hreflang'],
                    'href' => $alternate['href'],
                ];
            }
        }

        // JSON-LD Schema
        if ($seo->schemaJsonld) {
            $script[] = [
                'type' => 'application/ld+json',
                'innerHTML' => json_encode($seo->schemaJsonld, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
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
     * JSON-LD separately in Inertia).
     *
     * @param SEOData $seo The resolved SEO data
     * @return array{title: string|null, meta: array, link: array}
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
     *     <meta v-for="meta in seo.meta" :key="meta.name || meta.property"
     *           :name="meta.name" :property="meta.property" :content="meta.content" />
     * </Head>
     * ```
     */
    public function toInertiaHead(SEOData $seo): array
    {
        $data = $this->toArray($seo);

        return [
            'title' => $data['title'],
            'meta' => $data['meta'],
            'link' => $data['link'],
        ];
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
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT
        );

        return '<script type="application/ld+json">' . $json . '</script>';
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
        if (! $seo->alternates || empty($seo->alternates)) {
            return [];
        }

        $tags = [];

        foreach ($seo->alternates as $alternate) {
            $hreflang = $this->escape($alternate['hreflang']);
            $href = $this->escape($alternate['href']);
            $tags[] = '<link rel="alternate" hreflang="' . $hreflang . '" href="' . $href . '">';
        }

        return $tags;
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
