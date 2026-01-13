<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Create the seo_scan_runs table.
 *
 * This table tracks SEO scan operations - automated crawls of your site that
 * check for SEO issues like duplicate titles, missing meta descriptions,
 * broken links, and other problems.
 *
 * Features:
 * - Multiple scan types (full, incremental, targeted)
 * - Real-time progress tracking
 * - Configuration storage for reproducibility
 * - Error capture for failed scans
 * - Timing metrics
 *
 * Scan Types:
 * - 'full': Scans all pages/models (scheduled nightly)
 * - 'incremental': Only pages changed since last scan (efficient)
 * - 'targeted': Specific model or URL (on-demand)
 *
 * @see \Fibonoir\LaravelSEO\Models\SEOScanRun
 * @see \Fibonoir\LaravelSEO\Services\Scanner\SitewideScanner
 * @see \Fibonoir\LaravelSEO\Jobs\ScanSitewideJob
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('seo_scan_runs', function (Blueprint $table) {
            $table->id();

            /*
            |------------------------------------------------------------------
            | Scan Type
            |------------------------------------------------------------------
            |
            | What kind of scan this is:
            |
            | - 'full': Complete site scan, checks every page
            |   Use for: Initial setup, weekly scheduled scans
            |
            | - 'incremental': Only pages modified since last scan
            |   Use for: Daily scans, efficient ongoing monitoring
            |   Checks: content_hash changes in seo_meta table
            |
            | - 'targeted': Specific model class or single URL
            |   Use for: On-demand checks, after content updates
            |   Stored in options: { "model": "App\\Models\\Post", "id": 5 }
            |
            */
            $table->string('type', 50);

            /*
            |------------------------------------------------------------------
            | Scan Status
            |------------------------------------------------------------------
            |
            | Current state of the scan:
            |
            | - 'pending': Created but not yet started (queued)
            | - 'running': Currently processing pages
            | - 'completed': Finished successfully
            | - 'failed': Encountered an error, see error_message
            |
            | Status transitions:
            | pending → running → completed
            | pending → running → failed
            |
            */
            $table->enum('status', ['pending', 'running', 'completed', 'failed'])
                ->default('pending');

            /*
            |------------------------------------------------------------------
            | Progress Tracking
            |------------------------------------------------------------------
            |
            | Real-time progress for UI updates and monitoring.
            | Updated as the scan progresses.
            |
            */

            // Total number of pages/models to scan
            $table->unsignedInteger('total_pages')->default(0);

            // Number of pages/models scanned so far
            $table->unsignedInteger('scanned_pages')->default(0);

            // Number of SEO issues found during this scan
            $table->unsignedInteger('issues_found')->default(0);

            /*
            |------------------------------------------------------------------
            | Configuration & Errors
            |------------------------------------------------------------------
            |
            | Store scan configuration and any errors encountered.
            |
            */

            // Scan configuration for reproducibility
            // Structure: {
            //   "model": "App\\Models\\Post",  // For targeted scans
            //   "id": 5,                       // For single model scans
            //   "check_links": true,           // Run link validation
            //   "check_images": true,          // Run image validation
            //   "batch_size": 50               // Custom batch size
            // }
            $table->json('options')->nullable();

            // Error message if scan failed
            // Contains exception message and optionally stack trace
            $table->text('error_message')->nullable();

            /*
            |------------------------------------------------------------------
            | Timing
            |------------------------------------------------------------------
            |
            | Track when the scan started and finished.
            | Duration = completed_at - started_at
            |
            */

            // When the scan actually started processing (moved from pending)
            $table->timestamp('started_at')->nullable();

            // When the scan finished (completed or failed)
            $table->timestamp('completed_at')->nullable();

            /*
            |------------------------------------------------------------------
            | Timestamps
            |------------------------------------------------------------------
            |
            | created_at: When the scan was queued
            | updated_at: Last progress update
            |
            */
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('seo_scan_runs');
    }
};
