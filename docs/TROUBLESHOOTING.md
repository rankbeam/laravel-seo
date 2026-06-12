# Troubleshooting Guide

Common issues, solutions, and debugging tips for Laravel SEO Suite.

## Table of Contents

-   [Installation Issues](#installation-issues)
-   [Configuration Issues](#configuration-issues)
-   [SEO Data Not Showing](#seo-data-not-showing)
-   [Analysis Issues](#analysis-issues)
-   [Redirect Issues](#redirect-issues)
-   [Sitemap Issues](#sitemap-issues)
-   [Analytics Issues](#analytics-issues)
-   [Performance Issues](#performance-issues)
-   [Debug Mode](#debug-mode)
-   [Logging](#logging)

---

## Installation Issues

### Migration Errors

**Error:** `SQLSTATE[42S01]: Base table or view already exists`

**Solution:** Tables may already exist from a previous installation.

```bash
# Check existing tables
php artisan tinker
>>> Schema::hasTable('seo_meta')

# If needed, rollback and re-run
php artisan migrate:rollback --path=vendor/rankbeam/laravel-seo/database/migrations
php artisan migrate
```

---

**Error:** `Class 'Rankbeam\Seo\SEOServiceProvider' not found`

**Solution:** Clear composer autoload cache.

```bash
composer dump-autoload
php artisan package:discover
```

---

### Publish Errors

**Error:** `Unable to locate publishable resources`

**Solution:** The package may not be properly installed.

```bash
# Verify installation
composer show rankbeam/laravel-seo

# Re-install if needed
composer remove rankbeam/laravel-seo
composer require rankbeam/laravel-seo
```

---

## Configuration Issues

### Config Not Loading

**Symptoms:**
-   Default values being used instead of your configuration
-   `config('seo.site_name')` returns `null`

**Solutions:**

1. **Clear config cache:**
   ```bash
   php artisan config:clear
   ```

2. **Check config file exists:**
   ```bash
   ls config/seo.php
   ```

3. **Re-publish config:**
   ```bash
   php artisan vendor:publish --tag=seo-config --force
   ```

---

### Environment Variables Not Working

**Symptoms:** `.env` values not being read.

**Solutions:**

1. **Clear all caches:**
   ```bash
   php artisan config:clear
   php artisan cache:clear
   ```

2. **Check variable format:**
   ```env
   # Correct
   SEO_STACK=filament

   # Wrong (no quotes for simple values)
   SEO_STACK="filament"
   ```

3. **Restart server/queue:**
   ```bash
   php artisan queue:restart
   # Restart PHP-FPM or development server
   ```

---

## SEO Data Not Showing

### Meta Tags Not Rendering

**Symptoms:** `@seo($model)` outputs nothing.

**Checklist:**

1. **Model uses HasSEO trait:**
   ```php
   use Rankbeam\Seo\Traits\HasSEO;

   class Post extends Model
   {
       use HasSEO; // Must be present
   }
   ```

2. **Model has SEO record:**
   ```php
   // Check if SEO meta exists
   $post->seoMeta; // Should not be null
   ```

3. **TagRenderer is working:**
   ```php
   // Test directly
   $seo = $post->seoData();
   dd($seo); // Check data

   $html = app(TagRenderer::class)->render($seo);
   dd($html); // Check output
   ```

---

### Wrong Title/Description

**Symptoms:** Computed values used instead of saved values.

**Cause:** Precedence chain issue.

**Solution:** Check value sources:

```php
// Debug the resolution chain
$resolver = app(SEOResolver::class);

// Check each layer
$base = $resolver->buildBaseConfig('en');
$global = $resolver->applyGlobalDefaults($base, 'en');
$computed = $resolver->applyComputedValues($global, $model, 'en');
$explicit = $resolver->applyExplicitValues($computed, $model);

dd([
    'base' => $base->title,
    'global' => $global->title,
    'computed' => $computed->title,
    'explicit' => $explicit->title,
]);
```

---

### SEO Not Saving

**Symptoms:** `saveSEO()` doesn't persist data.

**Solutions:**

1. **Check database connection:**
   ```php
   try {
       $post->saveSEO(['title' => 'Test']);
       dd(DB::getQueryLog()); // Check queries
   } catch (\Exception $e) {
       dd($e->getMessage());
   }
   ```

2. **Check fillable/guarded:**
   ```php
   // SEOMeta should have proper fillable
   dd(SEOMeta::create(['title' => 'Test']));
   ```

3. **Check relationship:**
   ```php
   // Verify polymorphic relationship
   $post->seoMeta()->create(['title' => 'Test']);
   dd($post->seoMeta);
   ```

---

## Analysis Issues

### Score Always 0 or Null

**Symptoms:** `getSEOScore()` returns 0 or null.

**Solutions:**

1. **Run analysis manually:**
   ```php
   $post->analyzeForSEO();
   $post->refresh();
   dd($post->seoMeta->seo_score);
   ```

2. **Check content method:**
   ```php
   // Verify content is returned
   $content = $post->getContentForSEO();
   dd(strlen($content)); // Should be > 0
   ```

3. **Check keyword is set:**
   ```php
   $keywords = $post->getFocusKeywords();
   dd($keywords); // Need at least one keyword
   ```

---

### Analysis Job Not Running

**Symptoms:** Queue jobs dispatched but never processed.

**Solutions:**

1. **Check queue worker is running:**
   ```bash
   php artisan queue:work --verbose
   ```

2. **Check failed jobs:**
   ```bash
   php artisan queue:failed
   ```

3. **Run synchronously to see errors:**
   ```php
   use Rankbeam\Seo\Jobs\AnalyzeContentJob;

   // Run directly instead of dispatch
   AnalyzeContentJob::dispatchSync(Post::class, $post->id);
   ```

---

### Rules Not Running

**Symptoms:** Some rules always skip.

**Debug:**

```php
use Rankbeam\Seo\Services\Analyzer\ContentAnalyzer;
use Rankbeam\Seo\Data\AnalysisContext;

$analyzer = app(ContentAnalyzer::class);

$context = new AnalysisContext(
    content: $post->content,
    title: $post->title,
    url: $post->getUrlForSEO(),
    keyword: 'test keyword',
    locale: 'en'
);

$report = $analyzer->analyze($context);

// Check individual rule results
foreach ($report->results as $result) {
    echo "{$result->rule->getId()}: {$result->status} - {$result->message}\n";
}
```

---

## Redirect Issues

### Redirects Not Working

**Symptoms:** URLs that should redirect return 404.

**Checklist:**

1. **Middleware is registered:**
   ```php
   // Check kernel or route
   Route::middleware(['web', 'seo.redirect'])->group(...);
   ```

2. **Redirect is active:**
   ```php
   $redirect = SEORedirect::where('source_path', '/old-url')->first();
   dd($redirect->is_active); // Should be true
   ```

3. **Test redirect matching:**
   ```php
   $manager = app(RedirectManager::class);
   $result = $manager->testRedirect('/old-url');
   dd($result);
   ```

4. **Clear redirect cache:**
   ```bash
   php artisan seo:cache --clear
   ```

---

### Redirect Loop Detected

**Symptoms:** Browser shows "too many redirects" error.

**Solution:**

```php
$manager = app(RedirectManager::class);
$loops = $manager->findLoops();

foreach ($loops as $loop) {
    echo "Redirect #{$loop['redirect']->id}: {$loop['issue']}\n";
    echo "Chain: " . implode(' → ', $loop['chain']) . "\n";
}
```

---

### Regex Redirects Not Matching

**Symptoms:** Regex patterns don't match expected URLs.

**Debug:**

```php
$redirect = SEORedirect::find(1);
$pattern = '#^' . $redirect->source_path . '$#';

// Test pattern
$testUrls = ['/blog/2024/01/post', '/blog/old-post'];

foreach ($testUrls as $url) {
    $matches = preg_match($pattern, $url, $captured);
    echo "$url: " . ($matches ? 'MATCH' : 'NO MATCH') . "\n";
    if ($matches) {
        print_r($captured);
    }
}
```

---

## Sitemap Issues

### Sitemap Not Generating

**Symptoms:** `php artisan seo:sitemap` produces no output.

**Solutions:**

1. **Check models are configured:**
   ```php
   // config/seo.php
   'sitemap' => [
       'models' => [
           \App\Models\Post::class => ['priority' => 0.8],
       ],
   ],
   ```

2. **Check model implements requirements:**
   ```php
   // Model must have getUrlForSEO() method
   $post = Post::first();
   dd($post->getUrlForSEO());
   ```

3. **Check disk is writable:**
   ```php
   $disk = Storage::disk(config('seo.sitemap.disk'));
   dd($disk->put('test.txt', 'test')); // Should return true
   ```

---

### Sitemap Has Wrong URLs

**Symptoms:** URLs in sitemap are incorrect.

**Solution:** Override `getUrlForSEO()` in your model:

```php
public function getUrlForSEO(): string
{
    return route('posts.show', [
        'slug' => $this->slug,
    ]);
}
```

---

## Analytics Issues

### GA4 Not Connecting

**Symptoms:** Analytics data is empty.

**Checklist:**

1. **Check credentials file exists:**
   ```php
   $path = config('seo.analytics.credentials_path');
   dd(file_exists($path));
   ```

2. **Check property ID:**
   ```php
   $propertyId = config('seo.analytics.property_id');
   dd($propertyId); // Should be numeric
   ```

3. **Test connection:**
   ```php
   $ga4 = app(GA4Service::class);
   dd($ga4->isConfigured());
   ```

4. **Check service account permissions:**
   - Go to GA4 Admin → Property Access Management
   - Verify service account email has Viewer role

---

### Analytics Data Stale

**Symptoms:** Data doesn't update.

**Solutions:**

```bash
# Clear analytics cache
php artisan seo:cache --clear --only=analytics

# Force sync
php artisan seo:sync-analytics --days=7
```

---

## Performance Issues

### Slow Page Loads

**Symptoms:** Pages with SEO data load slowly.

**Solutions:**

1. **Enable caching:**
   ```php
   // config/seo.php
   'cache' => [
       'store' => 'redis', // Use Redis, not file
   ],
   ```

2. **Warm caches:**
   ```bash
   php artisan seo:cache --warm
   ```

3. **Use eager loading:**
   ```php
   $posts = Post::with('seoMeta')->paginate(20);
   ```

4. **Check for N+1 queries:**
   ```php
   DB::enableQueryLog();
   // Your code
   dd(DB::getQueryLog());
   ```

---

### Memory Exhaustion in Scanner

**Symptoms:** Scanner jobs fail with memory errors.

**Solutions:**

1. **Reduce batch size:**
   ```php
   // config/seo.php
   'scanner' => [
       'batch_size' => 25, // Lower from 50
   ],
   ```

2. **Increase memory limit:**
   ```bash
   php artisan queue:work --memory=512
   ```

3. **Run incremental scans:**
   ```bash
   php artisan seo:scan --type=incremental
   ```

---

## Debug Mode

### Enable Debug Logging

Add to your `.env`:

```env
SEO_DEBUG=true
LOG_LEVEL=debug
```

Create a debug config override:

```php
// config/seo.php
'debug' => env('SEO_DEBUG', false),
```

### Debug in Code

```php
use Illuminate\Support\Facades\Log;

// In your code
if (config('seo.debug')) {
    Log::debug('SEO Resolution', [
        'model' => get_class($model),
        'id' => $model->id,
        'resolved_title' => $seoData->title,
    ]);
}
```

### Dump SEO Data

```blade
@if(config('app.debug'))
<pre style="display:none">
{{ json_encode($post->seoData()->toArray(), JSON_PRETTY_PRINT) }}
</pre>
@endif
```

---

## Logging

### Log Channels

The package logs to your default channel. Create a dedicated channel:

```php
// config/logging.php
'channels' => [
    'seo' => [
        'driver' => 'daily',
        'path' => storage_path('logs/seo.log'),
        'level' => 'debug',
        'days' => 14,
    ],
],
```

### Important Log Messages

| Level | Message | Action |
|-------|---------|--------|
| `error` | Job failures | Check queue/database |
| `warning` | Analysis issues | Review content |
| `info` | Cache operations | Normal operation |
| `debug` | Resolution details | Development only |

### Finding Relevant Logs

```bash
# Search for SEO-related errors
grep -r "SEO\|seo:" storage/logs/laravel.log | tail -50

# Monitor in real-time
tail -f storage/logs/laravel.log | grep --line-buffered "SEO"
```

---

## Quick Diagnostics

### Health Check Command

```bash
php artisan seo:health
```

Output:

```
✓ Configuration loaded
✓ Database tables exist
✓ Cache store accessible
✓ Queue connection working
✗ GA4 not configured (optional)

Summary: 4/5 checks passed
```

### Manual Health Check

```php
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

$checks = [
    'Config loaded' => config('seo.site_name') !== null,
    'seo_meta table' => Schema::hasTable('seo_meta'),
    'seo_redirects table' => Schema::hasTable('seo_redirects'),
    'Cache working' => Cache::put('seo_test', true, 60) && Cache::get('seo_test'),
    'DB connection' => DB::select('SELECT 1'),
];

foreach ($checks as $name => $result) {
    echo ($result ? '✓' : '✗') . " $name\n";
}
```

---

## Getting Help

### Before Reporting Issues

1. **Check this guide** for your specific issue
2. **Update to latest version:** `composer update rankbeam/laravel-seo`
3. **Clear all caches:** `php artisan cache:clear && php artisan config:clear`
4. **Check Laravel logs:** `storage/logs/laravel.log`

### Information to Include

When reporting issues, include:

```php
// Run this and include output
echo "PHP: " . PHP_VERSION . "\n";
echo "Laravel: " . app()->version() . "\n";
echo "Package: " . composer_version('rankbeam/laravel-seo') . "\n";
echo "Stack: " . config('seo.stack') . "\n";
echo "Cache: " . config('seo.cache.store') . "\n";
echo "Database: " . DB::connection()->getDriverName() . "\n";
```

### Support Channels

-   **GitHub Issues:** Bug reports and feature requests
-   **GitHub Discussions:** Questions and community help
-   **Security Issues:** security@example.com (private disclosure)
