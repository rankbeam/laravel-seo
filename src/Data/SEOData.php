<?php

declare(strict_types=1);

namespace Rankbeam\Seo\Data;

use DateTimeInterface;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Database\Eloquent\Model;
use JsonSerializable;
use Rankbeam\Seo\Models\SEODefault;
use Rankbeam\Seo\Models\SEOMeta;
use Rankbeam\Seo\Services\SEOManager;
use Rankbeam\Seo\Traits\HasSEO;

/**
 * Immutable value object representing SEO data.
 *
 * This is the central data structure passed through the SEO system.
 * All properties are readonly, ensuring immutability. Use merge() to
 * create new instances with different values.
 *
 * ## Property Groups
 *
 * **Core SEO**: title, description, canonical, robots
 * **Open Graph**: ogTitle, ogDescription, ogImage, ogType, ogSiteName, ogUrl
 * **Twitter Cards**: twitterTitle, twitterDescription, twitterImage, twitterCard, twitterSite, twitterCreator
 * **Article Metadata**: publishedTime, modifiedTime, author, section, tags
 * **Keywords**: focusKeywords (array of keyword objects with synonyms)
 * **Schema**: schemaJsonld (JSON-LD structured data)
 * **Multilingual**: locale, alternates (hreflang links)
 *
 * ## Merge Precedence Logic
 *
 * The SEO system uses a layered approach where data from multiple sources
 * is merged together. The merge() method implements "last non-null wins"
 * semantics - a null value in the overriding object does NOT replace
 * an existing value.
 *
 * Typical precedence chain (lowest to highest priority):
 *
 * 1. **Global Defaults** (config/seo.php site_defaults)
 *    - Site-wide fallbacks like site name, default og:image, robots
 *
 * 2. **Model-Type Defaults** (SEODefault for scope like 'App\Models\Post')
 *    - Per-model-type templates: "{{title}} | Blog"
 *
 * 3. **Route Defaults** (SEODefault for specific route names)
 *    - Per-route overrides for static pages
 *
 * 4. **Computed Values** (HasSEO trait's getSEOData())
 *    - Auto-generated from model attributes (e.g., post title as SEO title)
 *
 * 5. **Explicit Values** (SEOMeta database record)
 *    - User-entered values in admin panel (highest priority)
 *
 * Example:
 * ```php
 * $final = SEOData::empty()
 *     ->merge($globalDefaults)    // og:image = '/default.jpg'
 *     ->merge($postTypeDefaults)  // title template = '{{title}} | Blog'
 *     ->merge($computedFromPost)  // title = 'My Post', og:image = null
 *     ->merge($explicitMeta);     // title = 'Custom Title', og:image = '/custom.jpg'
 *
 * // Result: title='Custom Title', og:image='/custom.jpg'
 * // The null og:image in $computedFromPost didn't override the default
 * ```
 *
 * @implements Arrayable<string, mixed>
 *
 * @see SEOManager For the precedence implementation
 * @see HasSEO For model integration
 * @see SEOMeta For stored SEO data
 * @see SEODefault For default templates
 */
final class SEOData implements Arrayable, JsonSerializable
{
    public function __construct(
        // Core SEO fields
        public readonly ?string $title = null,
        public readonly ?string $description = null,
        public readonly ?string $canonical = null,
        public readonly ?string $robots = null,

        // Open Graph fields
        public readonly ?string $ogTitle = null,
        public readonly ?string $ogDescription = null,
        public readonly ?string $ogImage = null,
        public readonly ?string $ogType = 'website',
        public readonly ?string $ogSiteName = null,
        public readonly ?string $ogUrl = null,

        // Twitter Card fields
        public readonly ?string $twitterTitle = null,
        public readonly ?string $twitterDescription = null,
        public readonly ?string $twitterImage = null,
        public readonly ?string $twitterCard = 'summary_large_image',
        public readonly ?string $twitterSite = null,
        public readonly ?string $twitterCreator = null,

        // Article metadata
        public readonly ?DateTimeInterface $publishedTime = null,
        public readonly ?DateTimeInterface $modifiedTime = null,
        public readonly ?string $author = null,
        public readonly ?string $section = null,
        /** @var array<int, string>|null */
        public readonly ?array $tags = null,

        // Focus keywords for analysis
        /** @var array<int, array{keyword: string, is_primary: bool, synonyms?: array<int, string>}>|null */
        public readonly ?array $focusKeywords = null,

        // Schema markup (JSON-LD)
        /** @var array<string, mixed>|null */
        public readonly ?array $schemaJsonld = null,

        // Multilingual support
        public readonly ?string $locale = null,
        /** @var array<int, array{hreflang: string, href: string}>|null */
        public readonly ?array $alternates = null,
    ) {}

