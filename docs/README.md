# Laravel SEO Suite

A comprehensive SEO toolkit for Laravel applications with multi-stack support (Filament, Livewire, Vue, React).

## Table of Contents

-   [Features](#features)
-   [Requirements](#requirements)
-   [Installation](#installation)
-   [Quick Start](#quick-start)
-   [Basic Configuration](#basic-configuration)
-   [Common Use Cases](#common-use-cases)
-   [Documentation](#documentation)

---

## Features

-   **SEO Meta Management** - Title, description, Open Graph, Twitter Cards
-   **Content Analyzer** - 32+ SEO rules with scoring and recommendations
-   **Multi-Keyword Support** - Primary and secondary keywords with synonyms
-   **JSON-LD Schema** - Article, FAQ, Product, LocalBusiness, Organization, Breadcrumb
-   **XML Sitemap** - Automatic generation with image and video support
-   **Redirect Manager** - 301/302 redirects with regex support and hit tracking
-   **404 Monitoring** - Track and resolve broken links
-   **Google Analytics 4** - Dashboard integration with cached metrics
-   **Internal Links** - Smart linking suggestions based on content analysis
-   **Multi-locale Support** - Full internationalization with hreflang tags
-   **Multi-Stack Support** - Filament, Livewire, Vue 3, React components

---

## Requirements

-   PHP 8.2+
-   Laravel 11.0+
-   Database: MySQL 8.0+ / PostgreSQL 13+ / SQLite 3.35+

### Optional Dependencies

```json
{
    "spatie/laravel-sitemap": "^7.0",
    "google/apiclient": "^2.15"
}
```

---

## Installation

### Step 1: Install via Composer

```bash
composer require rankbeam/laravel-seo
```

### Step 2: Run the Installer

```bash
php artisan seo:install
```

The installer will:

1. Publish the configuration file
2. Run database migrations
3. Ask which frontend stack you're using
4. Publish stack-specific components

### Step 3: Add the Trait to Your Models

```php
use Rankbeam\Seo\Traits\HasSEO;

class Post extends Model
{
    use HasSEO;
}
```

### Step 4: Add SEO Tags to Your Layout

**Blade (Livewire/Traditional):**

```blade
<head>
    @seo($post)
</head>
```

**Vue (Inertia):**

```vue
<script setup>
import { useSEO } from "@/Components/SEO/composables/useSEO";
useSEO(props.post.seo);
</script>
```

**React (Inertia):**

```tsx
import { useSEO } from "@/Components/SEO/hooks/useSEO";
useSEO(post.seo);
```

---

## Quick Start

### 1. Basic Model Setup

```php
use Illuminate\Database\Eloquent\Model;
use Rankbeam\Seo\Traits\HasSEO;

class Post extends Model
{
    use HasSEO;

    // Optional: Customize SEO data sources
    public function getSEOTitle(): ?string
    {
        return $this->headline ?? $this->title;
    }

    public function getSEODescription(): ?string
    {
        return $this->excerpt ?? Str::limit(strip_tags($this->content), 155);
    }

    public function getSEOImage(): ?string
    {
        return $this->featured_image ?? config('seo.default_og_image');
    }

    public function getUrlForSEO(): string
    {
        return route('posts.show', $this);
    }
}
```

### 2. Display SEO Tags

```blade
<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    @seo($post)
</head>
<body>
    {{ $slot }}
</body>
</html>
```

### 3. Save SEO Data (Forms)

```php
// In your controller
$post->saveSEO([
    'title' => $request->seo_title,
    'description' => $request->seo_description,
    'focus_keywords' => [
        ['keyword' => 'laravel seo', 'is_primary' => true],
        ['keyword' => 'meta tags', 'is_primary' => false],
    ],
]);
```

### 4. Analyze Content

```php
// Synchronous (blocking)
$post->analyzeForSEO();

// Asynchronous (queued)
$post->dispatchAnalysis();

// Get score and report
$score = $post->getSEOScore();         // 0-100
$report = $post->getSEOAnalysisReport(); // Detailed breakdown
```

---

## Basic Configuration

Publish the config file:

```bash
php artisan vendor:publish --tag=seo-config
```

### Essential Settings

```php
// config/seo.php

return [
    // Your frontend stack
    'stack' => env('SEO_STACK', 'filament'), // filament, livewire, vue, react, api

    // Site-wide defaults
    'site_name' => env('APP_NAME', 'My Site'),
    'title_suffix' => ' | ' . env('APP_NAME'),
    'default_og_image' => '/images/og-default.jpg',

    // Twitter/X credentials
    'twitter_site' => env('SEO_TWITTER_SITE'),      // @yoursite
    'twitter_creator' => env('SEO_TWITTER_CREATOR'), // @author

    // Feature toggles
    'features' => [
        'analytics' => env('SEO_ANALYTICS_ENABLED', false),
        'sitemap' => env('SEO_SITEMAP_ENABLED', true),
        'schema' => env('SEO_SCHEMA_ENABLED', true),
        'redirects' => env('SEO_REDIRECTS_ENABLED', true),
        'scanner' => env('SEO_SCANNER_ENABLED', true),
        '404_monitor' => env('SEO_404_MONITOR_ENABLED', true),
    ],
];
```

### Environment Variables

```env
# config/seo.php values
SEO_STACK=filament
SEO_TWITTER_SITE=yourhandle
SEO_TWITTER_CREATOR=authorhandle
SEO_DEFAULT_OG_IMAGE=/images/og-default.jpg

# Feature flags
SEO_ANALYTICS_ENABLED=true
SEO_SITEMAP_ENABLED=true
SEO_REDIRECTS_ENABLED=true
SEO_SCANNER_ENABLED=true
SEO_404_MONITOR_ENABLED=true

# Google Analytics 4
SEO_GA4_ENABLED=true
SEO_GA4_PROPERTY_ID=123456789
SEO_GA4_CREDENTIALS_PATH=/path/to/credentials.json

# Cache
SEO_CACHE_STORE=redis
```

---

## Common Use Cases

### Blog Posts

```php
class Post extends Model
{
    use HasSEO;

    public function getSEOTitle(): ?string
    {
        return $this->title;
    }

    public function getSEODescription(): ?string
    {
        return $this->excerpt ?? Str::limit(strip_tags($this->content), 155);
    }

    public function getSEOImage(): ?string
    {
        return $this->featured_image;
    }

    public function getUrlForSEO(): string
    {
        return route('blog.show', ['slug' => $this->slug]);
    }

    // Custom content fields that trigger re-analysis
    public function getSEOContentFields(): array
    {
        return ['title', 'content', 'excerpt', 'category_id'];
    }
}
```

### E-Commerce Products

```php
class Product extends Model
{
    use HasSEO;

    public function getSEOTitle(): ?string
    {
        return "{$this->name} - Buy Online";
    }

    public function getSEODescription(): ?string
    {
        return "{$this->name} for \${$this->price}. {$this->short_description}";
    }

    public function getSEOImage(): ?string
    {
        return $this->images->first()?->url;
    }

    // Product schema is auto-generated
    protected function getSchemaType(): string
    {
        return 'Product';
    }
}
```

### Static Pages (No Model)

```php
// In your controller
public function about()
{
    $seo = app(SEOResolver::class)->resolveForRoute('pages.about');

    return view('pages.about', ['seo' => $seo]);
}
```

```blade
{{-- In your view --}}
@seoForRoute('pages.about')
```

### Multi-Language Sites

```php
// Save SEO for different locales
$post->saveSEO(['title' => 'English Title'], 'en');
$post->saveSEO(['title' => 'Deutscher Titel'], 'de');
$post->saveSEO(['title' => 'Titre Français'], 'fr');

// Get SEO for a specific locale
$seoData = $post->seoData('de');
```

### Programmatic SEO Overrides

```php
// In your controller
public function index(Request $request)
{
    $posts = Post::paginate(20);
    $seo = app(SEOResolver::class)->resolveForRoute('blog.index');

    // Override for pagination
    if ($request->page > 1) {
        $seo = app(SEOResolver::class)->resolveWithOverrides($seo, [
            'title' => "Blog - Page {$request->page}",
            'robots' => 'noindex,follow',
        ]);
    }

    return view('blog.index', compact('posts', 'seo'));
}
```

### Creating Redirects

```php
use Rankbeam\Seo\Services\RedirectManager;

$manager = app(RedirectManager::class);

// Simple redirect
$manager->createFromPath('/old-url', '/new-url');

// With status code
$manager->createFromPath('/temp-redirect', '/destination', 302);

// From 404 log
$manager->createFrom404($log404Entry, '/correct-url');

// Regex redirect
$manager->create([
    'source_path' => '^/blog/(\d{4})/(\d{2})/(.*)$',
    'target_url' => '/posts/$3',
    'is_regex' => true,
]);
```

### Generating Sitemaps

```bash
# Via Artisan
php artisan seo:sitemap

# Via code
use Rankbeam\Seo\Jobs\GenerateSitemapJob;
GenerateSitemapJob::dispatch();
```

### Running SEO Scans

```bash
# Full scan
php artisan seo:scan

# Incremental scan (only changed content)
php artisan seo:scan --type=incremental
```

---

## Documentation

| Document                                   | Description                         |
| ------------------------------------------ | ----------------------------------- |
| [CONFIGURATION.md](./CONFIGURATION.md)     | Complete configuration reference    |
| [COMMANDS.md](./COMMANDS.md)               | Artisan commands reference          |
| [EXTENDING.md](./EXTENDING.md)             | Customization and extension guide   |
| [API-REFERENCE.md](./API-REFERENCE.md)     | Public API documentation            |
| [PERFORMANCE.md](./PERFORMANCE.md)         | Performance optimization guide      |
| [TROUBLESHOOTING.md](./TROUBLESHOOTING.md) | Common issues and solutions         |
| [CHANGELOG.md](./CHANGELOG.md)             | Version history and migration notes |

---

## Quick Reference

### Artisan Commands

```bash
# Installation
php artisan seo:install

# Cache management
php artisan seo:cache --warm           # Warm all caches
php artisan seo:cache --clear          # Clear all caches
php artisan seo:cache --stats          # Show statistics

# Content scanning
php artisan seo:scan                   # Full scan
php artisan seo:scan --type=incremental # Only changed content
php artisan seo:scan --model="App\Models\Post" # Specific model
php artisan seo:scan --model="App\Models\Post" --id=123 # Single instance
php artisan seo:scan --queue           # Queue for background
php artisan seo:scan --dry-run         # Preview without scanning

# Sitemap generation
php artisan seo:sitemap                # Generate sitemap
php artisan seo:sitemap --ping         # Generate and ping search engines

# Analytics sync
php artisan seo:sync-analytics         # Sync last 7 days
php artisan seo:sync-analytics --days=30 # Sync last 30 days

# Health check
php artisan seo:health                 # Check installation health
php artisan seo:health --json          # JSON output for monitoring
```

See [COMMANDS.md](./COMMANDS.md) for detailed command documentation.

### Blade Directives

```blade
@seo($model)              {{-- All SEO tags --}}
@seoForRoute('route.name') {{-- SEO for named route --}}
@seoTitle($model)         {{-- Title tag only --}}
@seoMeta($model)          {{-- Meta description only --}}
@seoSchema($model)        {{-- JSON-LD schema only --}}
@seoCanonical($model)     {{-- Canonical link only --}}
@seoRobots($model)        {{-- Robots meta only --}}
```

### Model Methods

```php
$model->seoData();             // Get resolved SEO data
$model->saveSEO($data);        // Save SEO data
$model->analyzeForSEO();       // Analyze synchronously
$model->dispatchAnalysis();    // Analyze async
$model->getSEOScore();         // Get score (0-100)
$model->getSEOAnalysisReport(); // Get detailed report
$model->needsSEOAnalysis();    // Check if needs analysis
$model->getFocusKeywords();    // Get focus keywords
$model->getPrimaryKeyword();   // Get primary keyword
```

---

## Support

-   **Issues**: [GitHub Issues](https://github.com/rankbeam/laravel-seo/issues)
-   **Discussions**: [GitHub Discussions](https://github.com/rankbeam/laravel-seo/discussions)
-   **Security**: Please report security vulnerabilities to security@example.com

## License

The Laravel SEO Suite is open-sourced software licensed under the [MIT license](../LICENSE.md).
