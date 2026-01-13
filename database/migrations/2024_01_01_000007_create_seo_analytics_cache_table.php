<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Create the seo_analytics_cache table.
 *
 * This table caches analytics data fetched from Google Analytics 4 (GA4).
 * Caching reduces API calls, speeds up dashboard loading, and allows offline
 * access to historical data.
 *
 * Features:
 * - Per-path, per-metric, per-date granularity
 * - Supports any numeric metric from GA4
 * - Optional dimensions for segmented data
 * - Unique constraint prevents duplicates
 *
 * Metrics cached:
 * - pageviews, sessions, users, newUsers
 * - bounceRate, avgSessionDuration, avgTimeOnPage
 * - entrances, exits, exitRate
 * - organicSearches, directTraffic, referralTraffic
 *
 * Data is synced via the SyncAnalyticsJob, typically daily.
 *
 * @see \Fibonoir\LaravelSEO\Models\SEOAnalyticsCache
 * @see \Fibonoir\LaravelSEO\Services\Analytics\GA4Service
 * @see \Fibonoir\LaravelSEO\Services\Analytics\AnalyticsCache
 * @see \Fibonoir\LaravelSEO\Jobs\SyncAnalyticsJob
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('seo_analytics_cache', function (Blueprint $table) {
            $table->id();

            /*
            |------------------------------------------------------------------
            | Page Path
            |------------------------------------------------------------------
            |
            | The URL path this metric applies to.
            |
            | Examples:
            | - '/' (homepage)
            | - '/blog/my-post'
            | - '/products/widget'
            | - '*' (aggregate for entire site)
            |
            | Should match GA4's pagePath dimension.
            |
            */
            $table->string('path', 2048);

            /*
            |------------------------------------------------------------------
            | Metric Type
            |------------------------------------------------------------------
            |
            | The type of metric stored. Matches GA4 API metric names.
            |
            | Common metrics:
            | - 'screenPageViews': Total page views
            | - 'sessions': Number of sessions
            | - 'totalUsers': Unique users
            | - 'newUsers': First-time visitors
            | - 'bounceRate': Percentage that left immediately (0-100)
            | - 'averageSessionDuration': Avg session length in seconds
            | - 'engagementRate': Percentage of engaged sessions (0-100)
            | - 'eventCount': Total events
            |
            | Custom metrics can also be stored.
            |
            */
            $table->string('metric_type', 50);

            /*
            |------------------------------------------------------------------
            | Date
            |------------------------------------------------------------------
            |
            | The date this metric value is for. Analytics data is stored
            | per-day granularity for flexibility in aggregation.
            |
            | Date ranges are queried by summing/averaging values between dates.
            |
            */
            $table->date('date');

            /*
            |------------------------------------------------------------------
            | Metric Value
            |------------------------------------------------------------------
            |
            | The numeric value of the metric.
            |
            | Uses DECIMAL(15,4) to support:
            | - Large counts (up to 99,999,999,999)
            | - Percentages with precision (e.g., 45.6789%)
            | - Duration in seconds with milliseconds
            |
            | Examples:
            | - pageviews: 1523.0000
            | - bounceRate: 45.6700
            | - avgSessionDuration: 185.5000 (seconds)
            |
            */
            $table->decimal('value', 15, 4);

            /*
            |------------------------------------------------------------------
            | Dimensions
            |------------------------------------------------------------------
            |
            | Optional segmentation dimensions for this metric.
            |
            | Allows storing the same metric broken down by:
            | - Device category: { "deviceCategory": "mobile" }
            | - Country: { "country": "United States" }
            | - Source/Medium: { "sessionSource": "google", "sessionMedium": "organic" }
            | - Multiple: { "deviceCategory": "desktop", "country": "UK" }
            |
            | NULL means aggregate (no segmentation).
            |
            */
            $table->json('dimensions')->nullable();

            /*
            |------------------------------------------------------------------
            | Timestamps
            |------------------------------------------------------------------
            |
            | Track when this cache entry was created/updated.
            | Useful for cache invalidation strategies.
            |
            */
            $table->timestamps();

            /*
            |------------------------------------------------------------------
            | Indexes
            |------------------------------------------------------------------
            |
            | - Unique [path, metric_type, date]: Prevents duplicate entries
            |   Allows upsert pattern: ON DUPLICATE KEY UPDATE value
            |
            | - date: Efficient date range queries for reporting
            |
            */
            $table->unique(
                ['path', 'metric_type', 'date'],
                'seo_analytics_cache_unique'
            );
            $table->index('date', 'seo_analytics_cache_date_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('seo_analytics_cache');
    }
};