    /**
     * Create from a model that uses the HasSEO trait.
     *
     * Extracts SEO data from the model's related SEOMeta record.
     * If no SEOMeta exists, returns an empty SEOData instance.
     *
     * Note: This only reads the stored/explicit SEO values.
     * For computed values (from model attributes), use the HasSEO
     * trait's getSEOData() method which includes both computed
     * and explicit data merged together.
     *
     * @param  Model  $model  Any Eloquent model with HasSEO trait
     * @return self SEOData populated from SEOMeta or empty
     *
     * @example
     * ```php
     * $post = Post::find(1);
     * $seoData = SEOData::fromModel($post);
     * echo $seoData->title; // Returns explicit title from seo_meta table
     * ```
     */
    public static function fromModel(Model $model): self
    {
        // Access the seoMeta relationship (defined in HasSEO trait)
        $meta = $model->seoMeta ?? null;

        if (! $meta) {
            return new self;
        }

        return new self(
            title: $meta->title,
            description: $meta->description,
            canonical: $meta->canonical,
            robots: $meta->robots,
            ogTitle: $meta->og_title,
            ogDescription: $meta->og_description,
            ogImage: $meta->og_image,
            ogType: $meta->og_type,
            twitterTitle: $meta->twitter_title,
            twitterDescription: $meta->twitter_description,
            twitterImage: $meta->twitter_image,
            twitterCard: $meta->twitter_card,
            focusKeywords: $meta->focus_keywords,
            schemaJsonld: $meta->schema_jsonld,
            locale: $meta->locale,
        );
    }

    /**
     * Create from an array (form input, API request, etc.).
     *
     * Accepts both snake_case and camelCase keys for flexibility
     * when accepting data from different sources (HTML forms typically
     * use snake_case, while JavaScript/API may use camelCase).
     *
     * DateTime fields (published_time, modified_time) are automatically
     * parsed into DateTimeImmutable objects.
     *
     * @param  array<string, mixed>  $data  Input data array
     * @return self SEOData populated from array values
     *
     * @example
     * ```php
     * // From form submission (snake_case)
     * $seoData = SEOData::fromArray([
     *     'title' => 'My Page Title',
     *     'og_image' => '/images/share.jpg',
     *     'focus_keywords' => [
     *         ['keyword' => 'laravel', 'is_primary' => true],
     *     ],
     * ]);
     *
     * // From API request (camelCase also works)
     * $seoData = SEOData::fromArray([
     *     'title' => 'My Page Title',
     *     'ogImage' => '/images/share.jpg',
     * ]);
     * ```
     */
    public static function fromArray(array $data): self
    {
        return new self(
            title: $data['title'] ?? null,
            description: $data['description'] ?? null,
            canonical: $data['canonical'] ?? null,
            robots: $data['robots'] ?? null,
            ogTitle: $data['og_title'] ?? $data['ogTitle'] ?? null,
            ogDescription: $data['og_description'] ?? $data['ogDescription'] ?? null,
            ogImage: $data['og_image'] ?? $data['ogImage'] ?? null,
            ogType: $data['og_type'] ?? $data['ogType'] ?? null,
            ogSiteName: $data['og_site_name'] ?? $data['ogSiteName'] ?? null,
            ogUrl: $data['og_url'] ?? $data['ogUrl'] ?? null,
            twitterTitle: $data['twitter_title'] ?? $data['twitterTitle'] ?? null,
            twitterDescription: $data['twitter_description'] ?? $data['twitterDescription'] ?? null,
            twitterImage: $data['twitter_image'] ?? $data['twitterImage'] ?? null,
            twitterCard: $data['twitter_card'] ?? $data['twitterCard'] ?? null,
            twitterSite: $data['twitter_site'] ?? $data['twitterSite'] ?? null,
            twitterCreator: $data['twitter_creator'] ?? $data['twitterCreator'] ?? null,
            publishedTime: isset($data['published_time']) ? new \DateTimeImmutable($data['published_time']) : null,
            modifiedTime: isset($data['modified_time']) ? new \DateTimeImmutable($data['modified_time']) : null,
            author: $data['author'] ?? null,
            section: $data['section'] ?? null,
            tags: $data['tags'] ?? null,
            focusKeywords: $data['focus_keywords'] ?? $data['focusKeywords'] ?? null,
            schemaJsonld: $data['schema_jsonld'] ?? $data['schemaJsonld'] ?? null,
            locale: $data['locale'] ?? null,
            alternates: $data['alternates'] ?? null,
        );
    }

