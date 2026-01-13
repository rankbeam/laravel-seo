<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Create the seo_meta table.
 *
 * This is the primary table for storing SEO metadata attached to any Eloquent model.
 * It uses a polymorphic relationship (morphs) to allow any model to have SEO data.
 *
 * Features:
 * - Multi-locale support for internationalized sites
 * - Core SEO fields (title, description, canonical, robots)
 * - Open Graph metadata for social sharing (Facebook, LinkedIn, etc.)
 * - Twitter Card metadata for Twitter sharing
 * - Multi-keyword focus with synonyms support
 * - JSON-LD schema markup storage
 * - Content analysis scores and detailed reports
 * - Content snapshots for background analysis
 *
 * @see \Fibonoir\LaravelSEO\Models\SEOMeta
 * @see \Fibonoir\LaravelSEO\Traits\HasSEO
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('seo_meta', function (Blueprint $table) {
            $table->id();

            /*
            |------------------------------------------------------------------
            | Polymorphic Relationship
            |------------------------------------------------------------------
            |
            | morphs() creates seoable_type (string) and seoable_id (bigint)
            | columns. This allows any Eloquent model to have SEO metadata by
            | using the HasSEO trait.
            |
            | Example: Post model with ID 5 would have:
            | - seoable_type: 'App\Models\Post'
            | - seoable_id: 5
            |
            */
            $table->morphs('seoable');

            /*
            |------------------------------------------------------------------
            | Locale / Language
            |------------------------------------------------------------------
            |
            | ISO 639-1 language code (e.g., 'en', 'fr', 'de', 'it').
            | Allows storing different SEO data for each language version
            | of the same content. Combined with seoable_type and seoable_id
            | in the unique index to prevent duplicate entries.
            |
            */
            $table->string('locale', 10)->default('en');

            /*
            |------------------------------------------------------------------
            | Core SEO Meta Tags
            |------------------------------------------------------------------
            |
            | These fields map directly to HTML meta tags and are critical
            | for search engine optimization.
            |
            */

            // <title> tag - Primary ranking factor, shown in search results
            // Optimal length: 50-60 characters. Max display: ~70 characters.
            $table->string('title', 70)->nullable();

            // <meta name="description"> - Shown in search result snippets
            // Optimal length: 150-160 characters. Max display: ~160 characters.
            $table->string('description', 160)->nullable();

            // <link rel="canonical"> - Prevents duplicate content issues
            // Should be the absolute URL of the preferred version of the page.
            $table->string('canonical')->nullable();

            // <meta name="robots"> - Controls search engine behavior
            // Values: index, noindex, follow, nofollow, noarchive, nosnippet, etc.
            // Example: 'noindex, follow' or 'index, nofollow'
            $table->string('robots', 50)->nullable();

            /*
            |------------------------------------------------------------------
            | Open Graph Meta Tags (og:*)
            |------------------------------------------------------------------
            |
            | Used by Facebook, LinkedIn, Pinterest, and other platforms when
            | sharing links. If null, the core SEO fields are used as fallbacks.
            |
            | @see https://ogp.me/
            |
            */

            // og:title - Title shown when sharing on social media
            $table->string('og_title', 70)->nullable();

            // og:description - Description shown when sharing
            $table->string('og_description', 200)->nullable();

            // og:image - Image URL for social sharing (recommended: 1200x630px)
            $table->string('og_image')->nullable();

            // og:type - Type of content: website, article, product, etc.
            $table->string('og_type', 30)->default('website');

            /*
            |------------------------------------------------------------------
            | Twitter Card Meta Tags (twitter:*)
            |------------------------------------------------------------------
            |
            | Controls how content appears when shared on Twitter/X.
            | Falls back to Open Graph values if null.
            |
            | @see https://developer.twitter.com/en/docs/twitter-for-websites/cards
            |
            */

            // twitter:title - Title for Twitter cards
            $table->string('twitter_title', 70)->nullable();

            // twitter:description - Description for Twitter cards
            $table->string('twitter_description', 200)->nullable();

            // twitter:image - Image for Twitter cards (recommended: 1200x628px)
            $table->string('twitter_image')->nullable();

            // twitter:card - Card type: summary, summary_large_image, app, player
            $table->string('twitter_card', 30)->default('summary_large_image');

            /*
            |------------------------------------------------------------------
            | Focus Keywords
            |------------------------------------------------------------------
            |
            | JSON array of focus keywords for content analysis.
            | Supports multiple keywords, each with optional synonyms.
            |
            | Structure:
            | [
            |   {
            |     "keyword": "laravel seo",
            |     "is_primary": true,
            |     "synonyms": ["laravel search optimization", "seo for laravel"]
            |   },
            |   {
            |     "keyword": "meta tags",
            |     "is_primary": false,
            |     "synonyms": []
            |   }
            | ]
            |
            */
            $table->json('focus_keywords')->nullable();

            /*
            |------------------------------------------------------------------
            | Schema.org Markup (JSON-LD)
            |------------------------------------------------------------------
            |
            | Structured data for rich snippets in search results.
            | Can include Article, FAQ, Product, LocalBusiness, etc.
            |
            | @see https://schema.org
            | @see https://developers.google.com/search/docs/appearance/structured-data
            |
            */

            // Full JSON-LD schema object(s) - can contain multiple schemas
            $table->json('schema_jsonld')->nullable();

            // Primary schema type for quick filtering/identification
            // Values: Article, FAQPage, Product, LocalBusiness, BreadcrumbList, etc.
            $table->string('schema_type', 50)->nullable();

            /*
            |------------------------------------------------------------------
            | Content Analysis Results
            |------------------------------------------------------------------
            |
            | Stores the results from the content analyzer engine.
            | Updated asynchronously via background jobs.
            |
            */

            // Overall SEO score (0-100) based on weighted rule results
            $table->unsignedTinyInteger('seo_score')->nullable();

            // Detailed analysis report with individual rule results
            // Structure: { "score": 85, "results": [...], "recommendations": [...] }
            $table->json('analysis_report')->nullable();

            // When the content was last analyzed
            $table->timestamp('analyzed_at')->nullable();

            /*
            |------------------------------------------------------------------
            | Content Snapshot
            |------------------------------------------------------------------
            |
            | Stores a copy of the content for background analysis.
            | Used to analyze content without blocking the user and to
            | detect content changes for re-analysis.
            |
            */

            // Full content snapshot (HTML or plain text)
            $table->longText('content_snapshot')->nullable();

            // SHA-256 hash of content for change detection
            $table->string('content_hash', 64)->nullable();

            // When the snapshot was taken
            $table->timestamp('snapshot_at')->nullable();

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
            | Unique constraint ensures one SEO record per model per locale.
            | Score and analyzed_at indexes support dashboard queries and
            | finding content that needs re-analysis.
            |
            */
            $table->unique(['seoable_type', 'seoable_id', 'locale'], 'seo_meta_unique');
            $table->index('seo_score', 'seo_meta_score_index');
            $table->index('analyzed_at', 'seo_meta_analyzed_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('seo_meta');
    }
};
