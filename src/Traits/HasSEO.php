<?php

declare(strict_types=1);

namespace Rankbeam\Seo\Traits;

use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Support\Str;
use Rankbeam\Seo\Data\SEOData;
use Rankbeam\Seo\Data\SEOImageCandidate;
use Rankbeam\Seo\Models\SEOMeta;
use Rankbeam\Seo\Services\SEOResolutionCache;
use Rankbeam\Seo\Services\SEOResolver;

/**
 * Add SEO capabilities to any Eloquent model.
 *
 * This trait provides automatic SEO metadata management, including:
 * - Automatic creation of SEO records when models are created
 * - Computed fallbacks for title, description, and image
 * - Integration with the SEOResolver precedence chain
 *
 * ## Basic Usage
 *
 * ```php
 * use Rankbeam\Seo\Traits\HasSEO;
 *
 * class Post extends Model
 * {
 *     use HasSEO;
 * }
 * ```
 *
 * ## Accessing SEO Data
 *
 * ```php
 * // Get fully resolved SEO data (with all defaults merged)
 * $seoData = $post->seoData();
 *
 * // Access individual properties
 * echo $seoData->title;
 * echo $seoData->description;
 *
 * // In Blade templates
 *
 * @seo($post)
 * ```
 *
 * ## Saving SEO Data
 *
 * ```php
 * // Save SEO metadata from form input
 * $post->saveSEO([
 *     'title' => 'Custom SEO Title',
 *     'description' => 'Custom meta description',
 *     'focus_keywords' => [
 *         ['keyword' => 'laravel', 'is_primary' => true],
 *     ],
 * ]);
 *
 * // Save for a specific locale
 * $post->saveSEO(['title' => 'Titre français'], 'fr');
 * ```
 *
 * ## Customizing Computed Values
 *
 * Override the getter methods in your model for custom logic:
 *
 * ```php
 * class Post extends Model
 * {
 *     use HasSEO;
 *
 *     // Custom title fallback
 *     public function getSEOTitle(): ?string
 *     {
 *         return $this->seo_title ?? $this->headline ?? $this->title;
 *     }
 *
 *     // Custom description fallback
 *     public function getSEODescription(): ?string
 *     {
 *         return $this->seo_description
 *             ?? $this->excerpt
 *             ?? Str::limit(strip_tags($this->body), 155);
 *     }
 *
 *     // Custom image fallback (e.g., using Spatie Media Library)
 *     public function getSEOImage(): ?string
 *     {
 *         return $this->getFirstMediaUrl('featured')
 *             ?: $this->hero_image
 *             ?: config('seo.default_og_image');
 *     }
 *
 *     // Custom robots directive (see "Controlling robots / indexability")
 *     public function getSEORobots(): ?string
 *     {
 *         return $this->is_published ? null : 'noindex, nofollow';
 *     }
 *
 *     // Custom content fields that trigger re-analysis
 *     public function getSEOContentFields(): array
 *     {
 *         return ['headline', 'body', 'excerpt', 'category_id'];
 *     }
 *
 *     // Custom URL generation
 *     public function getUrlForSEO(): string
 *     {
 *         return route('blog.post', ['slug' => $this->slug]);
 *     }
 * }
 * ```
 *
 * ## Controlling robots / indexability
 *
 * Per-model `noindex` is built in — there is nothing extra to install. The
 * resolver derives the robots directive (precedence high → low):
 *
 * 1. **Explicit `seo_meta.robots`** — `saveSEO(['robots' => 'noindex,follow'])`
 *    always wins.
 * 2. **A `getSEORobots(): ?string` method** on the model — return a directive
 *    (e.g. `'noindex, nofollow'`) or `null` to fall through. This hook is
 *    optional: add it only when you want one, the resolver detects it.
 * 3. **An `is_indexable` attribute** — a column or accessor. A falsy value
 *    derives `'noindex, nofollow'`; a truthy value derives `'index, follow'`.
 *
 * The rendered `<meta name="robots">` tag is **emitted only when the resolved
 * directive deviates from `seo.default_robots`** (default `index,follow`); a
 * page that is simply indexable emits no tag, which crawlers treat as
 * index,follow. A deviating directive (`noindex`, `max-snippet:-1`, …) is
 * emitted verbatim. Set `seo.robots.emit_default = true` to always render it.
 *
 * ```php
 * // Option A — let the resolver derive it from a flag:
 * Schema::table('pages', fn ($t) => $t->boolean('is_indexable')->default(true));
 *
 * // Option B — compute it from your own state:
 * public function getSEORobots(): ?string
 * {
 *     return $this->status === 'draft' ? 'noindex, nofollow' : null;
 * }
 *
 * // Option C — set it explicitly per page:
 * $page->saveSEO(['robots' => 'noindex, follow']);
 * ```
 *
 * ## Automatic Behaviors
 *
 * **On Model Creation:**
 * - Automatically creates an SEOMeta record with the current locale
 *
 * **On Model Deletion:**
 * - Automatically deletes the associated SEOMeta record
 *
 * @see SEOMeta For the SEO metadata model
 * @see SEOResolver For the precedence chain
 * @see SEOData For the data structure
 *
 * @property-read SEOMeta|null $seoMeta
 */
