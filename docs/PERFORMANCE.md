# Performance Optimization Guide

This document outlines the performance optimizations implemented in the Laravel SEO Suite and provides a checklist for production deployments.

## Table of Contents

-   [Cache Management](#cache-management)
-   [Database Optimization](#database-optimization)
-   [Job Optimization](#job-optimization)
-   [Eager Loading](#eager-loading)
-   [Configuration Caching](#configuration-caching)
-   [Production Checklist](#production-checklist)

---

## Cache Management

### CacheManager Service

The `CacheManager` service provides centralized cache management with methods for warming, clearing, and monitoring caches.

```php
use Rankbeam\Seo\Services\CacheManager;

$cache = app(CacheManager::class);

// Warm all caches (run after deployment)
$stats = $cache->warmCache(verbose: true);

// Clear all SEO caches
$cache->clearAll();

// Clear cache for a specific model
$cache->clearForModel($post);

// Get cache statistics
$stats = $cache->getStats();
```

### Cache Keys Reference

| Key Pattern                          | Description           | TTL      | Warmed |
| ------------------------------------ | --------------------- | -------- | ------ |
| `seo_redirects`                      | All active redirects  | 1 hour   | ✅     |
| `seo_defaults:{scope}:{locale}`      | SEO defaults by scope | 1 hour   | ✅     |
| `seo_link_index:{locale}`            | Internal links index  | 6 hours  | ✅     |
| `seo_resolved:{model}:{id}:{locale}` | Resolved SEO data     | 30 min   | ❌     |
| `seo_sitemap_generated`              | Sitemap timestamp     | 24 hours | ❌     |
| `seo_stem_{locale}:{word}`           | Stemmed words         | 24 hours | ❌     |

### Recommended Cache Stores

For production, use Redis or Memcached:

```php
// config/seo.php
'cache' => [
    'store' => env('SEO_CACHE_STORE', 'redis'),
    'prefix' => 'seo_',
],
```

### Cache Warming Script

Add to your deployment pipeline:

```bash
# After deployment
php artisan seo:cache --warm

# Or via scheduler (routes/console.php)
Schedule::command('seo:cache --warm')->dailyAt('03:00');
```

---

## Database Optimization

### Indexes

The package includes optimized indexes for common query patterns. Run the migration:

```bash
php artisan migrate
```

### Index Summary

| Table                 | Index                           | Columns                  | Purpose                 |
| --------------------- | ------------------------------- | ------------------------ | ----------------------- |
| `seo_meta`            | `seo_meta_seoable_type_idx`     | `seoable_type`           | Model-type queries      |
| `seo_meta`            | `seo_meta_type_locale_idx`      | `seoable_type, locale`   | Locale-filtered queries |
| `seo_meta`            | `seo_meta_score_analyzed_idx`   | `seo_score, analyzed_at` | Dashboard queries       |
| `seo_redirects`       | `seo_redirects_hit_count_idx`   | `hit_count`              | Popular redirects       |
| `seo_redirects`       | `seo_redirects_last_hit_idx`    | `last_hit_at`            | Activity monitoring     |
| `seo_404_logs`        | `seo_404_logs_status_date_idx`  | `status, last_seen_at`   | Status filtering        |
| `seo_analytics_cache` | `seo_analytics_metric_date_idx` | `metric_type, date`      | Aggregations            |

### Query Optimization Tips

1. **Use eager loading** when displaying SEO data in lists:

    ```php
    $posts = Post::with('seoMeta')->paginate(20);
    ```

2. **Use chunking** for bulk operations:

    ```php
    Post::chunk(100, function ($posts) {
        foreach ($posts as $post) {
            $post->analyzeForSEO();
        }
    });
    ```

3. **Index frequently filtered columns** in your models:
    ```php
    // If you filter by category often
    Schema::table('posts', function ($table) {
        $table->index('category_id');
    });
    ```

### Database Maintenance

Periodic cleanup commands:

```bash
# Purge old analytics data (keep 90 days)
php artisan seo:analytics:purge --days=90

# Purge old 404 logs (keep 30 days, preserve unresolved)
php artisan seo:404:purge --days=30 --keep-unresolved

# Optimize analytics table (MySQL)
php artisan seo:db:optimize
```

---

## Job Optimization

### Batch Processing

The SEO suite uses Laravel's job batching for bulk operations:

```php
// ScanSitewideJob uses Bus::batch() for parallel processing
ScanSitewideJob::dispatch(type: 'full');

// Configure batch size in config/seo.php
'scanner' => [
    'batch_size' => 50,  // Adjust based on server capacity
],
```

### Rate Limiting for External APIs

The `SyncAnalyticsJob` implements rate limiting for GA4 API calls:

```php
// 500ms delay between API requests (configurable)
protected int $rateLimitDelay = 500;
```

### Memory-Efficient Chunking

For large datasets, the package uses cursor-based iteration:

```php
// BuildLinkIndexJob uses chunkById for memory efficiency
$modelClass::query()->chunkById(100, function ($items) {
    // Process in batches
});
```

### Queue Configuration

Recommended queue settings for production:

```php
// config/seo.php
'scanner' => [
    'queue' => 'seo-scanner',  // Dedicated queue
],
'analytics' => [
    'queue' => 'seo-analytics',
],
```

```bash
# Run dedicated workers
php artisan queue:work --queue=seo-scanner --tries=3 --timeout=3600
php artisan queue:work --queue=seo-analytics --tries=3 --timeout=1800
```

### Job Timeouts and Retries

| Job                  | Timeout | Tries | Unique For |
| -------------------- | ------- | ----- | ---------- |
| `ScanSitewideJob`    | 3600s   | 3     | 1 hour     |
| `ScanPageJob`        | 120s    | 3     | N/A        |
| `SyncAnalyticsJob`   | 1800s   | 3     | 1 hour     |
| `BuildLinkIndexJob`  | 1800s   | 3     | 1 hour     |
| `GenerateSitemapJob` | 600s    | 3     | 1 hour     |

---

## Eager Loading

### Common N+1 Query Patterns

Avoid these common N+1 patterns:

```php
// ❌ Bad: N+1 query
$posts = Post::all();
foreach ($posts as $post) {
    echo $post->seoMeta->title;  // 1 query per post
}

// ✅ Good: Eager loading
$posts = Post::with('seoMeta')->get();
foreach ($posts as $post) {
    echo $post->seoMeta->title;  // No additional queries
}
```

### Model Scopes for Common Eager Loads

Define scopes in your models:

```php
class Post extends Model
{
    use HasSEO;

    public function scopeWithSEO($query)
    {
        return $query->with('seoMeta');
    }

    public function scopeWithSEOAndAuthor($query)
    {
        return $query->with(['seoMeta', 'author']);
    }
}

// Usage
$posts = Post::withSEO()->paginate(20);
```

### Global Scope for Admin Panels

For admin panels where SEO data is always needed:

```php
// In your Filament resource
public static function getEloquentQuery(): Builder
{
    return parent::getEloquentQuery()->with('seoMeta');
}
```

---

## Configuration Caching

### Enable Config Caching

The SEO config is designed to be cache-safe:

```bash
# Cache all config
php artisan config:cache

# Clear and re-cache
php artisan config:clear && php artisan config:cache
```

### Environment Variables

All dynamic values use environment variables:

```env
# config/seo.php values
SEO_STACK=filament
SEO_CACHE_STORE=redis
SEO_DEFAULT_OG_IMAGE=/images/og-default.jpg
SEO_TWITTER_SITE=yourusername

# Feature flags
SEO_ANALYTICS_ENABLED=true
SEO_SITEMAP_ENABLED=true
SEO_REDIRECTS_ENABLED=true
SEO_SCANNER_ENABLED=true
SEO_404_MONITOR_ENABLED=true

# GA4 Integration
SEO_GA4_ENABLED=true
SEO_GA4_PROPERTY_ID=123456789
SEO_GA4_CACHE_TTL=3600
```

### Runtime Config Overrides

Avoid `config()` calls at runtime where possible. The package caches config values during service provider boot.

---

## Production Checklist

### Pre-Deployment

-   [ ] **Cache Configuration**

    ```bash
    php artisan config:cache
    ```

-   [ ] **Run Migrations**

    ```bash
    php artisan migrate --force
    ```

-   [ ] **Publish Assets** (if using frontend components)
    ```bash
    php artisan vendor:publish --tag=seo-assets --force
    ```

### Post-Deployment

-   [ ] **Warm Caches**

    ```bash
    php artisan seo:cache --warm
    ```

-   [ ] **Generate Sitemap**

    ```bash
    php artisan seo:sitemap
    ```

-   [ ] **Restart Queue Workers**
    ```bash
    php artisan queue:restart
    ```

### Monitoring Setup

-   [ ] **Enable Queue Monitoring**

    -   Laravel Horizon recommended for Redis queues
    -   Monitor `seo-scanner` and `seo-analytics` queues

-   [ ] **Set Up Alerts**

    -   Failed job threshold > 5%
    -   Queue length > 1000 jobs
    -   Cache hit rate < 90%

-   [ ] **Log Monitoring**
    -   Watch for `CacheManager` log entries
    -   Monitor GA4 API error rates

### Scheduled Tasks

Add to `routes/console.php`:

```php
use Illuminate\Support\Facades\Schedule;

// Warm caches daily at 3 AM
Schedule::command('seo:cache --warm')->dailyAt('03:00');

// Generate sitemap daily at 4 AM
Schedule::command('seo:sitemap')->dailyAt('04:00');

// Sync analytics daily at 5 AM
Schedule::job(new \Rankbeam\Seo\Jobs\SyncAnalyticsJob(days: 7))->dailyAt('05:00');

// Scan for SEO issues weekly
Schedule::command('seo:scan --type=incremental')->weeklyOn(1, '06:00');

// Purge old data monthly
Schedule::command('seo:analytics:purge --days=90')->monthlyOn(1, '07:00');
Schedule::command('seo:404:purge --days=30')->monthlyOn(1, '07:15');
```

### Resource Requirements

| Component       | CPU    | Memory   | Storage             |
| --------------- | ------ | -------- | ------------------- |
| Web Server      | Low    | Low      | -                   |
| Scanner Workers | Medium | Medium   | -                   |
| Analytics Sync  | Low    | Low      | -                   |
| Database        | Medium | Medium   | ~1MB per 1000 pages |
| Redis Cache     | Low    | 50-200MB | -                   |

### Performance Benchmarks

Expected performance on standard hardware:

| Operation                      | Target Time |
| ------------------------------ | ----------- |
| Redirect lookup (cached)       | < 5ms       |
| SEO data resolution            | < 20ms      |
| Page scan (single)             | < 2s        |
| Sitemap generation (1000 URLs) | < 30s       |
| Full site scan (1000 pages)    | < 30 min    |

---

## Troubleshooting

### Slow Redirect Matching

**Symptoms:** High latency on pages with redirects

**Solutions:**

1. Check if cache is warmed: `php artisan seo:cache --stats`
2. Reduce regex redirects (exact matches are faster)
3. Use Redis instead of file cache

### Memory Issues in Scanner

**Symptoms:** Jobs failing with memory exhaustion

**Solutions:**

1. Reduce batch size: `config('seo.scanner.batch_size', 25)`
2. Increase worker memory limit
3. Use `--memory` flag: `php artisan queue:work --memory=512`

### Analytics Sync Timeouts

**Symptoms:** `SyncAnalyticsJob` timing out

**Solutions:**

1. Reduce paths per job: `config('seo.analytics.max_sync_paths', 200)`
2. Increase rate limit delay
3. Run during off-peak hours

### Database Slow Queries

**Symptoms:** Dashboard loading slowly

**Solutions:**

1. Run performance migration: `php artisan migrate`
2. Check indexes exist: `SHOW INDEX FROM seo_meta`
3. Analyze queries with `EXPLAIN`
4. Consider read replicas for analytics queries

---

## API Reference

### Console Commands

| Command               | Description                  |
| --------------------- | ---------------------------- |
| `seo:cache --warm`    | Warm all SEO caches          |
| `seo:cache --clear`   | Clear all SEO caches         |
| `seo:cache --stats`   | Show cache statistics        |
| `seo:scan`            | Run sitewide SEO scan        |
| `seo:sitemap`         | Generate XML sitemap         |
| `seo:index-links`     | Rebuild internal links index |
| `seo:analytics:sync`  | Sync GA4 analytics data      |
| `seo:analytics:purge` | Purge old analytics data     |
| `seo:404:purge`       | Purge old 404 logs           |

### CacheManager Methods

```php
$cache = app(CacheManager::class);

// Warming
$cache->warmCache(verbose: true);
$cache->warmRedirectsCache();
$cache->warmDefaultsCache();
$cache->warmLinkIndexCache();

// Clearing
$cache->clearAll();
$cache->clearForModel($model);
$cache->clearRedirectsCache();
$cache->clearDefaultsCache(scope: 'App\Models\Post', locale: 'en');
$cache->clearAnalyticsCache(path: '/blog/post-1');
$cache->clearLinkIndexCache(locale: 'en');

// Retrieval (with auto-warming)
$redirects = $cache->getRedirects();
$default = $cache->getDefault('global', 'en');
$linkIndex = $cache->getLinkIndex('en');

// Statistics
$stats = $cache->getStats();

// Maintenance
$cache->purgeAnalyticsCache(daysToKeep: 90);
$cache->purge404Logs(daysToKeep: 30, keepUnresolved: true);
$cache->optimizeAnalyticsTable();
```
