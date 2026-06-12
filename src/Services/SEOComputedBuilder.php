<?php

declare(strict_types=1);

namespace Rankbeam\Seo\Services;

use DateTimeInterface;
use Illuminate\Database\Eloquent\Model;
use Rankbeam\Seo\Data\SEOData;

/**
 * Builds computed SEO data from model content.
 *
 * This service extracts fallback SEO values from a model's attributes when
 * explicit SEO data hasn't been set. It provides intelligent defaults by
 * analyzing the model's content fields.
 *
 * ## Extraction Priority
 *
 * For each SEO field, the builder checks multiple sources in order:
 *
 * **Title:**
 * 1. `getSEOTitle()` method (if exists)
 * 2. Common fields: title, name, heading
 *
 * **Description:**
 * 1. `getSEODescription()` method (if exists)
 * 2. Short text fields: excerpt, summary, description, intro, lead
 * 3. Long content fields: content, body, text (auto-truncated)
 *
 * **Image:**
 * 1. `getSEOImage()` method (if exists)
 * 2. Common fields: featured_image, image, thumbnail, cover_image
 * 3. First `<img>` tag in content (fallback)
 * 4. Default from config
 *
 * **Dates:**
 * - Published: published_at, created_at, publish_date
 * - Modified: updated_at
 *
 * **Author:**
 * 1. `author` relationship (if loaded)
 * 2. `user` relationship (if loaded)
 * 3. `author_name` field
 *
 * ## Customization
 *
 * Models can override the default extraction by implementing methods:
 *
 * ```php
 * class Post extends Model
 * {
 *     public function getSEOTitle(): string
 *     {
 *         return $this->custom_seo_title ?? $this->title;
 *     }
 *
 *     public function getSEODescription(): string
 *     {
 *         return $this->custom_excerpt ?? Str::limit($this->content, 155);
 *     }
 *
 *     public function getSEOImage(): ?string
 *     {
 *         return $this->hero_image?->url ?? $this->thumbnail;
 *     }
 * }
 * ```
 *
 * @see \Rankbeam\Seo\Services\SEOResolver For how computed values are used
 * @see \Rankbeam\Seo\Traits\HasSEO For the model trait
 */
class SEOComputedBuilder
{
    /**
     * Default maximum length for computed descriptions.
     *
     * Override via config('seo.computed.description_max_length').
     */
    protected const MAX_DESCRIPTION_LENGTH = 160;

    /**
     * Minimum fraction of the max length a word boundary must reach for
     * truncation to cut at the boundary instead of mid-word.
     */
    protected const WORD_BOUNDARY_MIN_RATIO = 0.6;

    /**
     * Characters trimmed from the end of a truncated description.
     */
    protected const TRUNCATION_TRIM_CHARS = " \t\n\r\0\x0B,.;:-";

    /**
     * Common title fields to check on models.
     *
     * @var array<int, string>
     */
    protected array $titleFields = ['title', 'name', 'heading', 'headline'];

    /**
     * Common short-text fields for descriptions.
     *
     * @var array<int, string>
     */
    protected array $excerptFields = ['excerpt', 'summary', 'description', 'intro', 'lead', 'teaser'];

    /**
     * Common long-content fields for description fallback.
     *
     * @var array<int, string>
     */
    protected array $contentFields = ['content', 'body', 'text', 'article'];

    /**
     * Common image fields to check on models.
     *
     * @var array<int, string>
     */
    protected array $imageFields = [
        'featured_image',
        'image',
        'thumbnail',
        'cover_image',
        'og_image',
        'photo',
        'banner',
        'hero_image',
    ];