trait HasSEO
{
    /**
     * Boot the HasSEO trait.
     *
     * Registers model event listeners for automatic SEO management:
     * - created: Creates empty SEOMeta record
     * - saved:   Busts the resolver result cache when a content field changes
     * - deleted: Cleans up SEOMeta record (and busts the cache)
     */
    public static function bootHasSEO(): void
    {
        /*
        |------------------------------------------------------------------
        | Auto-Create SEO Meta on Model Creation
        |------------------------------------------------------------------
        |
        | When a new model is created, we automatically create an empty
        | SEOMeta record. This ensures the relationship always has data
        | and enables immediate editing in admin panels.
        |
        */
        static::created(function (self $model) {
            if (config('seo.features.auto_create_meta', true)) {
                $model->seoMeta()->create([
                    'locale' => app()->getLocale(),
                ]);
            }
        });

        /*
        |------------------------------------------------------------------
        | Bust the Resolver Cache on a Content-Field Change
        |------------------------------------------------------------------
        |
        | The resolver result cache (seo.cache.resolver.enabled) caches a
        | model's fully-resolved SEO, including values COMPUTED from its
        | content (title, excerpt, image, ...). When one of those content
        | fields changes, the cached resolution is stale, so clear this
        | model's entries. getSEOContentFields() is the model's declared set
        | (override it to widen). Inert when caching is off (the default).
        |
        */
        static::saved(function (self $model) {
            if (! config('seo.cache.resolver.enabled', false) || $model->getKey() === null) {
                return;
            }

            $fields = $model->getSEOContentFields();

            if ($fields !== [] && $model->wasChanged($fields)) {
                app(SEOResolutionCache::class)->forgetModel(static::class, $model->getKey());
            }
        });

        /*
        |------------------------------------------------------------------
        | Clean Up on Model Deletion
        |------------------------------------------------------------------
        |
        | When the model is deleted, we also delete its SEO metadata and bust
        | any cached resolution for it (deleting the seo_meta row already
        | busts via SEOMeta's own hook, but a model with no row would not).
        |
        */
        static::deleted(function (self $model) {
            $model->seoMeta()->delete();

            if (config('seo.cache.resolver.enabled', false) && $model->getKey() !== null) {
                app(SEOResolutionCache::class)->forgetModel(static::class, $model->getKey());
            }
        });
    }

    /**
     * Get the SEO metadata relationship.
     *
     * Returns a MorphOne relationship to the SEOMeta model with a
     * default value to prevent null checks.
     *
     * @return MorphOne<SEOMeta>
     *
     * @example
     * ```php
     * // Access SEO meta directly
     * $post->seoMeta->title;
     *
     * // Update via relationship
     * $post->seoMeta()->update(['title' => 'New Title']);
     * ```
     */
    public function seoMeta(): MorphOne
    {
        return $this->morphOne(SEOMeta::class, 'seoable')
            ->withDefault([
                'locale' => app()->getLocale(),
            ]);
    }