    /**
     * Create an empty SEOData instance.
     *
     * All properties will be null (or their default values for ogType
     * and twitterCard). Useful as a starting point for merge chains.
     *
     * @return self Empty SEOData with null/default values
     *
     * @example
     * ```php
     * $seoData = SEOData::empty()
     *     ->merge($defaults)
     *     ->merge($overrides);
     * ```
     */
    public static function empty(): self
    {
        return new self;
    }

    /**
     * Merge two SEOData instances, returning a new instance.
     *
     * This is the core method implementing the precedence chain. Values
     * from $other override values from $this, with one critical rule:
     *
     * **NULL values in $other do NOT override existing values in $this.**
     *
     * This allows higher-priority sources to selectively override only
     * the fields they care about, without wiping out lower-priority values.
     *
     * @param  self  $other  The SEOData to merge into this one (higher priority)
     * @return self A new SEOData instance with merged values
     *
     * @example
     * ```php
     * $defaults = new SEOData(
     *     title: 'Default Title',
     *     ogImage: '/default.jpg',
     * );
     *
     * $explicit = new SEOData(
     *     title: 'Custom Title',
     *     ogImage: null,  // This won't override!
     * );
     *
     * $merged = $defaults->merge($explicit);
     * // $merged->title === 'Custom Title' (overridden)
     * // $merged->ogImage === '/default.jpg' (preserved, null didn't override)
     * ```
     *
     * @see Class-level PHPDoc for full precedence chain documentation
     */
    public function merge(self $other): self
    {
        return new self(
            title: $other->title ?? $this->title,
            description: $other->description ?? $this->description,
            canonical: $other->canonical ?? $this->canonical,
            robots: $other->robots ?? $this->robots,
            ogTitle: $other->ogTitle ?? $this->ogTitle,
            ogDescription: $other->ogDescription ?? $this->ogDescription,
            ogImage: $other->ogImage ?? $this->ogImage,
            ogType: $other->ogType ?? $this->ogType,
            ogSiteName: $other->ogSiteName ?? $this->ogSiteName,
            ogUrl: $other->ogUrl ?? $this->ogUrl,
            twitterTitle: $other->twitterTitle ?? $this->twitterTitle,
            twitterDescription: $other->twitterDescription ?? $this->twitterDescription,
            twitterImage: $other->twitterImage ?? $this->twitterImage,
            twitterCard: $other->twitterCard ?? $this->twitterCard,
            twitterSite: $other->twitterSite ?? $this->twitterSite,
            twitterCreator: $other->twitterCreator ?? $this->twitterCreator,
            publishedTime: $other->publishedTime ?? $this->publishedTime,
            modifiedTime: $other->modifiedTime ?? $this->modifiedTime,
            author: $other->author ?? $this->author,
            section: $other->section ?? $this->section,
            tags: $other->tags ?? $this->tags,
            focusKeywords: $other->focusKeywords ?? $this->focusKeywords,
            schemaJsonld: $other->schemaJsonld ?? $this->schemaJsonld,
            locale: $other->locale ?? $this->locale,
            alternates: $other->alternates ?? $this->alternates,
        );
    }