    /**
     * Build computed SEO data from a model.
     *
     * Extracts all possible SEO values from the model's attributes
     * and relationships. Returns an SEOData object with only the
     * values that could be computed (others remain null).
     *
     * @param Model $model The Eloquent model to extract from
     * @param string $locale The locale for multi-language support
     * @return SEOData Computed SEO data (may have many null values)
     *
     * @example
     * ```php
     * $computed = $builder->fromModel($post, 'en');
     *
     * // $computed->title = "My Blog Post" (from $post->title)
     * // $computed->description = "This is the..." (from $post->excerpt)
     * // $computed->ogImage = "/images/post.jpg" (from $post->featured_image)
     * ```
     */
    public function fromModel(Model $model, string $locale): SEOData
    {
        return new SEOData(
            title: $this->computeTitle($model),
            description: $this->computeDescription($model),
            robots: $this->computeRobots($model),
            ogImage: $this->computeImage($model),
            ogType: $this->computeOgType($model),
            publishedTime: $this->computePublishedTime($model),
            modifiedTime: $this->computeModifiedTime($model),
            author: $this->computeAuthor($model),
            section: $this->computeSection($model),
            tags: $this->computeTags($model),
            locale: $locale,
        );
    }

    /**
     * Compute the title from model fields.
     *
     * @param Model $model The Eloquent model
     * @return string|null Computed title or null
     */
    protected function computeTitle(Model $model): ?string
    {
        // Priority 1: Custom method
        if (method_exists($model, 'getSEOTitle')) {
            $title = $model->getSEOTitle();
            if (! empty($title)) {
                return $title;
            }
        }

        // Priority 2: Common title fields
        foreach ($this->titleFields as $field) {
            $value = $this->getModelAttribute($model, $field);
            if (! empty($value) && is_string($value)) {
                return $value;
            }
        }

        return null;
    }

    /**
     * Compute the description from model fields.
     *
     * Candidate fields are checked in order and the first one yielding
     * meaningful text (after stripping HTML and decoding entities) wins.
     * The candidate list is configurable via
     * config('seo.computed.description_fields') so applications can encode
     * their own field priorities (e.g. subtitle, abstract, biography)
     * without subclassing.
     *
     * @param Model $model The Eloquent model
     * @return string|null Computed description or null
     */
    protected function computeDescription(Model $model): ?string
    {
        // Priority 1: Custom method
        if (method_exists($model, 'getSEODescription')) {
            $description = $model->getSEODescription();
            if (! empty($description)) {
                return $this->truncateDescription($description);
            }
        }

        // Priority 2: Configured or default candidate field list
        foreach ($this->descriptionFields() as $field) {
            $value = $this->getModelAttribute($model, $field);
            if (! empty($value) && is_string($value)) {
                $clean = $this->truncateDescription($value);

                // Fields holding only markup/whitespace are skipped so a
                // later candidate can still provide the description.
                if ($clean !== '') {
                    return $clean;
                }
            }
        }

        return null;
    }

    /**
     * The ordered description candidate fields.
     *
     * @return array<int, string>
     */
    protected function descriptionFields(): array
    {
        $configured = config('seo.computed.description_fields', []);

        if (! empty($configured)) {
            return $configured;
        }

        return array_merge($this->excerptFields, $this->contentFields);
    }

    /**
     * Compute the robots directive from the model's indexability.
     *
     * Models can expose either a getSEORobots() method or an
     * `is_indexable` attribute. A non-indexable model computes
     * 'noindex, nofollow'; an explicitly indexable one 'index, follow'.
     * Explicit robots values stored in seo_meta still win (they are merged
     * on top of computed values by the resolver).
     *
     * @param Model $model The Eloquent model
     * @return string|null Robots directive or null when the model has no opinion
     */
    protected function computeRobots(Model $model): ?string
    {
        if (method_exists($model, 'getSEORobots')) {
            $robots = $model->getSEORobots();
            if (! empty($robots)) {
                return $robots;
            }
        }

        $indexable = $this->getModelAttribute($model, 'is_indexable');

        if ($indexable === null) {
            return null;
        }

        return $indexable ? 'index, follow' : 'noindex, nofollow';
    }