    /**
     * Get the SEO metadata relationship scoped to one locale.
     *
     * @return MorphOne<SEOMeta>
     */
    public function seoMetaForLocale(?string $locale = null): MorphOne
    {
        $locale ??= app()->getLocale();

        return $this->morphOne(SEOMeta::class, 'seoable')
            ->where('locale', $locale)
            ->withDefault([
                'locale' => $locale,
            ]);
    }

    /**
     * Get fully resolved SEO data with the complete precedence chain.
     *
     * This method returns SEOData that has been processed through
     * the SEOResolver, which means all defaults have been merged:
     *
     * 1. Global config defaults
     * 2. Model-type defaults (e.g., all Posts)
     * 3. Route defaults
     * 4. Computed values (from this trait's getter methods)
     * 5. Explicit values (from SEOMeta record)
     *
     * @param  string|null  $locale  The locale to resolve for (uses app locale if null)
     * @return SEOData Fully resolved SEO data
     *
     * @example
     * ```php
     * // Get SEO data for the current locale
     * $seo = $post->seoData();
     * echo $seo->title;       // "My Post | Site Name"
     * echo $seo->description; // From excerpt or explicit override
     * echo $seo->ogImage;     // From featured_image or default
     *
     * // Get SEO data for a specific locale
     * $seoFr = $post->seoData('fr');
     * ```
     */
    public function seoData(?string $locale = null): SEOData
    {
        return app(SEOResolver::class)->resolve(
            model: $this,
            locale: $locale ?? app()->getLocale()
        );
    }

    /**
     * Save SEO metadata for this model.
     *
     * Updates or creates an SEOMeta record with the provided data.
     * Supports all SEOMeta fields including focus keywords and schema.
     *
     * @param  array<string, mixed>  $data  SEO data to save
     * @param  string|null  $locale  The locale to save for (uses app locale if null)
     *
     * @example
     * ```php
     * // Basic save
     * $post->saveSEO([
     *     'title' => 'Custom SEO Title',
     *     'description' => 'Custom meta description',
     *     'robots' => 'noindex,follow',
     * ]);
     *
     * // With focus keywords
     * $post->saveSEO([
     *     'title' => 'Laravel SEO Guide',
     *     'focus_keywords' => [
     *         ['keyword' => 'laravel seo', 'is_primary' => true],
     *         ['keyword' => 'meta tags', 'is_primary' => false],
     *     ],
     * ]);
     *
     * // Save for a specific locale
     * $post->saveSEO(['title' => 'Titre SEO'], 'fr');
     *
     * // With Open Graph and Twitter overrides
     * $post->saveSEO([
     *     'title' => 'Page Title',
     *     'og_title' => 'Different Share Title',
     *     'og_image' => '/images/social-share.jpg',
     *     'twitter_card' => 'summary',
     * ]);
     * ```
     */
    public function saveSEO(array $data, ?string $locale = null): void
    {
        $locale = $locale ?? app()->getLocale();

        $this->seoMeta()->updateOrCreate(
            ['locale' => $locale],
            $data
        );

        // Clear the relationship cache
        $this->unsetRelation('seoMeta');
    }

    /**
     * Get the fields that trigger re-analysis when changed.
     *
     * Override this method in your model to customize which fields
     * cause the SEO analyzer to re-run when the model is saved.
     *
     * @return array<int, string> List of field names
     *
     * @example
     * ```php
     * // In your model
     * public function getSEOContentFields(): array
     * {
     *     return ['headline', 'body', 'excerpt', 'category_id', 'tags'];
     * }
     * ```
     */
    public function getSEOContentFields(): array
    {
        return [
            'title',
            'name',
            'heading',
            'headline',
            'excerpt',
            'summary',
            'description',
            'intro',
            'lead',
            'teaser',
            'content',
            'body',
            'text',
            'article',
            'featured_image',
            'image',
            'thumbnail',
            'cover_image',
            'og_image',
            'photo',
            'banner',
            'hero_image',
        ];
    }

    /**
     * Get the computed SEO title.
     *
     * This is the fallback title used when no explicit title is set
     * in SEOMeta. Override in your model for custom logic.
     *
     * @return string|null The computed title or null
     *
     * @example
     * ```php
     * // In your model
     * public function getSEOTitle(): ?string
     * {
     *     return $this->headline ?? $this->title ?? $this->name;
     * }
     * ```
     */
    public function getSEOTitle(): ?string
    {
        return $this->title ?? $this->name ?? $this->headline ?? null;
    }

