# API Reference

Complete reference for all public methods, request/response formats, and error handling.

## Table of Contents

-   [HasSEO Trait](#hasseo-trait)
-   [SEOResolver Service](#seoresolver-service)
-   [RedirectManager Service](#redirectmanager-service)
-   [CacheManager Service](#cachemanager-service)
-   [ContentAnalyzer Service](#contentanalyzer-service)
-   [Data Objects](#data-objects)
-   [REST API Endpoints](#rest-api-endpoints)
-   [Error Handling](#error-handling)

---

## HasSEO Trait

Add SEO capabilities to any Eloquent model.

### Usage

```php
use Rankbeam\Seo\Traits\HasSEO;

class Post extends Model
{
    use HasSEO;
}
```

### Properties

#### `seoMeta`

The SEO metadata relationship.

```php
// Access SEO meta directly
$post->seoMeta->title;
$post->seoMeta->seo_score;

// Update via relationship
$post->seoMeta()->update(['title' => 'New Title']);
```

**Returns:** `MorphOne<SEOMeta>` with default values if not exists.

---

### Methods

#### `seoData(?string $locale = null): SEOData`

Get fully resolved SEO data with the complete precedence chain.

```php
// Get SEO data for current locale
$seo = $post->seoData();
echo $seo->title;
echo $seo->description;

// Get for specific locale
$seo = $post->seoData('de');
```

**Parameters:**
| Name | Type | Default | Description |
|------|------|---------|-------------|
| `$locale` | `?string` | `null` | Locale code, uses app locale if null |

**Returns:** `SEOData` - Fully resolved SEO data object.

---

#### `saveSEO(array $data, ?string $locale = null): void`

Save SEO metadata for this model.

```php
$post->saveSEO([
    'title' => 'Custom SEO Title',
    'description' => 'Custom meta description',
    'robots' => 'noindex,follow',
    'focus_keywords' => [
        ['keyword' => 'laravel seo', 'is_primary' => true],
        ['keyword' => 'meta tags', 'is_primary' => false],
    ],
]);

// Save for specific locale
$post->saveSEO(['title' => 'Titre SEO'], 'fr');
```

**Parameters:**
| Name | Type | Default | Description |
|------|------|---------|-------------|
| `$data` | `array` | - | SEO data to save |
| `$locale` | `?string` | `null` | Locale code, uses app locale if null |

**Supported Data Keys:**
-   `title`, `description`, `canonical`, `robots`
-   `og_title`, `og_description`, `og_image`, `og_type`
-   `twitter_title`, `twitter_description`, `twitter_image`, `twitter_card`
-   `focus_keywords`, `schema_jsonld`, `schema_type`

---

#### `analyzeForSEO(): void`

Trigger SEO analysis synchronously (blocking).

```php
$post->analyzeForSEO();

// Check results after analysis
$score = $post->fresh()->getSEOScore();
```

---

#### `dispatchAnalysis(int $delay = 0): void`

Dispatch SEO analysis as a background job.

```php
// Queue for background analysis
$post->dispatchAnalysis();

// With delay (seconds)
$post->dispatchAnalysis(30);
```

**Parameters:**
| Name | Type | Default | Description |
|------|------|---------|-------------|
| `$delay` | `int` | `0` | Delay in seconds before running |

---

#### `getSEOScore(): ?int`

Get the SEO score for this model.

```php
$score = $post->getSEOScore(); // 0-100 or null
```

**Returns:** `?int` - Score from 0-100, or null if not analyzed.

---

#### `getSEOAnalysisReport(): ?array`

Get the detailed SEO analysis report.

```php
$report = $post->getSEOAnalysisReport();

// Structure:
// [
//     'score' => 75,
//     'results' => [...],      // Individual rule results
//     'recommendations' => [...] // Improvement suggestions
// ]
```

**Returns:** `?array` - Analysis report or null.

---

#### `needsSEOAnalysis(): bool`

Check if the model needs SEO analysis.

```php
if ($post->needsSEOAnalysis()) {
    $post->dispatchAnalysis();
}
```

Returns `true` if:
-   Never analyzed
-   Analysis is older than 7 days
-   Content has changed since last analysis

**Returns:** `bool`

---

#### `getFocusKeywords(): array`

Get all focus keywords.

```php
$keywords = $post->getFocusKeywords();
// [
//     ['keyword' => 'laravel seo', 'is_primary' => true, 'synonyms' => ['laravel search']],
//     ['keyword' => 'meta tags', 'is_primary' => false],
// ]
```

**Returns:** `array<int, array{keyword: string, is_primary: bool, synonyms?: array}>`

---

#### `getPrimaryKeyword(): ?array`

Get the primary focus keyword.

```php
$primary = $post->getPrimaryKeyword();
// ['keyword' => 'laravel seo', 'is_primary' => true, 'synonyms' => [...]]
```

**Returns:** `?array` - Primary keyword or first keyword if none marked primary.

---

#### `hasExplicitSEO(): bool`

Check if SEO data has been explicitly set.

```php
if (!$post->hasExplicitSEO()) {
    // Show "Set up SEO" prompt in admin
}
```

**Returns:** `bool` - True if explicit SEO data exists.

---

### Customizable Methods

Override these in your model:

```php
public function getSEOTitle(): ?string
public function getSEODescription(): ?string
public function getSEOImage(): ?string
public function getContentForSEO(): string
public function getUrlForSEO(): string
public function getSEOContentFields(): array
```

---

### Query Scopes

#### `scopeWithLowSEOScore($query, int $threshold = 50)`

```php
$poorPosts = Post::withLowSEOScore(40)->get();
```

#### `scopeNeedingSEOAnalysis($query)`

```php
$needsWork = Post::needingSEOAnalysis()->get();
```

---

## SEOResolver Service

Resolves SEO data with proper precedence chain.

### Getting the Instance

```php
use Rankbeam\Seo\Services\SEOResolver;

$resolver = app(SEOResolver::class);
```

### Methods

#### `resolve(?Model $model = null, ?string $route = null, ?string $locale = null): SEOData`

Resolve SEO data with full precedence chain.

```php
// For a model
$seo = $resolver->resolve($post);

// For a specific route
$seo = $resolver->resolve($product, 'products.show', 'en');

// For current page without model
$seo = $resolver->resolve();
```

**Parameters:**
| Name | Type | Default | Description |
|------|------|---------|-------------|
| `$model` | `?Model` | `null` | Eloquent model |
| `$route` | `?string` | `null` | Route name (auto-detected if null) |
| `$locale` | `?string` | `null` | Locale code (uses app locale if null) |

**Returns:** `SEOData`

---

#### `resolveForRoute(string $routeName, ?string $locale = null): SEOData`

Resolve SEO for a named route without a model.

```php
$seo = $resolver->resolveForRoute('pages.about');
$seo = $resolver->resolveForRoute('blog.index', 'de');
```

**Parameters:**
| Name | Type | Default | Description |
|------|------|---------|-------------|
| `$routeName` | `string` | - | Laravel route name |
| `$locale` | `?string` | `null` | Locale code |

**Returns:** `SEOData`

---

#### `resolveWithOverrides(SEOData $base, array $overrides): SEOData`

Apply programmatic overrides to SEO data.

```php
// Make paginated pages noindex
if ($page > 1) {
    $seo = $resolver->resolveWithOverrides($seo, [
        'title' => "Page $page - {$seo->title}",
        'robots' => 'noindex,follow',
    ]);
}
```

**Parameters:**
| Name | Type | Default | Description |
|------|------|---------|-------------|
| `$base` | `SEOData` | - | Base SEO data to extend |
| `$overrides` | `array` | - | Key-value pairs to override |

**Returns:** `SEOData`

---

#### `resolveMany(iterable $models, ?string $locale = null): array`

Resolve SEO data for multiple models.

```php
$seoData = $resolver->resolveMany($posts);
// ['post_id' => SEOData, ...]
```

**Parameters:**
| Name | Type | Default | Description |
|------|------|---------|-------------|
| `$models` | `iterable<Model>` | - | Collection of models |
| `$locale` | `?string` | `null` | Locale code |

**Returns:** `array<int, SEOData>` - Indexed by model key.

---

## RedirectManager Service

Manage URL redirects.

### Getting the Instance

```php
use Rankbeam\Seo\Services\RedirectManager;

$manager = app(RedirectManager::class);
```

### Methods

#### `create(array $data): SEORedirect`

Create a new redirect.

```php
$redirect = $manager->create([
    'source_path' => '/old-page',
    'target_url' => '/new-page',
    'status_code' => 301,
    'is_regex' => false,
    'is_active' => true,
    'preserve_query' => true,
    'note' => 'Migrated from old site',
]);
```

**Parameters:**
| Key | Type | Default | Description |
|-----|------|---------|-------------|
| `source_path` | `string` | - | Source URL path |
| `target_url` | `string` | - | Target URL |
| `status_code` | `int` | `301` | HTTP status (301, 302, 307, 308) |
| `is_regex` | `bool` | `false` | Treat source as regex |
| `is_active` | `bool` | `true` | Enable/disable |
| `preserve_query` | `bool` | `true` | Keep query string |
| `note` | `?string` | `null` | Admin note |
| `created_by` | `?int` | `null` | User ID |

**Returns:** `SEORedirect`

**Throws:** `InvalidArgumentException` on validation failure.

---

#### `createFromPath(string $sourcePath, string $targetUrl, int $statusCode = 301): SEORedirect`

Create a simple redirect.

```php
$redirect = $manager->createFromPath('/old-url', '/new-url');
$redirect = $manager->createFromPath('/temp', '/destination', 302);
```

**Returns:** `SEORedirect`

---

#### `createFrom404(SEO404Log $log, string $targetUrl, int $statusCode = 301): SEORedirect`

Create a redirect from a 404 log entry.

```php
$log = SEO404Log::find(1);
$redirect = $manager->createFrom404($log, '/correct-page');
```

**Returns:** `SEORedirect`

---

#### `update(SEORedirect $redirect, array $data): SEORedirect`

Update an existing redirect.

```php
$redirect = $manager->update($redirect, [
    'target_url' => '/newer-page',
    'status_code' => 302,
]);
```

**Returns:** `SEORedirect`

---

#### `delete(SEORedirect $redirect): void`

Delete a redirect.

```php
$manager->delete($redirect);
```

---

#### `import(array $redirects): array`

Bulk import redirects.

```php
$result = $manager->import([
    ['source' => '/old-1', 'target' => '/new-1'],
    ['source' => '/old-2', 'target' => '/new-2', 'status' => 302],
]);

// $result = [
//     'created' => 2,
//     'updated' => 0,
//     'failed' => 0,
//     'errors' => [],
// ]
```

**Returns:** `array{created: int, updated: int, failed: int, errors: array}`

---

#### `export(): array`

Export all redirects.

```php
$data = $manager->export();
// Array of redirect data for backup
```

**Returns:** `array<int, array>`

---

#### `testRedirect(string $path): array`

Test what would happen for a given path.

```php
$result = $manager->testRedirect('/some-path');

// [
//     'matched' => true,
//     'redirect' => SEORedirect,
//     'target' => '/resolved-target',
//     'status_code' => 301,
// ]
```

**Returns:** `array{matched: bool, redirect: ?SEORedirect, target: ?string, status_code: ?int}`

---

#### `findLoops(): array`

Find redirect chains and loops.

```php
$problems = $manager->findLoops();

// [
//     ['redirect' => SEORedirect, 'issue' => 'loop', 'chain' => ['/a', '/b', '/a']],
//     ['redirect' => SEORedirect, 'issue' => 'long_chain', 'chain' => ['/a', '/b', '/c', '/d']],
// ]
```

**Returns:** `array<int, array{redirect: SEORedirect, issue: string, chain: array}>`

---

## CacheManager Service

Centralized cache management.

### Getting the Instance

```php
use Rankbeam\Seo\Services\CacheManager;

$cache = app(CacheManager::class);
```

### Methods

#### `warmCache(bool $verbose = false): array`

Warm all SEO caches.

```php
$stats = $cache->warmCache(verbose: true);

// [
//     'redirects' => 150,
//     'defaults' => 24,
//     'link_index' => 500,
//     'duration_ms' => 1234.56,
// ]
```

**Returns:** `array`

---

#### `clearAll(): array`

Clear all SEO caches.

```php
$result = $cache->clearAll();
// ['cleared' => ['redirects', 'defaults', 'analytics', ...]]
```

**Returns:** `array{cleared: array<string>}`

---

#### `clearForModel(Model $model): void`

Clear cache for a specific model.

```php
$cache->clearForModel($post);
```

---

#### `getStats(): array`

Get cache statistics.

```php
$stats = $cache->getStats();

// [
//     'store' => 'redis',
//     'prefix' => 'seo_',
//     'keys' => ['redirects' => true, ...],
//     'database_counts' => ['redirects' => 150, ...],
//     'recommendations' => [...],
// ]
```

**Returns:** `array`

---

#### Specific Cache Methods

```php
$cache->warmRedirectsCache();
$cache->warmDefaultsCache();
$cache->warmLinkIndexCache();

$cache->clearRedirectsCache();
$cache->clearDefaultsCache(?string $scope, ?string $locale);
$cache->clearAnalyticsCache(?string $path);
$cache->clearLinkIndexCache(?string $locale);
$cache->clearSitemapCache();
```

---

#### Database Maintenance

```php
// Purge old analytics (keep 90 days)
$deleted = $cache->purgeAnalyticsCache(daysToKeep: 90);

// Purge old 404 logs
$deleted = $cache->purge404Logs(daysToKeep: 30, keepUnresolved: true);

// Optimize table (MySQL/PostgreSQL)
$cache->optimizeAnalyticsTable();
```

---

## ContentAnalyzer Service

Analyze content for SEO.

### Getting the Instance

```php
use Rankbeam\Seo\Services\Analyzer\ContentAnalyzer;

$analyzer = app(ContentAnalyzer::class);
```

### Methods

#### `analyze(AnalysisContext $context): AnalysisReport`

Run full SEO analysis.

```php
use Rankbeam\Seo\Data\AnalysisContext;

$context = new AnalysisContext(
    content: $htmlContent,
    title: 'Page Title',
    description: 'Meta description',
    url: 'https://example.com/page',
    keyword: 'focus keyword',
    locale: 'en'
);

$report = $analyzer->analyze($context);

echo $report->score;          // 0-100
echo $report->grade;          // A, B, C, D, F
print_r($report->results);    // Individual rule results
print_r($report->recommendations); // Top improvements
```

**Returns:** `AnalysisReport`

---

## Data Objects

### SEOData

Immutable data object for resolved SEO.

```php
use Rankbeam\Seo\Data\SEOData;

// Properties
$seo->title;
$seo->description;
$seo->canonical;
$seo->robots;
$seo->ogTitle;
$seo->ogDescription;
$seo->ogImage;
$seo->ogType;
$seo->ogUrl;
$seo->ogSiteName;
$seo->twitterTitle;
$seo->twitterDescription;
$seo->twitterImage;
$seo->twitterCard;
$seo->twitterSite;
$seo->schemaJsonld;
$seo->locale;

// Methods
$newSeo = $seo->with('title', 'New Title');  // Returns new instance
$newSeo = $seo->merge($otherSeoData);        // Merge, other wins non-null
$array = $seo->toArray();
```

---

### AnalysisContext

Context for content analysis.

```php
use Rankbeam\Seo\Data\AnalysisContext;

$context = new AnalysisContext(
    content: '<html>...</html>',
    title: 'Page Title',
    description: 'Meta description',
    url: 'https://example.com/page',
    keyword: 'primary keyword',
    keywordSynonyms: ['synonym1', 'synonym2'],
    allKeywords: [
        ['keyword' => 'primary', 'is_primary' => true],
        ['keyword' => 'secondary', 'is_primary' => false],
    ],
    locale: 'en',
    model: $eloquentModel,
    seoMeta: $seoMetaModel
);
```

---

### RuleResult

Result from an analyzer rule.

```php
use Rankbeam\Seo\Data\RuleResult;

// Properties
$result->rule;           // RuleInterface
$result->passed;         // bool
$result->score;          // int (0-100)
$result->message;        // string
$result->recommendation; // ?string
$result->status;         // 'pass', 'warning', 'fail', 'skip'
```

---

## REST API Endpoints

Base URL: `/api/seo` (configurable)

### GET /api/seo/resolve

Resolve SEO data for a model or route.

**Query Parameters:**
| Name | Type | Required | Description |
|------|------|----------|-------------|
| `model_type` | `string` | No | Model class (e.g., `App\Models\Post`) |
| `model_id` | `int` | No | Model ID |
| `route` | `string` | No | Route name |
| `locale` | `string` | No | Locale code |

**Response:**

```json
{
    "data": {
        "title": "Page Title | Site Name",
        "description": "Meta description...",
        "canonical": "https://example.com/page",
        "robots": "index,follow",
        "og": {
            "title": "OG Title",
            "description": "OG Description",
            "image": "https://example.com/image.jpg",
            "type": "article",
            "url": "https://example.com/page"
        },
        "twitter": {
            "title": "Twitter Title",
            "description": "Twitter Description",
            "image": "https://example.com/image.jpg",
            "card": "summary_large_image"
        },
        "schema": [...]
    }
}
```

---

### POST /api/seo/analyze

Analyze content for SEO.

**Request Body:**

```json
{
    "content": "<html>...</html>",
    "title": "Page Title",
    "description": "Meta description",
    "url": "https://example.com/page",
    "keyword": "focus keyword",
    "locale": "en"
}
```

**Response:**

```json
{
    "data": {
        "score": 75,
        "grade": "C",
        "results": [
            {
                "rule_id": "keyword_in_title",
                "rule_name": "Keyword in Title",
                "category": "keyword",
                "status": "pass",
                "score": 100,
                "message": "Keyword found in title.",
                "recommendation": null
            }
        ],
        "recommendations": [
            "Add more internal links to improve navigation.",
            "Consider adding a table of contents for this long article."
        ],
        "stats": {
            "word_count": 1250,
            "reading_time_minutes": 5,
            "heading_count": 8,
            "image_count": 4,
            "link_count": 12
        }
    }
}
```

---

### GET /api/seo/redirects

List all redirects.

**Query Parameters:**
| Name | Type | Default | Description |
|------|------|---------|-------------|
| `active` | `bool` | - | Filter by active status |
| `is_regex` | `bool` | - | Filter by regex type |
| `per_page` | `int` | 25 | Items per page |

**Response:**

```json
{
    "data": [
        {
            "id": 1,
            "source_path": "/old-page",
            "target_url": "/new-page",
            "status_code": 301,
            "is_regex": false,
            "is_active": true,
            "hit_count": 150,
            "last_hit_at": "2024-01-15T10:30:00Z"
        }
    ],
    "meta": {
        "current_page": 1,
        "per_page": 25,
        "total": 100
    }
}
```

---

### POST /api/seo/redirects

Create a redirect.

**Request Body:**

```json
{
    "source_path": "/old-page",
    "target_url": "/new-page",
    "status_code": 301,
    "is_regex": false,
    "preserve_query": true,
    "note": "Migration redirect"
}
```

**Response:** `201 Created` with redirect data.

---

### GET /api/seo/404-logs

List 404 logs.

**Query Parameters:**
| Name | Type | Default | Description |
|------|------|---------|-------------|
| `status` | `string` | - | `new`, `ignored`, `redirected` |
| `min_hits` | `int` | - | Minimum hit count |
| `per_page` | `int` | 25 | Items per page |

---

### GET /api/seo/analytics/{path}

Get analytics for a path.

**Query Parameters:**
| Name | Type | Default | Description |
|------|------|---------|-------------|
| `period` | `string` | `7d` | Time period (`7d`, `30d`, `90d`) |
| `metrics` | `string` | - | Comma-separated metrics |

**Response:**

```json
{
    "data": {
        "path": "/blog/post-slug",
        "period": "7d",
        "metrics": {
            "pageviews": 1523,
            "users": 892,
            "avg_duration": 185.5,
            "bounce_rate": 45.2
        },
        "daily": {
            "2024-01-15": {"pageviews": 220, "users": 150},
            "2024-01-14": {"pageviews": 198, "users": 132}
        }
    }
}
```

---

## Error Handling

### HTTP Status Codes

| Code | Description |
|------|-------------|
| `200` | Success |
| `201` | Created |
| `400` | Bad Request - Invalid parameters |
| `404` | Not Found - Resource doesn't exist |
| `422` | Validation Error |
| `500` | Server Error |

### Error Response Format

```json
{
    "error": {
        "code": "VALIDATION_ERROR",
        "message": "The given data was invalid.",
        "details": {
            "source_path": ["The source path is required."],
            "target_url": ["The target url must be a valid URL."]
        }
    }
}
```

### Exception Types

| Exception | When Thrown |
|-----------|-------------|
| `InvalidArgumentException` | Invalid method arguments |
| `ModelNotFoundException` | Model not found |
| `ValidationException` | Request validation failure |
| `ConfigurationException` | Missing required configuration |

### Handling Exceptions

```php
use Rankbeam\Seo\Services\RedirectManager;

try {
    $redirect = $manager->create([
        'source_path' => '/loop',
        'target_url' => '/loop', // Would create a loop
    ]);
} catch (\InvalidArgumentException $e) {
    // Handle validation error
    logger()->warning('Redirect creation failed', [
        'message' => $e->getMessage(),
    ]);
}
```

---

## Rate Limits

API endpoints have the following default rate limits:

| Endpoint Group | Limit |
|---------------|-------|
| Read operations | 60/minute |
| Write operations | 30/minute |
| Analysis | 10/minute |

Configure in your route middleware:

```php
Route::middleware(['api', 'throttle:60,1'])->group(function () {
    // SEO API routes
});
```
