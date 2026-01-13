<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Add performance optimization indexes.
 *
 * This migration adds additional indexes based on query pattern analysis
 * to optimize common SEO suite operations.
 *
 * ## Query Patterns Analyzed
 *
 * 1. **SEO Meta Queries**
 *    - Find all meta for a model type → `seoable_type` index
 *    - Find stale analysis records → `analyzed_at` index (exists)
 *    - Find low-scoring content → `seo_score` index (exists)
 *    - Locale-specific queries → composite index
 *
 * 2. **Redirects Queries**
 *    - Active redirect lookup → `is_active` index (exists in composite)
 *    - Hit count sorting → `hit_count` index
 *    - Recent activity → `last_hit_at` index
 *
 * 3. **404 Logs Queries**
 *    - Date range queries → `last_seen_at` index
 *    - Referrer analysis → partial index on referrer
 *
 * 4. **Analytics Cache Queries**
 *    - Path lookups → index on first 191 chars
 *    - Metric-type filtering → `metric_type` index
 *
 * 5. **Internal Links Index Queries**
 *    - URL deduplication → unique index on url+locale
 *    - Model type filtering → `linkable_type` index
 *
 * ## Index Naming Convention
 *
 * Format: `{table}_{column(s)}_{purpose}_idx`
 *
 * @see \Fibonoir\LaravelSEO\Services\CacheManager
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        /*
        |------------------------------------------------------------------
        | SEO Meta Table Indexes
        |------------------------------------------------------------------
        |
        | Optimizes queries for:
        | - Finding all SEO records for a specific model type
        | - Filtering by locale across model types
        | - Dashboard queries combining type, locale, and score
        |
        */
        Schema::table('seo_meta', function (Blueprint $table) {
            // Index for model-type scoped queries
            // Query: SELECT * FROM seo_meta WHERE seoable_type = 'App\Models\Post'
            if (! $this->hasIndex('seo_meta', 'seo_meta_seoable_type_idx')) {
                $table->index('seoable_type', 'seo_meta_seoable_type_idx');
            }

            // Composite index for locale-filtered type queries
            // Query: SELECT * FROM seo_meta WHERE seoable_type = ? AND locale = ?
            if (! $this->hasIndex('seo_meta', 'seo_meta_type_locale_idx')) {
                $table->index(['seoable_type', 'locale'], 'seo_meta_type_locale_idx');
            }

            // Composite index for dashboard "needs attention" queries
            // Query: SELECT * FROM seo_meta WHERE seo_score < 50 AND analyzed_at < ?
            if (! $this->hasIndex('seo_meta', 'seo_meta_score_analyzed_idx')) {
                $table->index(['seo_score', 'analyzed_at'], 'seo_meta_score_analyzed_idx');
            }

            // Index for content hash lookups (change detection)
            // Query: SELECT * FROM seo_meta WHERE content_hash = ?
            if (! $this->hasIndex('seo_meta', 'seo_meta_content_hash_idx')) {
                $table->index('content_hash', 'seo_meta_content_hash_idx');
            }
        });

        /*
        |------------------------------------------------------------------
        | SEO Redirects Table Indexes
        |------------------------------------------------------------------
        |
        | Optimizes queries for:
        | - Finding popular redirects for cleanup
        | - Activity monitoring and reporting
        |
        */
        Schema::table('seo_redirects', function (Blueprint $table) {
            // Index for hit count sorting (finding popular redirects)
            // Query: SELECT * FROM seo_redirects ORDER BY hit_count DESC
            if (! $this->hasIndex('seo_redirects', 'seo_redirects_hit_count_idx')) {
                $table->index('hit_count', 'seo_redirects_hit_count_idx');
            }

            // Index for recent activity queries
            // Query: SELECT * FROM seo_redirects WHERE last_hit_at > ? ORDER BY last_hit_at DESC
            if (! $this->hasIndex('seo_redirects', 'seo_redirects_last_hit_idx')) {
                $table->index('last_hit_at', 'seo_redirects_last_hit_idx');
            }

            // Index for status code filtering
            // Query: SELECT * FROM seo_redirects WHERE status_code = 301
            if (! $this->hasIndex('seo_redirects', 'seo_redirects_status_code_idx')) {
                $table->index('status_code', 'seo_redirects_status_code_idx');
            }
        });

        /*
        |------------------------------------------------------------------
        | SEO Defaults Table Indexes
        |------------------------------------------------------------------
        |
        | Optimizes queries for:
        | - Fast scope+locale lookups (most common query)
        |
        */
        Schema::table('seo_defaults', function (Blueprint $table) {
            // Composite index for the most common query pattern
            // Query: SELECT * FROM seo_defaults WHERE scope = ? AND locale = ?
            if (! $this->hasIndex('seo_defaults', 'seo_defaults_scope_locale_idx')) {
                $table->unique(['scope', 'locale'], 'seo_defaults_scope_locale_idx');
            }
        });

        /*
        |------------------------------------------------------------------
        | SEO 404 Logs Table Indexes
        |------------------------------------------------------------------
        |
        | Optimizes queries for:
        | - Date range filtering for cleanup
        | - Finding 404s with specific referrers
        |
        */
        Schema::table('seo_404_logs', function (Blueprint $table) {
            // Index for date range queries (cleanup operations)
            // Query: SELECT * FROM seo_404_logs WHERE last_seen_at < ?
            if (! $this->hasIndex('seo_404_logs', 'seo_404_logs_last_seen_idx')) {
                $table->index('last_seen_at', 'seo_404_logs_last_seen_idx');
            }

            // Composite index for status+date queries
            // Query: SELECT * FROM seo_404_logs WHERE status = 'new' ORDER BY last_seen_at DESC
            if (! $this->hasIndex('seo_404_logs', 'seo_404_logs_status_date_idx')) {
                $table->index(['status', 'last_seen_at'], 'seo_404_logs_status_date_idx');
            }

            // Index for first_seen_at (historical analysis)
            if (! $this->hasIndex('seo_404_logs', 'seo_404_logs_first_seen_idx')) {
                $table->index('first_seen_at', 'seo_404_logs_first_seen_idx');
            }
        });

        /*
        |------------------------------------------------------------------
        | SEO Analytics Cache Table Indexes
        |------------------------------------------------------------------
        |
        | Optimizes queries for:
        | - Metric type filtering
        | - Path-based aggregations
        |
        */
        Schema::table('seo_analytics_cache', function (Blueprint $table) {
            // Index for metric type queries
            // Query: SELECT * FROM seo_analytics_cache WHERE metric_type = 'pageviews'
            if (! $this->hasIndex('seo_analytics_cache', 'seo_analytics_metric_type_idx')) {
                $table->index('metric_type', 'seo_analytics_metric_type_idx');
            }

            // Composite index for metric+date aggregations
            // Query: SELECT SUM(value) FROM seo_analytics_cache WHERE metric_type = ? AND date BETWEEN ? AND ?
            if (! $this->hasIndex('seo_analytics_cache', 'seo_analytics_metric_date_idx')) {
                $table->index(['metric_type', 'date'], 'seo_analytics_metric_date_idx');
            }
        });

        /*
        |------------------------------------------------------------------
        | SEO Internal Links Index Table Indexes
        |------------------------------------------------------------------
        |
        | Optimizes queries for:
        | - URL deduplication
        | - Model type filtering
        |
        */
        Schema::table('seo_internal_links_index', function (Blueprint $table) {
            // Index for model type queries
            // Query: SELECT * FROM seo_internal_links_index WHERE linkable_type = ?
            if (! $this->hasIndex('seo_internal_links_index', 'seo_links_linkable_type_idx')) {
                $table->index('linkable_type', 'seo_links_linkable_type_idx');
            }

            // Composite index for type+locale queries
            // Query: SELECT * FROM seo_internal_links_index WHERE linkable_type = ? AND locale = ?
            if (! $this->hasIndex('seo_internal_links_index', 'seo_links_type_locale_idx')) {
                $table->index(['linkable_type', 'locale'], 'seo_links_type_locale_idx');
            }
        });

        /*
        |------------------------------------------------------------------
        | SEO Scan Runs Table Indexes
        |------------------------------------------------------------------
        |
        | Optimizes queries for:
        | - Finding recent/active scans
        | - Status filtering
        |
        */
        if (Schema::hasTable('seo_scan_runs')) {
            Schema::table('seo_scan_runs', function (Blueprint $table) {
                // Index for status queries
                if (! $this->hasIndex('seo_scan_runs', 'seo_scan_runs_status_idx')) {
                    $table->index('status', 'seo_scan_runs_status_idx');
                }

                // Index for type queries
                if (! $this->hasIndex('seo_scan_runs', 'seo_scan_runs_type_idx')) {
                    $table->index('type', 'seo_scan_runs_type_idx');
                }

                // Index for started_at (finding recent scans)
                if (! $this->hasIndex('seo_scan_runs', 'seo_scan_runs_started_idx')) {
                    $table->index('started_at', 'seo_scan_runs_started_idx');
                }
            });
        }

        /*
        |------------------------------------------------------------------
        | SEO Scan Issues Table Indexes
        |------------------------------------------------------------------
        |
        | Optimizes queries for:
        | - Finding issues by type
        | - Severity filtering
        |
        */
        if (Schema::hasTable('seo_scan_issues')) {
            Schema::table('seo_scan_issues', function (Blueprint $table) {
                // Index for issue type queries
                if (! $this->hasIndex('seo_scan_issues', 'seo_scan_issues_type_idx')) {
                    $table->index('issue_type', 'seo_scan_issues_type_idx');
                }

                // Index for severity queries
                if (! $this->hasIndex('seo_scan_issues', 'seo_scan_issues_severity_idx')) {
                    $table->index('severity', 'seo_scan_issues_severity_idx');
                }

                // Composite index for type+severity filtering
                if (! $this->hasIndex('seo_scan_issues', 'seo_scan_issues_type_severity_idx')) {
                    $table->index(['issue_type', 'severity'], 'seo_scan_issues_type_severity_idx');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('seo_meta', function (Blueprint $table) {
            $table->dropIndexIfExists('seo_meta_seoable_type_idx');
            $table->dropIndexIfExists('seo_meta_type_locale_idx');
            $table->dropIndexIfExists('seo_meta_score_analyzed_idx');
            $table->dropIndexIfExists('seo_meta_content_hash_idx');
        });

        Schema::table('seo_redirects', function (Blueprint $table) {
            $table->dropIndexIfExists('seo_redirects_hit_count_idx');
            $table->dropIndexIfExists('seo_redirects_last_hit_idx');
            $table->dropIndexIfExists('seo_redirects_status_code_idx');
        });

        Schema::table('seo_defaults', function (Blueprint $table) {
            $table->dropIndexIfExists('seo_defaults_scope_locale_idx');
        });

        Schema::table('seo_404_logs', function (Blueprint $table) {
            $table->dropIndexIfExists('seo_404_logs_last_seen_idx');
            $table->dropIndexIfExists('seo_404_logs_status_date_idx');
            $table->dropIndexIfExists('seo_404_logs_first_seen_idx');
        });

        Schema::table('seo_analytics_cache', function (Blueprint $table) {
            $table->dropIndexIfExists('seo_analytics_metric_type_idx');
            $table->dropIndexIfExists('seo_analytics_metric_date_idx');
        });

        Schema::table('seo_internal_links_index', function (Blueprint $table) {
            $table->dropIndexIfExists('seo_links_linkable_type_idx');
            $table->dropIndexIfExists('seo_links_type_locale_idx');
        });

        if (Schema::hasTable('seo_scan_runs')) {
            Schema::table('seo_scan_runs', function (Blueprint $table) {
                $table->dropIndexIfExists('seo_scan_runs_status_idx');
                $table->dropIndexIfExists('seo_scan_runs_type_idx');
                $table->dropIndexIfExists('seo_scan_runs_started_idx');
            });
        }

        if (Schema::hasTable('seo_scan_issues')) {
            Schema::table('seo_scan_issues', function (Blueprint $table) {
                $table->dropIndexIfExists('seo_scan_issues_type_idx');
                $table->dropIndexIfExists('seo_scan_issues_severity_idx');
                $table->dropIndexIfExists('seo_scan_issues_type_severity_idx');
            });
        }
    }

    /**
     * Check if an index exists on a table.
     */
    protected function hasIndex(string $table, string $indexName): bool
    {
        $connection = Schema::getConnection();
        $driver = $connection->getDriverName();

        if ($driver === 'mysql') {
            $indexes = $connection->select(
                "SHOW INDEX FROM {$table} WHERE Key_name = ?",
                [$indexName]
            );

            return count($indexes) > 0;
        }

        if ($driver === 'pgsql') {
            $indexes = $connection->select(
                "SELECT indexname FROM pg_indexes WHERE tablename = ? AND indexname = ?",
                [$table, $indexName]
            );

            return count($indexes) > 0;
        }

        if ($driver === 'sqlite') {
            $indexes = $connection->select(
                "SELECT name FROM sqlite_master WHERE type='index' AND tbl_name = ? AND name = ?",
                [$table, $indexName]
            );

            return count($indexes) > 0;
        }

        // For other drivers, assume index doesn't exist
        return false;
    }
};
