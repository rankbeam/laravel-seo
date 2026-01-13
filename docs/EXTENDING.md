# Extending Laravel SEO Suite

Guide for customizing and extending the SEO package functionality.

## Table of Contents

-   [Custom Analyzer Rules](#custom-analyzer-rules)
-   [Custom Schema Types](#custom-schema-types)
-   [Event Hooks](#event-hooks)
-   [Service Container Bindings](#service-container-bindings)
-   [Customizing Models](#customizing-models)
-   [Custom Blade Directives](#custom-blade-directives)
-   [Adding Languages](#adding-languages)

---

## Custom Analyzer Rules

The content analyzer uses a rule-based system. You can create custom rules to check for specific SEO criteria.

### Creating a Custom Rule

**Step 1:** Create a rule class implementing `RuleInterface`:

```php
<?php

namespace App\SEO\Rules;

use Fibonoir\LaravelSEO\Contracts\RuleInterface;
use Fibonoir\LaravelSEO\Data\AnalysisContext;
use Fibonoir\LaravelSEO\Data\RuleResult;

class BrandMentionRule implements RuleInterface
{
    /**
     * Unique identifier for the rule.
     */
    public function getId(): string
    {
        return 'brand_mention';
    }

    /**
     * Human-readable name.
     */
    public function getName(): string
    {
        return 'Brand Mention Check';
    }

    /**
     * Category for grouping in reports.
     */
    public function getCategory(): string
    {
        return 'content'; // content, keyword, meta, links, media, technical
    }

    /**
     * Rule importance weight (0-100).
     * Higher weight = more impact on overall score.
     */
    public function getWeight(): int
    {
        return 5;
    }

    /**
     * Execute the rule check.
     */
    public function check(AnalysisContext $context): RuleResult
    {
        $brandName = config('seo.site_name', '');
        $content = $context->getContent();

        if (empty($brandName)) {
            return RuleResult::skip(
                $this,
                'Brand name not configured'
            );
        }

        $mentions = substr_count(
            strtolower($content),
            strtolower($brandName)
        );

        if ($mentions === 0) {
            return RuleResult::fail(
                $this,
                "Your brand name '{$brandName}' is not mentioned in the content.",
                "Consider naturally mentioning your brand at least once."
            );
        }

        if ($mentions > 5) {
            return RuleResult::warning(
                $this,
                "Brand name mentioned {$mentions} times - might be excessive.",
                "Aim for 1-3 natural brand mentions."
            );
        }

        return RuleResult::pass(
            $this,
            "Brand name mentioned {$mentions} time(s)."
        );
    }
}
```

**Step 2:** Register the rule in config:

```php
// config/seo.php
'analyzer' => [
    'rule_paths' => [
        'App\SEO\Rules' => app_path('SEO/Rules'),
    ],
],
```

### RuleResult Methods

```php
// Passing result (adds to score)
RuleResult::pass(RuleInterface $rule, string $message);

// Warning (partial score)
RuleResult::warning(RuleInterface $rule, string $message, ?string $recommendation = null);

// Failing result (no score contribution)
RuleResult::fail(RuleInterface $rule, string $message, ?string $recommendation = null);

// Skip (not applicable, doesn't affect score)
RuleResult::skip(RuleInterface $rule, string $reason);
```

### AnalysisContext Properties

```php
$context->getContent();           // Raw HTML content
$context->getPlainText();         // Stripped text content
$context->getTitle();             // Page title
$context->getDescription();       // Meta description
$context->getUrl();               // Page URL
$context->getKeyword();           // Primary focus keyword
$context->getKeywordSynonyms();   // Keyword synonyms
$context->getAllKeywords();       // All focus keywords
$context->getLocale();            // Content locale
$context->getModel();             // Eloquent model (if available)
$context->getSeoMeta();           // SEOMeta model (if available)

// Extracted data
$context->getHeadings();          // Array of headings by level
$context->getImages();            // Array of image data
$context->getLinks();             // Array of link data
$context->getWordCount();         // Total word count
$context->getSentences();         // Array of sentences
$context->getParagraphs();        // Array of paragraphs
```

### Extending AbstractRule

For convenience, extend `AbstractRule` which provides common functionality:

```php
<?php

namespace App\SEO\Rules;

use Fibonoir\LaravelSEO\Rules\AbstractRule;
use Fibonoir\LaravelSEO\Data\AnalysisContext;
use Fibonoir\LaravelSEO\Data\RuleResult;

class CustomRule extends AbstractRule
{
    protected string $id = 'custom_rule';
    protected string $name = 'Custom Rule';
    protected string $category = 'content';
    protected int $weight = 10;

    public function check(AnalysisContext $context): RuleResult
    {
        // Your logic here
    }
}
```

---

## Custom Schema Types

Add custom JSON-LD schema types for specialized content.

### Creating a Custom Schema

```php
<?php

namespace App\SEO\Schema;

use Fibonoir\LaravelSEO\Services\Schema\SchemaCollection;
use Illuminate\Database\Eloquent\Model;

class RecipeSchema
{
    public function generate(Model $model): array
    {
        return [
            '@context' => 'https://schema.org',
            '@type' => 'Recipe',
            'name' => $model->title,
            'description' => $model->description,
            'image' => $model->featured_image,
            'author' => [
                '@type' => 'Person',
                'name' => $model->author->name,
            ],
            'datePublished' => $model->created_at->toIso8601String(),
            'prepTime' => "PT{$model->prep_time}M",
            'cookTime' => "PT{$model->cook_time}M",
            'totalTime' => "PT" . ($model->prep_time + $model->cook_time) . "M",
            'recipeYield' => "{$model->servings} servings",
            'recipeIngredient' => $model->ingredients->pluck('name')->toArray(),
            'recipeInstructions' => $model->steps->map(fn ($step) => [
                '@type' => 'HowToStep',
                'text' => $step->instruction,
            ])->toArray(),
            'nutrition' => $model->nutrition ? [
                '@type' => 'NutritionInformation',
                'calories' => "{$model->nutrition->calories} calories",
            ] : null,
            'recipeCategory' => $model->category->name,
            'recipeCuisine' => $model->cuisine,
            'aggregateRating' => $model->ratings_count > 0 ? [
                '@type' => 'AggregateRating',
                'ratingValue' => $model->average_rating,
                'ratingCount' => $model->ratings_count,
            ] : null,
        ];
    }
}
```

### Using Custom Schema in Models

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Fibonoir\LaravelSEO\Traits\HasSEO;
use App\SEO\Schema\RecipeSchema;

class Recipe extends Model
{
    use HasSEO;

    /**
     * Get custom schema for this model.
     */
    public function getSchemaForSEO(): ?array
    {
        return app(RecipeSchema::class)->generate($this);
    }
}
```

### Registering Global Schema Types

```php
// In AppServiceProvider::boot()
use Fibonoir\LaravelSEO\Services\Schema\SchemaCollection;

$this->app->extend(SchemaCollection::class, function ($collection) {
    $collection->registerType('Recipe', RecipeSchema::class);
    $collection->registerType('Course', CourseSchema::class);
    $collection->registerType('Event', EventSchema::class);

    return $collection;
});
```

---

## Event Hooks

The package dispatches events at key points for customization.

### Available Events

| Event                        | When Dispatched                    |
| ---------------------------- | ---------------------------------- |
| `SEOAnalysisStarting`        | Before content analysis begins     |
| `SEOAnalysisCompleted`       | After analysis completes           |
| `SEOScoreUpdated`            | When a model's SEO score changes   |
| `RedirectMatched`            | When a redirect is triggered       |
| `RedirectCreated`            | When a new redirect is created     |
| `NotFoundLogged`             | When a 404 is logged               |
| `SitemapGenerated`           | After sitemap generation           |
| `ScanCompleted`              | After a sitewide scan completes    |

### Listening to Events

```php
// In EventServiceProvider

protected $listen = [
    \Fibonoir\LaravelSEO\Events\SEOAnalysisCompleted::class => [
        \App\Listeners\NotifyLowSEOScore::class,
    ],
    \Fibonoir\LaravelSEO\Events\NotFoundLogged::class => [
        \App\Listeners\AlertOn404Spike::class,
    ],
];
```

### Example Listener

```php
<?php

namespace App\Listeners;

use Fibonoir\LaravelSEO\Events\SEOAnalysisCompleted;
use App\Notifications\LowSEOScoreNotification;

class NotifyLowSEOScore
{
    public function handle(SEOAnalysisCompleted $event): void
    {
        $score = $event->seoMeta->seo_score;
        $model = $event->seoMeta->seoable;

        if ($score < 50 && $model->is_published) {
            // Notify content team
            $model->author->notify(new LowSEOScoreNotification($model, $score));
        }
    }
}
```

### Event Data

```php
// SEOAnalysisCompleted
$event->seoMeta;    // SEOMeta model
$event->score;      // Integer score (0-100)
$event->report;     // Array analysis report

// RedirectMatched
$event->redirect;   // SEORedirect model
$event->request;    // HTTP request
$event->targetUrl;  // Resolved target URL

// NotFoundLogged
$event->log;        // SEO404Log model
$event->request;    // HTTP request
$event->isNew;      // Boolean - first time seeing this path

// SitemapGenerated
$event->path;       // Path to generated sitemap
$event->urlCount;   // Number of URLs in sitemap
```

---

## Service Container Bindings

Customize core services through the container.

### Available Services

| Service                   | Purpose                          |
| ------------------------- | -------------------------------- |
| `SEOResolver`             | Resolves SEO data for models     |
| `ContentAnalyzer`         | Analyzes content for SEO         |
| `TagRenderer`             | Renders HTML meta tags           |
| `RedirectManager`         | Manages URL redirects            |
| `CacheManager`            | Manages SEO caches               |
| `SEODefaultsRepository`   | Retrieves SEO defaults           |
| `SEOComputedBuilder`      | Builds computed SEO values       |
| `Stemmer`                 | Word stemming for analysis       |
| `Tokenizer`               | Text tokenization                |
| `StopWords`               | Stop word filtering              |

### Extending a Service

```php
// In AppServiceProvider::register()

$this->app->extend(
    \Fibonoir\LaravelSEO\Services\SEOResolver::class,
    function ($resolver, $app) {
        return new \App\Services\CustomSEOResolver(
            $app->make(\Fibonoir\LaravelSEO\Services\SEODefaultsRepository::class),
            $app->make(\Fibonoir\LaravelSEO\Services\SEOComputedBuilder::class)
        );
    }
);
```

### Replacing a Service

```php
// In AppServiceProvider::register()

$this->app->singleton(
    \Fibonoir\LaravelSEO\Services\TagRenderer::class,
    \App\Services\CustomTagRenderer::class
);
```

### Creating a Custom Resolver

```php
<?php

namespace App\Services;

use Fibonoir\LaravelSEO\Services\SEOResolver;
use Fibonoir\LaravelSEO\Data\SEOData;
use Illuminate\Database\Eloquent\Model;

class CustomSEOResolver extends SEOResolver
{
    public function resolve(
        ?Model $model = null,
        ?string $route = null,
        ?string $locale = null,
    ): SEOData {
        $seoData = parent::resolve($model, $route, $locale);

        // Add custom tracking parameters
        if ($seoData->canonical) {
            $seoData = $seoData->with(
                'canonical',
                $seoData->canonical . '?ref=organic'
            );
        }

        // Add custom schema
        if ($model && method_exists($model, 'getCustomSchema')) {
            $existingSchema = $seoData->schemaJsonld ?? [];
            $customSchema = $model->getCustomSchema();
            $seoData = $seoData->with(
                'schemaJsonld',
                array_merge($existingSchema, [$customSchema])
            );
        }

        return $seoData;
    }
}
```

---

## Customizing Models

### Overriding HasSEO Methods

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Fibonoir\LaravelSEO\Traits\HasSEO;

class Post extends Model
{
    use HasSEO;

    /**
     * Custom title generation logic.
     */
    public function getSEOTitle(): ?string
    {
        if ($this->seo_title) {
            return $this->seo_title;
        }

        if ($this->is_featured) {
            return "Featured: {$this->title}";
        }

        return $this->title;
    }

    /**
     * Custom description with fallback chain.
     */
    public function getSEODescription(): ?string
    {
        return $this->meta_description
            ?? $this->excerpt
            ?? $this->ai_summary
            ?? \Str::limit(strip_tags($this->content), 155);
    }

    /**
     * Custom image with media library support.
     */
    public function getSEOImage(): ?string
    {
        // Spatie Media Library
        if ($media = $this->getFirstMedia('featured')) {
            return $media->getUrl('og');
        }

        return $this->featured_image ?? config('seo.default_og_image');
    }

    /**
     * Custom URL generation.
     */
    public function getUrlForSEO(): string
    {
        return route('blog.post', [
            'category' => $this->category->slug,
            'post' => $this->slug,
        ]);
    }

    /**
     * Content for SEO analysis.
     */
    public function getContentForSEO(): string
    {
        return $this->title . "\n\n" .
               $this->excerpt . "\n\n" .
               $this->content;
    }

    /**
     * Fields that trigger re-analysis when changed.
     */
    public function getSEOContentFields(): array
    {
        return [
            'title',
            'excerpt',
            'content',
            'category_id',
            'tags',
        ];
    }

    /**
     * Custom schema type.
     */
    public function getSchemaType(): string
    {
        return 'Article';
    }

    /**
     * Custom schema data.
     */
    public function getSchemaForSEO(): ?array
    {
        return [
            '@context' => 'https://schema.org',
            '@type' => 'BlogPosting',
            'headline' => $this->title,
            'description' => $this->getSEODescription(),
            'image' => $this->getSEOImage(),
            'datePublished' => $this->published_at?->toIso8601String(),
            'dateModified' => $this->updated_at->toIso8601String(),
            'author' => [
                '@type' => 'Person',
                'name' => $this->author->name,
                'url' => route('author.show', $this->author),
            ],
            'publisher' => [
                '@type' => 'Organization',
                'name' => config('seo.schema.publisher.name'),
                'logo' => [
                    '@type' => 'ImageObject',
                    'url' => config('seo.schema.publisher.logo'),
                ],
            ],
            'mainEntityOfPage' => [
                '@type' => 'WebPage',
                '@id' => $this->getUrlForSEO(),
            ],
        ];
    }
}
```

### Adding Custom Scopes

```php
// In your model

public function scopeWithGoodSEO($query)
{
    return $query->whereHas('seoMeta', function ($q) {
        $q->where('seo_score', '>=', 70);
    });
}

public function scopeNeedsSEOWork($query)
{
    return $query->whereHas('seoMeta', function ($q) {
        $q->where('seo_score', '<', 50)
          ->orWhereNull('seo_score');
    });
}

public function scopeRecentlyAnalyzed($query)
{
    return $query->whereHas('seoMeta', function ($q) {
        $q->where('analyzed_at', '>=', now()->subDays(7));
    });
}
```

---

## Custom Blade Directives

Register custom directives for specialized use cases.

```php
// In AppServiceProvider::boot()

use Illuminate\Support\Facades\Blade;
use Fibonoir\LaravelSEO\Services\SEOResolver;

// Render hreflang tags for multi-language
Blade::directive('seoHreflang', function ($expression) {
    return "<?php
        \$model = {$expression};
        \$locales = config('seo.analyzer.supported_locales', ['en']);
        foreach (\$locales as \$locale) {
            \$url = method_exists(\$model, 'getUrlForLocale')
                ? \$model->getUrlForLocale(\$locale)
                : url()->current();
            echo '<link rel=\"alternate\" hreflang=\"' . \$locale . '\" href=\"' . \$url . '\">' . PHP_EOL;
        }
    ?>";
});

// Render breadcrumb schema
Blade::directive('seoBreadcrumbs', function ($expression) {
    return "<?php echo app(\\App\\Services\\BreadcrumbRenderer::class)->render({$expression}); ?>";
});

// Conditional noindex for dev/staging
Blade::directive('seoNoindexUnlessProduction', function () {
    return "<?php if (app()->environment() !== 'production'): ?>
        <meta name=\"robots\" content=\"noindex,nofollow\">
    <?php endif; ?>";
});
```

---

## Adding Languages

Add support for new languages in the analyzer.

### Adding Stop Words

Create `resources/lang/vendor/seo/{locale}.json`:

```json
{
    "stop_words": [
        "the", "a", "an", "and", "or", "but", "in", "on", "at", "to", "for"
    ],
    "transition_words": [
        "however", "therefore", "furthermore", "meanwhile", "consequently"
    ]
}
```

### Adding Stemmer Support

```php
// In AppServiceProvider::boot()

use Fibonoir\LaravelSEO\Support\Stemmer;

$this->app->extend(Stemmer::class, function ($stemmer) {
    $stemmer->registerLanguage('tr', function ($word) {
        // Turkish stemming logic
        return $this->turkishStem($word);
    });

    return $stemmer;
});
```

### Adding Readability Calculator

```php
// In AppServiceProvider::boot()

use Fibonoir\LaravelSEO\Support\ReadabilityCalculator;

$this->app->extend(ReadabilityCalculator::class, function ($calculator) {
    $calculator->registerFormula('tr', function ($text, $sentences, $words, $syllables) {
        // Turkish readability formula (e.g., Ateşman)
        $asl = $words / max($sentences, 1);
        $ash = $syllables / max($words, 1);

        return 198.825 - (40.175 * $asl) - (2.610 * $ash);
    });

    return $calculator;
});
```

---

## Best Practices

### 1. Keep Rules Focused

Each rule should check one specific thing:

```php
// Good: Focused rule
class KeywordInTitleRule implements RuleInterface { ... }

// Bad: Multi-purpose rule
class AllKeywordChecksRule implements RuleInterface { ... }
```

### 2. Use Caching for Expensive Operations

```php
public function check(AnalysisContext $context): RuleResult
{
    $cacheKey = "rule_result:{$this->getId()}:" . md5($context->getContent());

    return Cache::remember($cacheKey, 3600, function () use ($context) {
        // Expensive operation
    });
}
```

### 3. Fail Gracefully

```php
public function check(AnalysisContext $context): RuleResult
{
    try {
        // Your logic
    } catch (\Exception $e) {
        report($e);

        return RuleResult::skip($this, 'Unable to check: ' . $e->getMessage());
    }
}
```

### 4. Document Your Extensions

```php
/**
 * Checks that all external links have rel="nofollow noopener".
 *
 * This rule helps protect against link spam and security issues.
 *
 * @category links
 * @weight 8
 */
class ExternalLinkAttributesRule extends AbstractRule { ... }
```

### 5. Test Your Custom Rules

```php
use Tests\TestCase;
use App\SEO\Rules\BrandMentionRule;
use Fibonoir\LaravelSEO\Data\AnalysisContext;

class BrandMentionRuleTest extends TestCase
{
    public function test_passes_when_brand_mentioned(): void
    {
        config(['seo.site_name' => 'Acme']);

        $context = new AnalysisContext(
            content: 'Welcome to Acme, the best company.',
            title: 'Test',
            url: 'https://example.com/test'
        );

        $rule = new BrandMentionRule();
        $result = $rule->check($context);

        $this->assertTrue($result->passed);
    }
}
```
