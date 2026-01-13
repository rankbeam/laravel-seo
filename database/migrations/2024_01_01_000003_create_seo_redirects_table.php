<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Create the seo_redirects table.
 *
 * This table stores URL redirects for managing URL changes, preserving SEO link
 * equity, and handling legacy URLs. Supports both exact path matching and regex
 * patterns for flexible redirect rules.
 *
 * Features:
 * - 301 (permanent) and 302 (temporary) redirects
 * - Regex pattern matching with capture groups
 * - Query string preservation
 * - Hit counting and tracking
 * - Active/inactive toggle
 * - Audit trail with user attribution
 *
 * Usage Examples:
 * - Simple: /old-page → /new-page (301)
 * - Regex: ^/blog/(\d{4})/(\d{2})/(.*)$ → /posts/$3 (301)
 * - Temporary: /sale → /summer-sale-2024 (302)
 *
 * @see \Fibonoir\LaravelSEO\Models\SEORedirect
 * @see \Fibonoir\LaravelSEO\Http\Middleware\RedirectMiddleware
 * @see \Fibonoir\LaravelSEO\Services\RedirectManager
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('seo_redirects', function (Blueprint $table) {
            $table->id();

            /*
            |------------------------------------------------------------------
            | Source Path
            |------------------------------------------------------------------
            |
            | The URL path to match for redirecting. Does NOT include the domain.
            |
            | For exact matching (is_regex = false):
            | - '/old-page'
            | - '/blog/old-post-slug'
            | - '/products/discontinued-item'
            |
            | For regex matching (is_regex = true):
            | - '^/blog/(\d{4})/(\d{2})/(.*)$' - Captures year, month, slug
            | - '^/category/(.*)$' - Captures category path
            | - '^/user/(\d+)/profile$' - Captures user ID
            |
            | Note: 2048 chars supports very long paths. Index is limited to
            | first 191 chars due to MySQL index size limits with utf8mb4.
            |
            */
            $table->string('source_path', 2048);

            /*
            |------------------------------------------------------------------
            | Target URL
            |------------------------------------------------------------------
            |
            | Where to redirect the user. Can be:
            |
            | - Relative path: '/new-page'
            | - Absolute URL: 'https://example.com/new-location'
            | - With capture groups: '/posts/$1/$2' (when is_regex = true)
            |
            | Capture group references ($1, $2, etc.) are replaced with values
            | matched from the source_path regex.
            |
            */
            $table->string('target_url', 2048);

            /*
            |------------------------------------------------------------------
            | HTTP Status Code
            |------------------------------------------------------------------
            |
            | The HTTP redirect status code to return:
            |
            | - 301: Permanent redirect (default) - Use for permanent moves
            |        Search engines transfer link equity to new URL
            |
            | - 302: Temporary redirect - Use for temporary changes
            |        Search engines keep original URL indexed
            |
            | - 307: Temporary redirect (HTTP/1.1) - Preserves request method
            |
            | - 308: Permanent redirect (HTTP/1.1) - Preserves request method
            |
            | - 410: Gone - Content permanently removed (not a redirect)
            |        Returns 410 status without redirecting
            |
            */
            $table->unsignedSmallInteger('status_code')->default(301);

            /*
            |------------------------------------------------------------------
            | Regex Mode
            |------------------------------------------------------------------
            |
            | When true, source_path is treated as a PCRE regex pattern.
            | Capture groups can be used in target_url.
            |
            | Example:
            | source_path: '^/blog/(\d{4})/(\d{2})/(.*)$'
            | target_url: '/posts/$3'
            | Result: /blog/2024/01/my-post → /posts/my-post
            |
            | When false, source_path is matched exactly (faster).
            |
            */
            $table->boolean('is_regex')->default(false);

            /*
            |------------------------------------------------------------------
            | Active Status
            |------------------------------------------------------------------
            |
            | Toggle to enable/disable the redirect without deleting it.
            | Useful for:
            | - Temporarily disabling a redirect for testing
            | - Keeping redirect history for audit purposes
            | - Scheduled activation/deactivation
            |
            */
            $table->boolean('is_active')->default(true);

            /*
            |------------------------------------------------------------------
            | Query String Handling
            |------------------------------------------------------------------
            |
            | When true: Query string from original request is appended to target
            | /old-page?ref=google → /new-page?ref=google
            |
            | When false: Query string is stripped
            | /old-page?ref=google → /new-page
            |
            */
            $table->boolean('preserve_query')->default(true);

            /*
            |------------------------------------------------------------------
            | Hit Tracking
            |------------------------------------------------------------------
            |
            | Track how often this redirect is triggered.
            | Useful for:
            | - Identifying popular redirects to clean up
            | - Detecting redirects that are no longer needed
            | - Monitoring redirect performance
            |
            */

            // Total number of times this redirect has been triggered
            $table->unsignedBigInteger('hit_count')->default(0);

            // When this redirect was last triggered
            $table->timestamp('last_hit_at')->nullable();

            /*
            |------------------------------------------------------------------
            | Audit Information
            |------------------------------------------------------------------
            |
            | Track who created the redirect and why.
            |
            */

            // Optional note explaining why this redirect exists
            $table->string('note')->nullable();

            // User who created the redirect (nullable for system-created)
            $table->foreignId('created_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

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
            | - source_path: For fast lookup during redirect matching
            |   Note: MySQL limits index to 191 chars with utf8mb4
            |
            | - [is_active, is_regex]: Composite index for filtering
            |   active redirects and separating regex vs exact matches
            |
            */
            $table->index('source_path', 'seo_redirects_source_index');
            $table->index(['is_active', 'is_regex'], 'seo_redirects_active_regex_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('seo_redirects');
    }
};
