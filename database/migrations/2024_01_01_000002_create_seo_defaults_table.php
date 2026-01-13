<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Create the seo_defaults table.
 *
 * This table stores default SEO values that are applied hierarchically:
 *
 * 1. Global defaults (scope = '*' or 'global')
 *    Applied to all pages as the base layer.
 *
 * 2. Model-type defaults (scope = 'App\Models\Post')
 *    Applied to all instances of a specific model type.
 *    Example: All blog posts use the same title template.
 *
 * 3. Route defaults (scope = 'blog.index' or 'pages.about')
 *    Applied to specific named routes.
 *    Useful for static pages without models.
 *
 * Templates support placeholders that are replaced at runtime:
 * - {title} - Model's title attribute
 * - {name} - Model's name attribute
 * - {site_name} - From config('seo.site_name')
 * - {year} - Current year
 * - {date} - Formatted date
 *
 * @see \Fibonoir\LaravelSEO\Models\SEODefault
 * @see \Fibonoir\LaravelSEO\Services\SEODefaultsRepository
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('seo_defaults', function (Blueprint $table) {
            $table->id();

            /*
            |------------------------------------------------------------------
            | Scope
            |------------------------------------------------------------------
            |
            | Determines where these defaults apply. Can be:
            |
            | - '*' or 'global': Applies to all pages (lowest priority)
            | - Model class: 'App\Models\Post', 'App\Models\Product'
            | - Route name: 'blog.index', 'pages.about', 'products.show'
            |
            | The SEO resolver checks scopes in order:
            | route > model type > global
            |
            | Examples:
            | - scope: 'global' - Base defaults for entire site
            | - scope: 'App\Models\Post' - All blog posts
            | - scope: 'App\Models\Product' - All products
            | - scope: 'blog.archive' - Blog archive page
            | - scope: 'pages.*' - All pages routes (wildcard)
            |
            */
            $table->string('scope', 100);

            /*
            |------------------------------------------------------------------
            | Locale
            |------------------------------------------------------------------
            |
            | ISO 639-1 language code. Combined with scope to allow
            | different defaults for each language.
            |
            | Example: Posts in English vs Posts in Italian can have
            | different title templates.
            |
            */
            $table->string('locale', 10)->default('en');

            /*
            |------------------------------------------------------------------
            | Title Template
            |------------------------------------------------------------------
            |
            | Template for generating page titles. Supports placeholders.
            |
            | Examples:
            | - '{title} | {site_name}'
            | - '{name} - Buy Online | {site_name}'
            | - 'Blog - {site_name}' (static for route defaults)
            |
            | Placeholders:
            | - {title}, {name} - From model attributes
            | - {site_name} - From config
            | - {category} - From model relationship
            | - {year}, {month} - Date values
            |
            */
            $table->string('title_template')->nullable();

            /*
            |------------------------------------------------------------------
            | Description Template
            |------------------------------------------------------------------
            |
            | Template for meta description. Same placeholder support.
            |
            | Examples:
            | - '{excerpt}'
            | - 'Shop {name} at great prices. {short_description}'
            | - 'Read our latest blog posts about {category}.'
            |
            */
            $table->string('description_template')->nullable();

            /*
            |------------------------------------------------------------------
            | Default OG Image
            |------------------------------------------------------------------
            |
            | Default Open Graph image URL for this scope.
            | Used when the model/page doesn't have a specific image.
            |
            | Can be:
            | - Absolute URL: 'https://example.com/images/og-blog.jpg'
            | - Relative path: '/images/og-products.jpg'
            |
            */
            $table->string('og_image_default')->nullable();

            /*
            |------------------------------------------------------------------
            | Default Robots Directive
            |------------------------------------------------------------------
            |
            | Default robots meta tag value for this scope.
            |
            | Common values:
            | - 'index, follow' (default, allow everything)
            | - 'noindex, follow' (don't index, but follow links)
            | - 'index, nofollow' (index page, don't follow links)
            | - 'noindex, nofollow' (don't index or follow)
            | - 'noarchive' (don't cache)
            |
            | Useful for:
            | - Archive pages: 'noindex, follow'
            | - User profiles: 'noindex, nofollow'
            | - Thank you pages: 'noindex, nofollow'
            |
            */
            $table->string('robots_default', 50)->nullable();

            /*
            |------------------------------------------------------------------
            | Schema Defaults
            |------------------------------------------------------------------
            |
            | Default JSON-LD schema settings for this scope.
            | Merged with model-specific schema data.
            |
            | Structure:
            | {
            |   "@type": "Article",
            |   "publisher": {
            |     "@type": "Organization",
            |     "name": "My Company",
            |     "logo": "https://..."
            |   }
            | }
            |
            */
            $table->json('schema_defaults')->nullable();

            /*
            |------------------------------------------------------------------
            | Timestamps
            |------------------------------------------------------------------
            */
            $table->timestamps();

            /*
            |------------------------------------------------------------------
            | Indexes
            |------------------------------------------------------------------
            |
            | Unique constraint ensures one default record per scope per locale.
            | The resolver queries by scope + locale to find applicable defaults.
            |
            */
            $table->unique(['scope', 'locale'], 'seo_defaults_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('seo_defaults');
    }
};
