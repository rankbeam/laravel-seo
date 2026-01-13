# Laravel SEO Suite

[![Latest Version on Packagist](https://img.shields.io/packagist/v/fibonoir/laravel-seo.svg?style=flat-square)](https://packagist.org/packages/fibonoir/laravel-seo)
[![Total Downloads](https://img.shields.io/packagist/dt/fibonoir/laravel-seo.svg?style=flat-square)](https://packagist.org/packages/fibonoir/laravel-seo)
[![License](https://img.shields.io/packagist/l/fibonoir/laravel-seo.svg?style=flat-square)](https://packagist.org/packages/fibonoir/laravel-seo)

A **Rank Math Pro-style SEO suite** for Laravel — complete with content analysis, 32 SEO checks, sitemap generation, redirect management, 404 monitoring, schema markup, and analytics integration.

## ✨ Features

| Feature | Description |
|---------|-------------|
| **Per-page SEO Editor** | Content editors can optimize each page without developer help |
| **Real-time Content Analyzer** | 32 SEO checks with instant feedback (keyword density, readability, etc.) |
| **Sitewide Scanner** | Find duplicate titles, missing meta, broken links across ALL pages |
| **Redirect Manager** | Handle URL changes with 301/302/410 redirects, regex support |
| **404 Monitor** | Track missing pages and create redirects with one click |
| **Schema Markup Builder** | Article, FAQ, Breadcrumb, LocalBusiness, Product schemas |
| **Sitemap Generation** | Automatic XML sitemaps with index support for large sites |
| **Analytics Integration** | GA4 data right in your admin panel |
| **Multi-keyword Support** | Primary + secondary keywords with synonyms |
| **Multilingual Ready** | hreflang support for international sites |

## 📋 Requirements

- PHP 8.2 or higher
- Laravel 11.0 or higher

## 🚀 Installation

```bash
composer require fibonoir/laravel-seo
```

Then run the interactive installer:

```bash
php artisan seo:install
```

The installer will:
1. Detect your frontend stack (Filament, Livewire, Vue, or React)
2. Publish appropriate configuration and components
3. Run database migrations
4. Set up initial defaults

## 📖 Quick Start

### 1. Add the HasSEO trait to your models

```php
use Fibonoir\LaravelSEO\Traits\HasSEO;

class Post extends Model
{
    use HasSEO;
}
```

### 2. Render SEO tags in your Blade layout

```blade
<head>
    @seo($post)
</head>
```

Or for routes without models:

```blade
<head>
    @seoForRoute('home')
</head>
```

### 3. Use the Facade for more control

```php
use Fibonoir\LaravelSEO\Facades\SEO;

// Get resolved SEO data
$seoData = SEO::resolve($post);

// Render as HTML
$html = SEO::render($post);

// Get as array for Vue/React
$array = SEO::toArray($post);
```

## 🎨 Frontend Integration

### Filament

SEO fields are automatically available in your Filament resources:

```php
use App\Filament\Forms\Components\SEOFields;

public static function form(Form $form): Form
{
    return $form->schema([
        // Your fields...
        SEOFields::make(),
    ]);
}
```

### Livewire

Include the SEO form component in your views:

```blade
<livewire:seo.seo-form :model="$post" />
```

### Vue (with Inertia)

```vue
<script setup>
import { useSEO } from '@/composables/useSEO'

const props = defineProps({ seo: Object })
useSEO(props.seo)
</script>
```

### React (with Inertia)

```tsx
import { useSEO } from '@/hooks/useSEO'

export default function Page({ seo }) {
  useSEO(seo)
  return <div>...</div>
}
```

## 🔧 Configuration

Publish the configuration file:

```bash
php artisan vendor:publish --tag=seo-config
```

Key configuration options in `config/seo.php`:

```php
return [
    'site_name' => env('APP_NAME'),
    'title_suffix' => ' | ' . env('APP_NAME'),
    
    'features' => [
        'analytics' => false,
        'sitemap' => true,
        'schema' => true,
        'redirects' => true,
        '404_monitor' => true,
    ],
    
    'analyzer' => [
        'min_content_length' => 300,
        'keyword_density_range' => [1.0, 2.5],
    ],
];
```

## 📊 SEO Analysis

The package includes 32 SEO checks across 5 categories:

| Category | Checks |
|----------|--------|
| **Focus Keyword** | Density, in title/URL/description/headings/first paragraph, distribution |
| **Meta & Title** | Length checks, numbers, power words |
| **Content Quality** | Length, readability, heading structure, transitions, paragraphs |
| **Media & Links** | Alt tags, internal/external links, broken links/images |
| **Technical SEO** | Head elements, canonical, noindex, lang, OG image, HTTPS |

## 🗺️ Sitemap Generation

Generate your sitemap:

```bash
php artisan seo:sitemap
```

Configure models in `config/seo.php`:

```php
'sitemap' => [
    'models' => [
        \App\Models\Post::class => [
            'priority' => 0.8,
            'changefreq' => 'weekly',
        ],
    ],
],
```

## ↩️ Redirects & 404 Monitoring

Apply the middleware globally or to specific routes:

```php
// In bootstrap/app.php for Laravel 11+
->withMiddleware(function (Middleware $middleware) {
    $middleware->prepend(\Fibonoir\LaravelSEO\Http\Middleware\RedirectMiddleware::class);
    $middleware->append(\Fibonoir\LaravelSEO\Http\Middleware\Log404Middleware::class);
})
```

## 🏥 Health Check

Verify your installation:

```bash
php artisan seo:health
```

## 📚 Documentation

For full documentation, visit [documentation link].

## 🧪 Testing

```bash
composer test
```

## 📝 Changelog

Please see [CHANGELOG](CHANGELOG.md) for recent changes.

## 🤝 Contributing

Please see [CONTRIBUTING](CONTRIBUTING.md) for details.

## 🔒 Security

If you discover any security-related issues, please email security@example.com instead of using the issue tracker.

## 📄 License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