    /**
     * Check if any focus keywords are set.
     *
     * Focus keywords are used by the SEO analyzer to check keyword
     * presence in title, description, headings, and content.
     *
     * @return bool True if focusKeywords array is non-empty
     */
    public function hasKeywords(): bool
    {
        return ! empty($this->focusKeywords);
    }

    /**
     * Get the primary focus keyword.
     *
     * Returns the keyword marked with is_primary=true, or the first
     * keyword if none is explicitly marked as primary.
     *
     * @return array{keyword: string, is_primary: bool, synonyms?: array<int, string>}|null
     *
     * @example
     * ```php
     * $seoData = SEOData::fromArray([
     *     'focus_keywords' => [
     *         ['keyword' => 'laravel seo', 'is_primary' => true, 'synonyms' => ['seo for laravel']],
     *         ['keyword' => 'meta tags', 'is_primary' => false],
     *     ],
     * ]);
     *
     * $primary = $seoData->getPrimaryKeyword();
     * // ['keyword' => 'laravel seo', 'is_primary' => true, 'synonyms' => ['seo for laravel']]
     * ```
     */
    public function getPrimaryKeyword(): ?array
    {
        if (empty($this->focusKeywords)) {
            return null;
        }

        foreach ($this->focusKeywords as $keyword) {
            if ($keyword['is_primary'] ?? false) {
                return $keyword;
            }
        }

        // Return first keyword if none marked as primary
        return $this->focusKeywords[0] ?? null;
    }

    /**
     * Convert to a nested array structure with grouped values.
     *
     * Groups related fields together (og, twitter, article, analysis)
     * for cleaner output. Empty groups are filtered out.
     *
     * Open Graph and Twitter fallback to core values:
     * - og:title falls back to title
     * - og:description falls back to description
     * - twitter:title falls back to og:title, then title
     * - twitter:image falls back to og:image
     *
     * @return array<string, mixed> Nested array with grouped SEO data
     *
     * @example Output structure:
     * ```php
     * [
     *     'title' => 'My Title',
     *     'description' => 'My description',
     *     'canonical' => 'https://example.com/page',
     *     'og' => [
     *         'title' => 'My Title',
     *         'description' => 'My description',
     *         'image' => '/share.jpg',
     *         'type' => 'website',
     *     ],
     *     'twitter' => [
     *         'card' => 'summary_large_image',
     *         'title' => 'My Title',
     *         // ...
     *     ],
     *     'schema' => [...],
     * ]
     * ```
     */
    public function toArray(): array
    {
        return array_filter([
            'title' => $this->title,
            'description' => $this->description,
            'canonical' => $this->canonical,
            'robots' => $this->robots,
            'og' => array_filter([
                'title' => $this->ogTitle ?? $this->title,
                'description' => $this->ogDescription ?? $this->description,
                'image' => $this->ogImage,
                'type' => $this->ogType,
                'site_name' => $this->ogSiteName,
                'url' => $this->ogUrl,
            ]),
            'twitter' => array_filter([
                'card' => $this->twitterCard,
                'title' => $this->twitterTitle ?? $this->ogTitle ?? $this->title,
                'description' => $this->twitterDescription ?? $this->ogDescription ?? $this->description,
                'image' => $this->twitterImage ?? $this->ogImage,
                'site' => $this->twitterSite,
                'creator' => $this->twitterCreator,
            ]),
            'article' => array_filter([
                'published_time' => $this->publishedTime?->format('c'),
                'modified_time' => $this->modifiedTime?->format('c'),
                'author' => $this->author,
                'section' => $this->section,
                'tags' => $this->tags,
            ]),
            'focus_keywords' => $this->focusKeywords,
            'schema' => $this->schemaJsonld,
            'alternates' => $this->alternates,
        ], fn ($v) => $v !== null && $v !== []);
    }