    /**
     * Compute the image from model fields.
     *
     * @param Model $model The Eloquent model
     * @return string|null Computed image URL or null
     */
    protected function computeImage(Model $model): ?string
    {
        // Priority 1: Custom method
        if (method_exists($model, 'getSEOImage')) {
            $image = $model->getSEOImage();
            if (! empty($image)) {
                return $this->normalizeImageUrl($image);
            }
        }

        // Priority 2: Common image fields
        foreach ($this->imageFields as $field) {
            $value = $this->getModelAttribute($model, $field);
            if (! empty($value) && is_string($value)) {
                return $this->normalizeImageUrl($value);
            }
        }

        // Priority 3: Extract from content
        foreach ($this->contentFields as $field) {
            $content = $this->getModelAttribute($model, $field);
            if (! empty($content) && is_string($content)) {
                $image = $this->extractFirstImage($content);
                if ($image) {
                    return $this->normalizeImageUrl($image);
                }
            }
        }

        // Priority 4: Default from config
        $default = config('seo.default_og_image');

        return $default ? $this->normalizeImageUrl($default) : null;
    }

    /**
     * Compute the Open Graph type based on model class.
     *
     * @param Model $model The Eloquent model
     * @return string The og:type value
     */
    protected function computeOgType(Model $model): string
    {
        // Check for custom method
        if (method_exists($model, 'getSEOOgType')) {
            return $model->getSEOOgType();
        }

        // Infer from class name
        $className = strtolower(class_basename($model));

        return match (true) {
            str_contains($className, 'post') => 'article',
            str_contains($className, 'article') => 'article',
            str_contains($className, 'news') => 'article',
            str_contains($className, 'blog') => 'article',
            str_contains($className, 'product') => 'product',
            str_contains($className, 'profile') => 'profile',
            str_contains($className, 'user') => 'profile',
            str_contains($className, 'video') => 'video.other',
            str_contains($className, 'music') => 'music.song',
            default => 'website',
        };
    }

    /**
     * Compute the published time.
     *
     * @param Model $model The Eloquent model
     * @return DateTimeInterface|null The published date or null
     */
    protected function computePublishedTime(Model $model): ?DateTimeInterface
    {
        $fields = ['published_at', 'publish_date', 'created_at', 'date'];

        foreach ($fields as $field) {
            $value = $this->getModelAttribute($model, $field);
            if ($value instanceof DateTimeInterface) {
                return $value;
            }
        }

        return null;
    }

    /**
     * Compute the modified time.
     *
     * @param Model $model The Eloquent model
     * @return DateTimeInterface|null The modified date or null
     */
    protected function computeModifiedTime(Model $model): ?DateTimeInterface
    {
        $value = $this->getModelAttribute($model, 'updated_at');

        return $value instanceof DateTimeInterface ? $value : null;
    }

    /**
     * Compute the author name.
     *
     * @param Model $model The Eloquent model
     * @return string|null The author name or null
     */
    protected function computeAuthor(Model $model): ?string
    {
        // Check for custom method
        if (method_exists($model, 'getSEOAuthor')) {
            return $model->getSEOAuthor();
        }

        // Check author relationship (only if loaded to avoid N+1)
        if (method_exists($model, 'author') && $model->relationLoaded('author')) {
            $author = $model->getRelation('author');
            if ($author && isset($author->name)) {
                return $author->name;
            }
        }

        // Check user relationship
        if (method_exists($model, 'user') && $model->relationLoaded('user')) {
            $user = $model->getRelation('user');
            if ($user && isset($user->name)) {
                return $user->name;
            }
        }

        // Check direct author fields
        foreach (['author_name', 'author', 'written_by', 'created_by_name'] as $field) {
            $value = $this->getModelAttribute($model, $field);
            if (! empty($value) && is_string($value)) {
                return $value;
            }
        }

        return null;
    }

    /**
     * Compute the article section/category.
     *
     * @param Model $model The Eloquent model
     * @return string|null The section name or null
     */
    protected function computeSection(Model $model): ?string
    {
        // Check for custom method
        if (method_exists($model, 'getSEOSection')) {
            return $model->getSEOSection();
        }

        // Check category relationship
        if (method_exists($model, 'category') && $model->relationLoaded('category')) {
            $category = $model->getRelation('category');
            if ($category) {
                return $category->name ?? $category->title ?? null;
            }
        }

        // Check direct fields
        foreach (['section', 'category_name', 'category', 'type'] as $field) {
            $value = $this->getModelAttribute($model, $field);
            if (! empty($value) && is_string($value)) {
                return $value;
            }
        }

        return null;
    }

