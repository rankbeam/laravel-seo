<?php

declare(strict_types=1);

namespace Rankbeam\Seo\Traits;

use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Support\Str;
use Rankbeam\Seo\Data\SEOData;
use Rankbeam\Seo\Models\SEOMeta;
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
 * ## Automatic Behaviors
 *
 * **On Model Creation:**
 * - Automatically creates an SEOMeta record with the current locale
 *
 * **On Model Deletion:**
 * - Automatically deletes the associated SEOMeta record
 *
 * @see \Rankbeam\Seo\Models\SEOMeta For the SEO metadata model
 * @see \Rankbeam\Seo\Services\SEOResolver For the precedence chain
 * @see \Rankbeam\Seo\Data\SEOData For the data structure
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
     * - deleted: Cleans up SEOMeta record
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
        | Clean Up on Model Deletion
        |------------------------------------------------------------------
        |
        | When the model is deleted, we also delete its SEO metadata.
        |
        */
        static::deleted(function (self $model) {
            $model->seoMeta()->delete();
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
                'og_type' => 'website',
                'twitter_card' => 'summary_large_image',
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
     * @param string|null $locale The locale to resolve for (uses app locale if null)
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
     * @param array<string, mixed> $data SEO data to save
     * @param string|null $locale The locale to save for (uses app locale if null)
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
        return ['title', 'content', 'body', 'excerpt', 'description', 'name'];
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
