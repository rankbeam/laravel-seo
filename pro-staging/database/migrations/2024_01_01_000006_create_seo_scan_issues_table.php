<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Create the seo_scan_issues table.
 *
 * This table stores individual SEO issues discovered during site scans.
 * Issues can be linked to specific models or just URLs (for external pages
 * or pages without models).
 *
 * Features:
 * - Polymorphic linking to any model
 * - Issue categorization by type and severity
 * - Status workflow for tracking fixes
 * - Rich context for debugging
 * - Link to originating scan run
 *
 * Issue Types:
 * - duplicate_title, duplicate_description
 * - missing_title, missing_description, missing_h1
 * - title_too_long, title_too_short
 * - broken_link, broken_image
 * - missing_alt_text, missing_canonical
 * - thin_content, keyword_stuffing
 * - invalid_schema, invalid_robots
 *
 * @see \Fibonoir\LaravelSEO\Models\SEOScanIssue
 * @see \Fibonoir\LaravelSEO\Services\Scanner\PageScanner
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('seo_scan_issues', function (Blueprint $table) {
            $table->id();

            /*
            |------------------------------------------------------------------
            | Scannable Relationship (Polymorphic, Nullable)
            |------------------------------------------------------------------
            |
            | Links the issue to a specific model when applicable.
            | nullableMorphs() creates nullable scannable_type and scannable_id.
            |
            | Examples:
            | - Post with ID 5: scannable_type='App\Models\Post', scannable_id=5
            | - External URL: both null, use 'url' field instead
            |
            */
            $table->nullableMorphs('scannable');

            /*
            |------------------------------------------------------------------
            | URL
            |------------------------------------------------------------------
            |
            | The URL where this issue was found. Useful when:
            | - Issue relates to a URL without a model
            | - Multiple URLs map to the same model
            | - Quick reference without loading the model
            |
            */
            $table->string('url', 2048)->nullable();

            /*
            |------------------------------------------------------------------
            | Issue Classification
            |------------------------------------------------------------------
            |
            | Categorize the issue for filtering and grouping.
            |
            */

            // Type of SEO issue detected
            // Examples: duplicate_title, missing_description, broken_link,
            //           thin_content, missing_h1, invalid_schema
            $table->string('issue_type', 50);

            // Severity level for prioritization
            // - 'critical': Must fix immediately (duplicate content, no title)
            // - 'warning': Should fix soon (missing description, thin content)
            // - 'notice': Nice to have (missing power words, short paragraphs)
            $table->string('severity', 20);

            // Which SEO field is affected (optional)
            // Examples: title, description, canonical, og_image, h1
            $table->string('field', 50)->nullable();

            /*
            |------------------------------------------------------------------
            | Issue Details
            |------------------------------------------------------------------
            |
            | Human-readable message and machine-readable context.
            |
            */

            // Human-readable description of the issue
            // Example: "Title is 85 characters, exceeding the 70 character limit"
            $table->text('message');

            // Additional context for debugging/display
            // Structure varies by issue_type:
            // {
            //   "current_value": "Very Long Title...",
            //   "expected": "Max 70 characters",
            //   "duplicate_of": 123,      // For duplicates
            //   "broken_url": "...",      // For broken links
            //   "status_code": 404        // For broken links
            // }
            $table->json('context')->nullable();

            /*
            |------------------------------------------------------------------
            | Status Workflow
            |------------------------------------------------------------------
            |
            | Track resolution of the issue:
            |
            | - 'open': Issue exists and needs attention
            | - 'fixed': Issue has been resolved (verified by re-scan)
            | - 'ignored': Intentionally not fixing (false positive, acceptable)
            |
            */
            $table->enum('status', ['open', 'fixed', 'ignored'])->default('open');

            /*
            |------------------------------------------------------------------
            | Timing
            |------------------------------------------------------------------
            |
            | Track when the issue was found and resolved.
            |
            */

            // When this issue was first detected
            $table->timestamp('detected_at');

            // When this issue was marked as fixed or ignored
            $table->timestamp('resolved_at')->nullable();

            /*
            |------------------------------------------------------------------
            | Scan Run Relationship
            |------------------------------------------------------------------
            |
            | Links to the scan that discovered this issue.
            | cascadeOnDelete: Issues are deleted when scan run is deleted
            | (prevents orphaned issues from old scans)
            |
            */
            $table->foreignId('scan_run_id')
                ->nullable()
                ->constrained('seo_scan_runs')
                ->cascadeOnDelete();

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
            | Optimize common query patterns:
            |
            | - [issue_type, status]: Dashboard filtering by type and status
            | - [scannable_type, scannable_id]: Find all issues for a model
            | - severity: Sort/filter by priority
            |
            */
            $table->index(['issue_type', 'status'], 'seo_issues_type_status_index');
            $table->index(['scannable_type', 'scannable_id'], 'seo_issues_scannable_index');
            $table->index('severity', 'seo_issues_severity_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('seo_scan_issues');
    }
};