    /**
     * Compute article tags.
     *
     * @param Model $model The Eloquent model
     * @return array<int, string>|null Array of tag names or null
     */
    protected function computeTags(Model $model): ?array
    {
        // Check for custom method
        if (method_exists($model, 'getSEOTags')) {
            return $model->getSEOTags();
        }

        // Check tags relationship
        if (method_exists($model, 'tags') && $model->relationLoaded('tags')) {
            $tags = $model->getRelation('tags');
            if ($tags && $tags->isNotEmpty()) {
                return $tags->pluck('name')->filter()->values()->toArray();
            }
        }

        // Check tags field (might be JSON array)
        $value = $this->getModelAttribute($model, 'tags');

        if (is_array($value) && ! empty($value)) {
            return array_values(array_filter($value, 'is_string'));
        }

        return null;
    }

    /**
     * Safely get a model attribute.
     *
     * @param Model $model The Eloquent model
     * @param string $field The field name
     * @return mixed The attribute value or null
     */
    protected function getModelAttribute(Model $model, string $field): mixed
    {
        try {
            return $model->getAttribute($field);
        } catch (\Exception) {
            return null;
        }
    }

    /**
     * Truncate and clean text for use as a description.
     *
     * Strips HTML, decodes entities, collapses whitespace, then truncates
     * at a word boundary without adding an ellipsis. Search engines ignore
     * trailing "..." and mid-word cuts read poorly in SERP snippets, so the
     * cut lands on the last full word within the limit (as long as that
     * word boundary is at least 60% into the limit; otherwise a hard cut
     * is used). Trailing punctuation left by the cut is trimmed.
     *
     * @param string $text The raw text
     * @return string Cleaned and truncated text
     */
    protected function truncateDescription(string $text): string
    {
        // Strip HTML tags, then decode entities (&amp; → &, &agrave; → à)
        $text = html_entity_decode(strip_tags($text), ENT_QUOTES | ENT_HTML5, 'UTF-8');

        // Normalize whitespace (including unicode spaces)
        $text = preg_replace('/\s+/u', ' ', $text);
        $text = trim($text);

        $maxLength = (int) config('seo.computed.description_max_length', self::MAX_DESCRIPTION_LENGTH);

        if (mb_strlen($text) <= $maxLength) {
            return $text;
        }

        $candidate = mb_substr($text, 0, $maxLength + 1);
        $lastSpacePosition = mb_strrpos($candidate, ' ');

        if ($lastSpacePosition !== false && $lastSpacePosition >= (int) floor($maxLength * self::WORD_BOUNDARY_MIN_RATIO)) {
            return rtrim(mb_substr($candidate, 0, $lastSpacePosition), self::TRUNCATION_TRIM_CHARS);
        }

        return rtrim(mb_substr($text, 0, $maxLength), self::TRUNCATION_TRIM_CHARS);
    }

    /**
     * Extract the first image URL from HTML content.
     *
     * @param string $html The HTML content
     * @return string|null The image URL or null
     */
    protected function extractFirstImage(string $html): ?string
    {
        // Try src attribute first
        if (preg_match('/<img[^>]+src=["\']([^"\']+)["\']/', $html, $matches)) {
            return $matches[1];
        }

        // Try data-src for lazy-loaded images
        if (preg_match('/<img[^>]+data-src=["\']([^"\']+)["\']/', $html, $matches)) {
            return $matches[1];
        }

        return null;
    }

    /**
     * Normalize an image URL to be absolute.
     *
     * @param string $url The image URL (may be relative)
     * @return string Absolute URL
     */
    protected function normalizeImageUrl(string $url): string
    {
        // Already absolute
        if (str_starts_with($url, 'http://') || str_starts_with($url, 'https://')) {
            return $url;
        }

        // Protocol-relative
        if (str_starts_with($url, '//')) {
            return 'https:' . $url;
        }

        // Relative URL - make absolute
        return url($url);
    }
}
