<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Create the seo_internal_links_index table.
 *
 * This table maintains a searchable index of all pages in your site, optimized
 * for finding internal linking opportunities. When editing content, this index
 * is queried to suggest relevant pages to link to based on keyword overlap.
 *
 * Features:
 * - Stemmed keyword index for fuzzy matching
 * - Heading extraction for anchor text suggestions
 * - Multi-locale support
 * - Polymorphic linking to source models
 *
 * Usage Flow:
 * 1. User edits a blog post about "Laravel authentication"
 * 2. System extracts keywords: [laravel, authent, login, secur, ...]
 * 3. Queries this table for pages with overlapping keywords
 * 4. Suggests: "How to Secure Your Laravel App", "Laravel Login Tutorial"
 * 5. User clicks to insert link with suggested anchor text
 *
 * Index is rebuilt:
 * - On model save (via HasSEO trait)
 * - Via scheduled job (nightly full rebuild)
 * - Manually via `php artisan seo:index-links`
 *
 * @see \Fibonoir\LaravelSEO\Models\SEOInternalLinksIndex
 * @see \Fibonoir\LaravelSEO\Services\InternalLinks\LinkIndexBuilder
 * @see \Fibonoir\LaravelSEO\Services\InternalLinks\LinkSuggester
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('seo_internal_links_index', function (Blueprint $table) {
            $table->id();

            /*
            |------------------------------------------------------------------
            | Linkable Relationship (Polymorphic)
            |------------------------------------------------------------------
            |
            | The model this index entry represents.
            | morphs() creates linkable_type and linkable_id columns.
            |
            | Examples:
            | - Post: linkable_type='App\Models\Post', linkable_id=5
            | - Product: linkable_type='App\Models\Product', linkable_id=123
            |
            | Allows quick lookup of all indexed pages and their metadata.
            |
            */
            $table->morphs('linkable');

            /*
            |------------------------------------------------------------------
            | Locale
            |------------------------------------------------------------------
            |
            | Language code for this index entry. Important for:
            | - Suggesting links in the correct language
            | - Using correct stemmer for keyword extraction
            | - Multi-language sites with separate content trees
            |
            */
            $table->string('locale', 10)->default('en');

            /*
            |------------------------------------------------------------------
            | Page URL
            |------------------------------------------------------------------
            |
            | Full URL path for this page. Used in:
            | - Building the href for suggested links
            | - Deduplication (same URL shouldn't be indexed twice)
            | - Display in suggestion UI
            |
            */
            $table->string('url', 2048);

            /*
            |------------------------------------------------------------------
            | Page Title
            |------------------------------------------------------------------
            |
            | The page title, used for:
            | - Display in suggestion dropdown
            | - Generating default anchor text
            | - Relevance display
            |
            */
            $table->string('title');

            /*
            |------------------------------------------------------------------
            | Stemmed Keywords
            |------------------------------------------------------------------
            |
            | JSON array of stemmed keywords extracted from the page content.
            | Stemming reduces words to their root form for better matching.
            |
            | Structure:
            | [
            |   { "stem": "laravel", "count": 15, "weight": 0.85 },
            |   { "stem": "authent", "count": 8, "weight": 0.72 },
            |   { "stem": "secur", "count": 5, "weight": 0.45 }
            | ]
            |
            | Keywords are:
            | - Tokenized from content
            | - Stop words removed
            | - Stemmed using locale-appropriate stemmer
            | - Weighted by TF-IDF or similar algorithm
            |
            | Matching algorithm: Find pages where stemmed_keywords overlap
            | with the keywords in the content being edited.
            |
            */
            $table->json('stemmed_keywords');

            /*
            |------------------------------------------------------------------
            | Headings
            |------------------------------------------------------------------
            |
            | JSON array of headings (H1, H2, H3) from the page.
            | Used for anchor text suggestions.
            |
            | Structure:
            | {
            |   "h1": ["How to Secure Your Laravel Application"],
            |   "h2": ["Authentication Basics", "Using Laravel Sanctum", "Best Practices"],
            |   "h3": ["Token-based Auth", "Session Auth", "API Authentication"]
            | }
            |
            | When suggesting a link, headings provide natural anchor text options
            | that are descriptive and SEO-friendly.
            |
            */
            $table->json('headings');

            /*
            |------------------------------------------------------------------
            | Timestamps
            |------------------------------------------------------------------
            |
            | Track when this index entry was created/updated.
            | Useful for incremental rebuilds.
            |
            */
            $table->timestamps();

            /*
            |------------------------------------------------------------------
            | Indexes
            |------------------------------------------------------------------
            |
            | - locale: Filter suggestions by language
            |   Query pattern: WHERE locale = 'en' AND ...
            |
            | Note: The stemmed_keywords JSON is searched in application code,
            | not via SQL queries. For very large sites, consider a dedicated
            | search engine (Elasticsearch, Meilisearch) for better performance.
            |
            */
            $table->index('locale', 'seo_links_index_locale_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('seo_internal_links_index');
    }
};
