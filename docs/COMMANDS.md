# Console Commands Reference

Complete reference for all Artisan commands provided by Laravel SEO Suite.

## Table of Contents

-   [seo:install](#seoinstall)
-   [seo:scan](#seoscan)
-   [seo:health](#seohealth)
-   [seo:cache](#seocache)
-   [seo:sitemap](#seositemap)
-   [seo:sync-analytics](#seosync-analytics)
-   [Scheduling Commands](#scheduling-commands)

---

## seo:install

Interactive installer for the SEO suite.

### Signature

```bash
php artisan seo:install
```

### Description

Runs an interactive installation wizard that:

1. Publishes the configuration file
2. Runs database migrations
3. Asks which frontend stack you're using
4. Publishes stack-specific components
5. Optionally installs required packages

### Example

```bash
$ php artisan seo:install

 Welcome to Laravel SEO Suite Installer!

 Which frontend stack are you using?
  [0] Filament
  [1] Livewire
  [2] Vue (Inertia)
  [3] React (Inertia)
  [4] API Only
 > 0

 Publishing configuration...
 Running migrations...
 Publishing Filament components...

 ✓ Installation complete!
```

---

## seo:scan

Run SEO scans to analyze content and update scores.

### Signature

```bash
php artisan seo:scan
    {--type=full : Scan type (full, incremental)}
    {--model= : Specific model class to scan}
    {--id= : Specific model ID to scan (requires --model)}
    {--queue : Queue the scan job instead of running directly}
    {--dry-run : Show what would be scanned without scanning}
```

### Options

| Option        | Description                                    | Default |
| ------------- | ---------------------------------------------- | ------- |
| `--type`      | Scan type: `full` or `incremental`            | `full`  |
| `--model`     | Fully qualified model class to scan           | -       |
| `--id`        | Specific model ID (requires `--model`)        | -       |
| `--queue`     | Dispatch jobs to queue instead of sync        | false   |
| `--dry-run`   | Preview without executing                      | false   |

### Scan Types

| Type          | Description                                    |
| ------------- | ---------------------------------------------- |
| `full`        | Scan all SEO-enabled models regardless of status |
| `incremental` | Only scan models that need analysis (never analyzed, stale, or changed) |

### Examples

**Full sitewide scan:**

```bash
php artisan seo:scan
```

**Incremental scan (recommended for daily use):**

```bash
php artisan seo:scan --type=incremental
```

**Scan a specific model class:**

```bash
php artisan seo:scan --model="App\Models\Post"
```

**Scan a single model instance:**

```bash
php artisan seo:scan --model="App\Models\Post" --id=123
```

**Queue the scan for background processing:**

```bash
php artisan seo:scan --queue
```

**Preview what would be scanned:**

```bash
php artisan seo:scan --dry-run
```

### Output Example

```
Starting full sitewide SEO scan

Scan Plan:

| Model    | Count | Scope |
|----------|-------|-------|
| Post     | 150   | All   |
| Page     | 25    | All   |
| Product  | 500   | All   |

Total items to scan: 675

 Proceed with scanning 675 items? (yes/no) [yes]:
 > yes

 150/675 [======>-----------] 22% - Scanning Post...

Scan Summary:

| Metric           | Count |
|------------------|-------|
| Total Scanned    | 675   |
| Good (70+)       | 450   |
| Needs Work (50-69)| 175  |
| Poor (<50)       | 50    |
| Errors           | 0     |

Score Distribution:
  ■ Good: 67%  ■ Needs Work: 26%  ■ Poor: 7%
```

---

## seo:health

Check the health of the SEO suite installation.

### Signature

```bash
php artisan seo:health
    {--json : Output results as JSON}
```

### Options

| Option   | Description                        |
| -------- | ---------------------------------- |
| `--json` | Output results as JSON for monitoring |

### Checks Performed

| Category      | Check                          | Critical |
| ------------- | ------------------------------ | -------- |
| Database      | Required tables exist          | Yes      |
| Configuration | Config file loaded             | Yes      |
| Configuration | Valid stack configured         | Yes      |
| Configuration | Site name set                  | No       |
| Configuration | Cache store optimal            | No       |
| Cache         | Cache read/write working       | Yes      |
| Cache         | SEO caches warmed              | No       |
| Queue         | Queue connection configured    | No       |
| Queue         | Failed jobs count              | No       |
| Packages      | Required packages installed    | Yes      |
| Packages      | Optional packages status       | No       |
| Analytics     | GA4 configured (if enabled)    | No       |
| Data          | Orphaned records check         | No       |
| Data          | Unanalyzed content count       | No       |

### Exit Codes

| Code | Meaning                        |
| ---- | ------------------------------ |
| `0`  | All critical checks passed     |
| `1`  | One or more critical checks failed |

### Examples

**Standard health check:**

```bash
php artisan seo:health
```

**JSON output for monitoring:**

```bash
php artisan seo:health --json
```

**Use in scripts:**

```bash
php artisan seo:health || echo "Health check failed!"
```

### Output Example (Pretty)

```
╔══════════════════════════════════════════════╗
║        SEO Suite Health Check              ║
╚══════════════════════════════════════════════╝

Database:
  ✓ Table 'seo_meta' exists
  ✓ Table 'seo_defaults' exists
  ✓ Table 'seo_redirects' exists
  ✓ Table 'seo_404_logs' exists

Configuration:
  ✓ Configuration file loaded
  ✓ Stack configured: filament
  ✓ Site name: My Website
  ! Cache store: file (consider redis/memcached for production)

Cache:
  ✓ Cache read/write working
  ! SEO caches not warmed (run: php artisan seo:cache --warm)

Queue:
  ✓ Queue connection: redis
  ✓ Queue appears healthy

Packages:
  ✓ wamania/php-stemmer installed
  ✓ spatie/laravel-sitemap installed
  ○ google/apiclient not installed (optional)

Data:
  ✓ Data integrity check passed (1250 SEO records)
  ! Content needs analysis: 45 never analyzed, 120 stale (>30 days)

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

Health Check Passed with Warnings: 3 warning(s)

  Passed: 12  Warnings: 3  Failed: 0
```

### Output Example (JSON)

```json
{
    "status": "healthy",
    "summary": {
        "passed": 12,
        "failed": 0,
        "warnings": 3
    },
    "checks": {
        "table_seo_meta": {
            "status": "pass",
            "message": "Table 'seo_meta' exists",
            "critical": true
        },
        "config_loaded": {
            "status": "pass",
            "message": "Configuration file loaded",
            "critical": true
        },
        "cache_warmed": {
            "status": "warn",
            "message": "SEO caches not warmed",
            "critical": false
        }
    },
    "timestamp": "2024-01-15T10:30:00+00:00"
}
```

---

## seo:cache

Manage SEO caches.

### Signature

```bash
php artisan seo:cache
    {--warm : Warm all SEO caches}
    {--clear : Clear all SEO caches}
    {--stats : Show cache statistics}
    {--only= : Only warm/clear specific cache}
    {--model= : Model class for model-specific operations}
    {--id= : Model ID for model-specific operations}
```

### Options

| Option    | Description                              |
| --------- | ---------------------------------------- |
| `--warm`  | Preload caches for better performance    |
| `--clear` | Invalidate all SEO caches                |
| `--stats` | Display cache statistics                 |
| `--only`  | Target specific cache: `redirects`, `defaults`, `link-index`, `analytics`, `sitemap` |
| `--model` | Model class for targeted clearing        |
| `--id`    | Model ID for targeted clearing           |

### Examples

**Warm all caches:**

```bash
php artisan seo:cache --warm
```

**Clear all caches:**

```bash
php artisan seo:cache --clear
```

**Show statistics:**

```bash
php artisan seo:cache --stats
```

**Warm only redirects cache:**

```bash
php artisan seo:cache --warm --only=redirects
```

**Clear cache for specific model:**

```bash
php artisan seo:cache --clear --model="App\Models\Post" --id=123
```

### Output Example (Stats)

```
SEO Cache Statistics

Configuration:
| Setting | Value |
|---------|-------|
| Store   | redis |
| Prefix  | seo_  |

Cache Keys Status:
| Key             | Status      |
|-----------------|-------------|
| redirects       | ✓ Cached    |
| link_index_en   | ✓ Cached    |
| defaults_global | ✗ Not cached|

Database Counts:
| Table              | Count  |
|--------------------|--------|
| redirects          | 150    |
| active_redirects   | 142    |
| defaults           | 24     |
| seo_meta           | 1,250  |
| analytics_entries  | 45,000 |
| link_index_entries | 1,100  |
| 404_logs           | 2,500  |

Recommendations:
  ⚠ Redirects cache is empty - run `php artisan seo:cache --warm`
```

---

## seo:sitemap

Generate XML sitemap.

### Signature

```bash
php artisan seo:sitemap
    {--ping : Ping search engines after generation}
```

### Options

| Option   | Description                              |
| -------- | ---------------------------------------- |
| `--ping` | Notify Google and Bing of sitemap update |

### Examples

**Generate sitemap:**

```bash
php artisan seo:sitemap
```

**Generate and ping search engines:**

```bash
php artisan seo:sitemap --ping
```

### Output Example

```
Generating sitemap...

Processing models:
  ✓ Post: 150 URLs
  ✓ Page: 25 URLs
  ✓ Product: 500 URLs

Adding static URLs: 5

Total URLs: 680
Sitemap written to: public/sitemap.xml

✓ Sitemap generated successfully!
```

---

## seo:sync-analytics

Sync analytics data from Google Analytics 4.

### Signature

```bash
php artisan seo:sync-analytics
    {--days=7 : Number of days to sync}
    {--path= : Specific path to sync (optional)}
    {--queue : Queue the job instead of running directly}
    {--stats : Show cache statistics}
```

### Options

| Option    | Description                           | Default |
| --------- | ------------------------------------- | ------- |
| `--days`  | Number of days of data to fetch       | 7       |
| `--path`  | Specific URL path to sync             | -       |
| `--queue` | Dispatch to queue                     | false   |
| `--stats` | Show analytics cache statistics       | false   |

### Requirements

-   `google/apiclient` package installed
-   GA4 credentials configured
-   Analytics feature enabled

### Examples

**Sync last 7 days:**

```bash
php artisan seo:sync-analytics
```

**Sync last 30 days:**

```bash
php artisan seo:sync-analytics --days=30
```

**Sync specific path:**

```bash
php artisan seo:sync-analytics --path=/blog/my-post
```

**Queue the sync:**

```bash
php artisan seo:sync-analytics --queue
```

**Show cache statistics:**

```bash
php artisan seo:sync-analytics --stats
```

### Output Example

```
Syncing analytics for last 7 days...

Discovering paths...
Found 150 paths to sync.

 150/150 [============================] 100%

✓ Analytics sync complete!
  Synced: 150 paths
  Errors: 0
```

---

## Scheduling Commands

### Recommended Schedule

Add to `routes/console.php`:

```php
use Illuminate\Support\Facades\Schedule;

// Daily incremental scan at 2 AM
Schedule::command('seo:scan --type=incremental')
    ->dailyAt('02:00')
    ->withoutOverlapping();

// Weekly full scan on Sunday at 3 AM
Schedule::command('seo:scan --type=full')
    ->weeklyOn(0, '03:00')
    ->withoutOverlapping();

// Warm caches daily at 4 AM
Schedule::command('seo:cache --warm')
    ->dailyAt('04:00');

// Generate sitemap daily at 5 AM
Schedule::command('seo:sitemap')
    ->dailyAt('05:00');

// Sync analytics daily at 6 AM (if enabled)
Schedule::command('seo:sync-analytics --days=7')
    ->dailyAt('06:00')
    ->when(fn () => config('seo.analytics.enabled'));

// Health check every hour (log warnings)
Schedule::command('seo:health')
    ->hourly()
    ->onFailure(function () {
        // Send alert
    });
```

### Monitoring Integration

```bash
# Cron job with alerting
0 * * * * cd /path/to/app && php artisan seo:health --json >> /var/log/seo-health.log 2>&1

# Check exit code for monitoring systems
php artisan seo:health && echo "OK" || echo "FAILED"
```

---

## Command Summary

| Command                | Description                    | Frequency    |
| ---------------------- | ------------------------------ | ------------ |
| `seo:install`          | Initial setup                  | Once         |
| `seo:scan`             | Analyze content                | Daily/Weekly |
| `seo:health`           | Check installation health      | Hourly       |
| `seo:cache --warm`     | Preload caches                 | After deploy |
| `seo:cache --clear`    | Invalidate caches              | As needed    |
| `seo:sitemap`          | Generate XML sitemap           | Daily        |
| `seo:sync-analytics`   | Fetch GA4 data                 | Daily        |