    /**
     * Convert to a flat array with snake_case keys.
     *
     * Returns all fields at the same level (no nesting), using snake_case
     * naming convention. Ideal for form binding in Blade/Livewire or
     * database storage.
     *
     * DateTime values are formatted as 'Y-m-d H:i:s' strings.
     * Arrays (focus_keywords, schema_jsonld, etc.) are preserved as-is.
     *
     * @return array<string, mixed> Flat array with all SEO fields
     *
     * @example
     * ```php
     * // In a Livewire component
     * public array $seo;
     *
     * public function mount(Post $post)
     * {
     *     $this->seo = $post->getSEOData()->toFlatArray();
     * }
     *
     * // In Blade: <input wire:model="seo.og_title" />
     * ```
     */
    public function toFlatArray(): array
    {
        return [
            'title' => $this->title,
            'description' => $this->description,
            'canonical' => $this->canonical,
            'robots' => $this->robots,
            'og_title' => $this->ogTitle,
            'og_description' => $this->ogDescription,
            'og_image' => $this->ogImage,
            'og_type' => $this->ogType,
            'og_site_name' => $this->ogSiteName,
            'og_url' => $this->ogUrl,
            'twitter_title' => $this->twitterTitle,
            'twitter_description' => $this->twitterDescription,
            'twitter_image' => $this->twitterImage,
            'twitter_card' => $this->twitterCard,
            'twitter_site' => $this->twitterSite,
            'twitter_creator' => $this->twitterCreator,
            'published_time' => $this->publishedTime?->format('Y-m-d H:i:s'),
            'modified_time' => $this->modifiedTime?->format('Y-m-d H:i:s'),
            'author' => $this->author,
            'section' => $this->section,
            'tags' => $this->tags,
            'focus_keywords' => $this->focusKeywords,
            'schema_jsonld' => $this->schemaJsonld,
            'locale' => $this->locale,
            'alternates' => $this->alternates,
        ];
    }

    /**
     * Implement JsonSerializable interface.
     *
     * Returns the nested array format (same as toArray()) when
     * the object is JSON encoded.
     *
     * @return array<string, mixed>
     *
     * @example
     * ```php
     * $seoData = SEOData::fromArray(['title' => 'My Title']);
     * echo json_encode($seoData);
     * // {"title":"My Title","og":{"title":"My Title",...},...}
     * ```
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    /**
     * Create a copy with a specific field changed.
     *
     * Since SEOData is immutable, this returns a new instance
     * with the specified field updated.
     *
     * @param  string  $field  The field name (camelCase)
     * @param  mixed  $value  The new value
     * @return self New SEOData instance with updated field
     *
     * @example
     * ```php
     * $seoData = SEOData::fromArray(['title' => 'Original']);
     * $updated = $seoData->with('title', 'Updated Title');
     * // $seoData->title === 'Original' (unchanged)
     * // $updated->title === 'Updated Title'
     * ```
     */
    public function with(string $field, mixed $value): self
    {
        $data = $this->toFlatArray();

        // Convert camelCase to snake_case for flat array
        $snakeField = strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', $field));

        // Check both formats
        if (array_key_exists($snakeField, $data)) {
            $data[$snakeField] = $value;
        } elseif (array_key_exists($field, $data)) {
            $data[$field] = $value;
        } else {
            // Direct property access for camelCase fields
            $data[$snakeField] = $value;
        }

        return self::fromArray($data);
    }

    /**
     * Check if this SEOData has any non-null values.
     *
     * @return bool True if at least one SEO field has a value
     */
    public function isEmpty(): bool
    {
        return $this->title === null
            && $this->description === null
            && $this->canonical === null
            && $this->robots === null
            && $this->ogTitle === null
            && $this->ogDescription === null
            && $this->ogImage === null
            && $this->twitterTitle === null
            && $this->twitterDescription === null
            && $this->twitterImage === null
            && $this->focusKeywords === null
            && $this->schemaJsonld === null;
    }

    /**
     * Get all focus keywords as a simple string array.
     *
     * @return array<int, string> Array of keyword strings
     */
    public function getKeywordStrings(): array
    {
        if (empty($this->focusKeywords)) {
            return [];
        }

        return array_map(
            fn (array $kw) => $kw['keyword'],
            $this->focusKeywords
        );
    }
}
