<?php

declare(strict_types=1);

namespace Fibonoir\LaravelSEO\Traits;

use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Support\Str;
use Fibonoir\LaravelSEO\Data\SEOData;
use Fibonoir\LaravelSEO\Jobs\AnalyzeContentJob;
use Fibonoir\LaravelSEO\Models\SEOMeta;
use Fibonoir\LaravelSEO\Services\InternalLinks\LinkIndexBuilder;
use Fibonoir\LaravelSEO\Services\SEOResolver;

/**
 * Add SEO capabilities to any Eloquent model.
 *
 * This trait provides automatic SEO metadata management, including:
 * - Automatic creation of SEO records when models are created
 * - Automatic re-analysis when content fields change
 * - Computed fallbacks for title, description, and image
 * - Integration with the SEOResolver precedence chain
 *
 * ## Basic Usage
 *
 * ```php
 * use Fibonoir\LaravelSEO\Traits\HasSEO;
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
 * **On Model Save (when content fields change):**
 * - Dispatches AnalyzeContentJob with a 5-second delay
 * - The delay prevents excessive analysis during bulk operations
 *
 * **On Model Deletion:**
 * - Automatically deletes the associated SEOMeta record
 *
 * @see \Fibonoir\LaravelSEO\Models\SEOMeta For the SEO metadata model
 * @see \Fibonoir\LaravelSEO\Services\SEOResolver For the precedence chain
 * @see \Fibonoir\LaravelSEO\Data\SEOData For the data structure
 * @see \Fibonoir\LaravelSEO\Jobs\AnalyzeContentJob For content analysis
 *
 * @method static static|\Illuminate\Database\Eloquent\Builder|\Illuminate\Database\Query\Builder whereSeoScore(int $score)
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
     * - saved: Triggers re-analysis if content fields changed
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
        | Re-Analyze When Content Changes
        |------------------------------------------------------------------
        |
        | When content fields are modified, we dispatch an analysis job
        | with a 5-second delay. The delay:
        | - Prevents analysis during rapid successive saves
        | - Allows time for related data to be saved (tags, categories)
        | - Reduces server load during bulk imports
        |
        */
        static::saved(function (self $model) {
            $contentFields = $model->getSEOContentFields();
            $contentChanged = $model->wasChanged($contentFields);

            // Auto-analyze if enabled and content changed
            if (config('seo.features.auto_analyze', true) && $contentChanged) {
                AnalyzeContentJob::dispatch(get_class($model), $model->getKey())
                    ->delay(now()->addSeconds(5));
            }

            // Update internal links index if enabled and content changed
            if (config('seo.features.internal_links_index', true) && $contentChanged) {
                try {
                    app(LinkIndexBuilder::class)->updateIndex($model);
                } catch (\Exception $e) {
                    // Silently fail - index update is not critical
                    \Illuminate\Support\Facades\Log::debug('Failed to update link index', [
                        'model' => get_class($model),
                        'id' => $model->getKey(),
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        });

        /*
        |------------------------------------------------------------------
        | Clean Up on Model Deletion
        |------------------------------------------------------------------
        |
        | When the model is deleted, we also delete its SEO metadata
        | and remove it from the internal links index.
        |
        */
        static::deleted(function (self $model) {
            // Delete SEO meta
            $model->seoMeta()->delete();

            // Remove from internal links index
            if (config('seo.features.internal_links_index', true)) {
                try {
                    app(LinkIndexBuilder::class)->deleteFromIndex($model);
                } catch (\Exception $e) {
                    // Silently fail - cleanup is not critical
                    \Illuminate\Support\Facades\Log::debug('Failed to remove from link index', [
                        'model' => get_class($model),
                        'id' => $model->getKey(),
                        'error' => $e->getMessage(),
                    ]);
                }
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
     * $post->seoMeta->seo_score;
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
     * Get the SEO score for this model.
     *
     * @return int|null Score from 0-100, or null if not analyzed
     */
    public function getSEOScore(): ?int
    {
        return $this->seoMeta?->seo_score;
    }

    /**
     * Get the SEO analysis report.
     *
     * @return array<string, mixed>|null The analysis report or null
     */
    public function getSEOAnalysisReport(): ?array
    {
        return $this->seoMeta?->analysis_report;
    }

    /**
     * Check if the model needs SEO analysis.
     *
     * Returns true if:
     * - Never analyzed
     * - Analysis is older than 7 days
     * - Content has changed since last analysis
     *
     * @return bool True if analysis is needed
     */
    public function needsSEOAnalysis(): bool
    {
        $meta = $this->seoMeta;

        if (! $meta || ! $meta->analyzed_at) {
            return true;
        }

        // Re-analyze if older than 7 days
        if ($meta->analyzed_at->lt(now()->subDays(7))) {
            return true;
        }

        // Re-analyze if content changed
        if ($meta->content_hash) {
            $currentHash = md5($this->getContentForSEO());

            if ($currentHash !== $meta->content_hash) {
                return true;
            }
        }

        return false;
    }

    /**
     * Trigger SEO analysis manually.
     *
     * Runs the analysis synchronously (blocking).
     * Use dispatchAnalysis() for async processing.
     *
     * @return void
     *
     * @example
     * ```php
     * // Analyze immediately (blocking)
     * $post->analyzeForSEO();
     *
     * // Check the results
     * $score = $post->fresh()->getSEOScore();
     * ```
     */
    public function analyzeForSEO(): void
    {
        AnalyzeContentJob::dispatchSync(get_class($this), $this->getKey());

        // Refresh the model to get updated SEO data
        $this->unsetRelation('seoMeta');
    }

    /**
     * Dispatch SEO analysis as a background job.
     *
     * @param int $delay Delay in seconds before running
     * @return void
     *
     * @example
     * ```php
     * // Queue for background analysis
     * $post->dispatchAnalysis();
     *
     * // With delay
     * $post->dispatchAnalysis(30); // Analyze in 30 seconds
     * ```
     */
    public function dispatchAnalysis(int $delay = 0): void
    {
        $job = AnalyzeContentJob::dispatch(get_class($this), $this->getKey());

        if ($delay > 0) {
            $job->delay(now()->addSeconds($delay));
        }
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

    /**
     * Scope to query models with low SEO scores.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param int $threshold Score threshold (default 50)
     * @return \Illuminate\Database\Eloquent\Builder
     *
     * @example
     * ```php
     * // Get posts with poor SEO
     * $poorSEO = Post::withLowSEOScore(40)->get();
     * ```
     */
    public function scopeWithLowSEOScore($query, int $threshold = 50)
    {
        return $query->whereHas('seoMeta', function ($q) use ($threshold) {
            $q->where('seo_score', '<', $threshold);
        });
    }

    /**
     * Scope to query models needing SEO analysis.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     *
     * @example
     * ```php
     * // Get posts needing analysis
     * $needsAnalysis = Post::needingSEOAnalysis()->get();
     * ```
     */
    public function scopeNeedingSEOAnalysis($query)
    {
        return $query->whereDoesntHave('seoMeta', function ($q) {
            $q->whereNotNull('analyzed_at')
                ->where('analyzed_at', '>=', now()->subDays(7));
        });
    }
}