    /**
     * Get the computed SEO description.
     *
     * This is the fallback description used when no explicit description
     * is set in SEOMeta. Automatically truncates to 155 characters.
     *
     * Priority:
     * 1. excerpt
     * 2. summary
     * 3. description
     * 4. intro
     * 5. content (stripped of HTML, truncated)
     * 6. body (stripped of HTML, truncated)
     *
     * @return string|null The computed description or null
     *
     * @example
     * ```php
     * // In your model (using Spatie Sluggable or similar)
     * public function getSEODescription(): ?string
     * {
     *     return $this->meta_description
     *         ?? $this->excerpt
     *         ?? Str::limit(strip_tags($this->content), 155);
     * }
     * ```
     */
    public function getSEODescription(): ?string
    {
        $fields = ['excerpt', 'summary', 'description', 'intro', 'lead', 'content', 'body', 'text'];

        foreach ($fields as $field) {
            $value = $this->getAttribute($field);

            if (! empty($value) && is_string($value)) {
                // Strip HTML tags and normalize whitespace
                $clean = strip_tags($value);
                $clean = preg_replace('/\s+/', ' ', $clean);
                $clean = trim($clean);

                return Str::limit($clean, 155);
            }
        }

        return null;
    }

    /**
     * Get the computed SEO image.
     *
     * This is the fallback image used for og:image and twitter:image
     * when no explicit image is set in SEOMeta.
     *
     * Priority:
     * 1. featured_image
     * 2. image
     * 3. thumbnail
     * 4. cover_image
     * 5. hero_image
     * 6. Default from config
     *
     * @return string|null The image URL or null
     *
     * @example
     * ```php
     * // In your model (with Spatie Media Library)
     * public function getSEOImage(): ?string
     * {
     *     // Try featured media
     *     if ($media = $this->getFirstMedia('featured')) {
     *         return $media->getUrl('og');
     *     }
     *
     *     // Try thumbnail
     *     return $this->thumbnail ?? config('seo.default_og_image');
     * }
     * ```
     */
    public function getSEOImage(): ?string
    {
        $fields = [
            'featured_image',
            'image',
            'thumbnail',
            'cover_image',
            'hero_image',
            'og_image',
            'photo',
            'banner',
        ];

        foreach ($fields as $field) {
            $value = $this->getAttribute($field);

            if (! empty($value) && is_string($value)) {
                return $value;
            }
        }

        return config('seo.default_og_image');
    }

    /**
     * Get the ordered social-image candidates for this model.
     *
     * Optional companion to {@see getSEOImage()}. When the computed-image
     * strategy is `best` (config `seo.computed.image_selection.strategy`), the
     * builder scores these candidates by how close their LOCAL pixel
     * dimensions are to the configured ideal (default 1200×630) and skips any
     * below the configured minimum (default 200×200). `getSEOImage()` remains
     * the highest-priority candidate; entries returned here fill out the
     * ordered list below it.
     *
     * Return {@see SEOImageCandidate} objects (or plain URL strings, which are
     * treated as priority 0). Core measures **local images only** — a remote
     * URL is never fetched (that is the Filament preview's client-side job), so
     * it cannot be scored or skipped for size and only acts as a fallback.
     *
     * The default is an empty list: models that do not override this are
     * unaffected, and the default `first` strategy keeps returning
     * `getSEOImage()` unchanged.
     *
     * @return iterable<int, SEOImageCandidate|string>
     *
     * @example
     * ```php
     * public function getSEOImages(): iterable
     * {
     *     return [
     *         SEOImageCandidate::make($this->hero_url)->priority(100),
     *         SEOImageCandidate::make($this->social_card_url)->priority(50),
     *         $this->thumbnail_url, // a plain string is priority 0
     *     ];
     * }
     * ```
     */
    public function getSEOImages(): iterable
    {
        return [];
    }

    /**
     * Get the hreflang alternates for this model.
     *
     * Override this method to provide absolute URLs for localized variants.
     *
     * @return array<int, array{hreflang: string, href: string}>|null
     */
    public function getSEOAlternates(): ?array
    {
        return null;
    }

