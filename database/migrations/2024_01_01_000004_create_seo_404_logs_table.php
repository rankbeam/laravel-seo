<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Create the seo_404_logs table.
 *
 * This table logs 404 (Not Found) errors to help identify broken links,
 * missing content, and opportunities to create redirects. Each unique path
 * gets one record with a hit counter, avoiding duplicate entries.
 *
 * Features:
 * - Automatic hit counting for repeated 404s
 * - Referrer tracking to find broken link sources
 * - Status workflow: new → ignored or redirected
 * - Link to redirect when resolved
 * - User agent and IP for debugging
 *
 * Workflow:
 * 1. User visits /missing-page → 404 logged with status 'new'
 * 2. Admin reviews 404 logs in dashboard
 * 3. Admin either:
 *    a. Creates redirect → status becomes 'redirected', linked to seo_redirects
 *    b. Marks as ignored → status becomes 'ignored' (expected 404)
 *
 * @see \Fibonoir\LaravelSEO\Models\SEO404Log
 * @see \Fibonoir\LaravelSEO\Http\Middleware\Log404Middleware
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('seo_404_logs', function (Blueprint $table) {
            $table->id();

            /*
            |------------------------------------------------------------------
            | Request Path
            |------------------------------------------------------------------
            |
            | The URL path that returned 404. Does NOT include domain or query string.
            |
            | Examples:
            | - '/missing-page'
            | - '/blog/deleted-post'
            | - '/products/discontinued-item'
            |
            | Unique constraint ensures one record per path; subsequent 404s
            | increment hit_count and update last_seen_at.
            |
            */
            $table->string('path', 2048);

            /*
            |------------------------------------------------------------------
            | Referrer URL
            |------------------------------------------------------------------
            |
            | The page that linked to this 404. Helps identify:
            | - Internal broken links (your own site)
            | - External broken links (other sites linking to you)
            | - Empty if direct navigation or referrer stripped
            |
            | Examples:
            | - 'https://example.com/blog/article-with-broken-link'
            | - 'https://google.com/search?q=...'
            | - null (direct navigation)
            |
            */
            $table->string('referrer', 2048)->nullable();

            /*
            |------------------------------------------------------------------
            | Client Information
            |------------------------------------------------------------------
            |
            | Captured from the first request for debugging purposes.
            | NOT updated on subsequent hits.
            |
            */

            // User agent string - helps identify bots vs real users
            $table->string('user_agent')->nullable();

            // IP address (supports IPv4 and IPv6)
            // Used for identifying bot attacks or repeated probing
            $table->string('ip', 45)->nullable();

            /*
            |------------------------------------------------------------------
            | Hit Tracking
            |------------------------------------------------------------------
            |
            | Track how often this 404 occurs to prioritize fixes.
            | High hit count = high priority to fix.
            |
            */

            // Number of times this path returned 404
            $table->unsignedBigInteger('hit_count')->default(1);

            // When this 404 was first encountered
            $table->timestamp('first_seen_at');

            // When this 404 was last encountered
            $table->timestamp('last_seen_at');

            /*
            |------------------------------------------------------------------
            | Status Workflow
            |------------------------------------------------------------------
            |
            | Tracks the resolution state of this 404:
            |
            | - 'new': Unreviewed, needs attention
            | - 'ignored': Intentionally not fixed (expected 404, bot probe, etc.)
            | - 'redirected': A redirect has been created for this path
            |
            */
            $table->enum('status', ['new', 'ignored', 'redirected'])->default('new');

            /*
            |------------------------------------------------------------------
            | Redirect Relationship
            |------------------------------------------------------------------
            |
            | When status is 'redirected', links to the redirect that was created.
            | Allows tracking which 404s have been resolved and how.
            |
            | nullOnDelete: If redirect is deleted, this stays as historical record
            | but status may need manual update.
            |
            */
            $table->foreignId('redirect_id')
                ->nullable()
                ->constrained('seo_redirects')
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
            | - path (unique): One record per 404 path, upsert on duplicates
            | - status: Filter by new/ignored/redirected in dashboard
            | - hit_count: Sort by most frequent 404s for prioritization
            |
            */
            $table->unique('path', 'seo_404_logs_path_unique');
            $table->index('status', 'seo_404_logs_status_index');
            $table->index('hit_count', 'seo_404_logs_hits_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('seo_404_logs');
    }
};