    /**
     * Get the computed JSON-LD schema graph for this model.
     *
     * The composition hook for structured data. Return one or more schema.org
     * nodes (a flat list of associative arrays). It is invoked ONLY as a
     * fallback: an explicit stored `seo_meta.schema_jsonld` is authoritative —
     * when present it is emitted as-is and this hook is NOT called (no silent
     * merge). When absent, the resolver calls this hook (or the
     * `seo.schema.type_map` config mapping) to produce the graph.
     *
     * The default is an empty list, so models that do not override it render no
     * schema — behaviour is unchanged for them.
     *
     * Build the graph from the existing primitives via
     * {@see \Rankbeam\Seo\Services\Schema\SchemaGraph::for()} instead of
     * hand-rolling node arrays:
     *
     * ```php
     * use Rankbeam\Seo\Services\Schema\SchemaGraph;
     *
     * public function getSEOSchema(): array
     * {
     *     return SchemaGraph::for($this)
     *         ->organization()
     *         ->website()
     *         ->webPage()
     *         ->breadcrumbFromAncestors()
     *         ->toArray();
     * }
     * ```
     *
     * @return array<int|string, mixed> One or more schema.org nodes
     *
     * @see \Rankbeam\Seo\Services\Schema\SchemaGraph::for() For composition
     */
    public function getSEOSchema(): array
    {
        return [];
    }

    /**
     * Get the content for SEO analysis.
     *
     * Returns the main content of the model for keyword analysis,
     * readability scoring, and other SEO checks.
     *
     * @return string The content to analyze
     *
     * @example
     * ```php
     * // In your model (with multiple content fields)
     * public function getContentForSEO(): string
     * {
     *     return $this->introduction . "\n\n" . $this->body . "\n\n" . $this->conclusion;
     * }
     * ```
     */
    public function getContentForSEO(): string
    {
        return $this->content ?? $this->body ?? $this->text ?? '';
    }

    /**
     * Get the URL for this model.
     *
     * Returns the canonical URL for the model. Used for:
     * - canonical meta tag
     * - og:url
     * - sitemap generation
     *
     * @return string The model's URL
     *
     * @example
     * ```php
     * // In your model
     * public function getUrlForSEO(): string
     * {
     *     return route('posts.show', [
     *         'slug' => $this->slug,
     *         'category' => $this->category->slug,
     *     ]);
     * }
     * ```
     */
    public function getUrlForSEO(): string
    {
        // Check for explicit URL attribute
        if (! empty($this->url)) {
            return $this->url;
        }

        // Check for slug-based URL attribute
        if (! empty($this->slug)) {
            // Try common route patterns
            $modelName = Str::kebab(class_basename($this));

            foreach (["{$modelName}s.show", "{$modelName}.show", "show-{$modelName}"] as $routeName) {
                try {
                    return route($routeName, $this);
                } catch (\Exception) {
                    continue;
                }
            }

            // Try with slug parameter
            try {
                return route("{$modelName}s.show", ['slug' => $this->slug]);
            } catch (\Exception) {
                // Fall through
            }
        }

        // Fallback to current URL
        try {
            return url()->current();
        } catch (\Exception) {
            return '';
        }
    }

    /**
     * Get the focus keywords for this model.
     *
     * @return array<int, array{keyword: string, is_primary: bool, synonyms?: array<int, string>}>
     */
    public function getFocusKeywords(): array
    {
        return $this->seoMeta?->focus_keywords ?? [];
    }

    /**
     * Get the primary focus keyword.
     *
     * @return array{keyword: string, is_primary: bool, synonyms?: array<int, string>}|null
     */
    public function getPrimaryKeyword(): ?array
    {
        $keywords = $this->getFocusKeywords();

        foreach ($keywords as $keyword) {
            if ($keyword['is_primary'] ?? false) {
                return $keyword;
            }
        }

        return $keywords[0] ?? null;
    }

    /**
     * Check if SEO data has been explicitly set (not just computed).
     *
     * @return bool True if explicit SEO data exists
     */
    public function hasExplicitSEO(): bool
    {
        $meta = $this->seoMeta;

        return $meta
            && ($meta->title || $meta->description || $meta->focus_keywords);
    }
}
