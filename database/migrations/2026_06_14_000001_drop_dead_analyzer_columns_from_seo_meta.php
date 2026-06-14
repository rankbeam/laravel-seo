<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Core 3.0 contract reset: drop the dead content-analyzer columns from
 * seo_meta.
 *
 * The v2 create_seo_meta_table migration shipped six columns and two indexes
 * for a content-analyzer subsystem that never shipped — nothing in the
 * released core or Pro ever wrote a value to them. Core 3 sheds them: the
 * numerical SEO score becomes a Pro-owned scan-result field (with its own
 * stored rubric_version), and core keeps only the metadata contract.
 *
 * The published create migration is immutable, so this is a separate cleanup
 * migration. It runs on both paths:
 * - Fresh install: create_seo_meta_table builds the columns, then this drops
 *   them, netting the correct v3 schema.
 * - Upgrade from a released v2 install: this drops the columns in place.
 *
 * `focus_keywords` is KEPT — it is core-owned and read by the Pro scanner.
 *
 * Idempotent and safe on partially-migrated schemas: every index and column
 * is checked for existence before being dropped, so re-running, or running
 * against a schema where some of these were already removed by hand, is a
 * no-op rather than an error.
 *
 * IRREVERSIBLE: down() is intentionally a no-op. These columns only ever held
 * NULLs in shipped installs, so there is nothing to restore; re-creating empty
 * columns would only resurrect the dead schema this migration exists to remove.
 *
 * @see \Rankbeam\Seo\Models\SEOMeta
 */
return new class extends Migration
{
    /**
     * The dead analyzer indexes, dropped before their columns.
     *
     * @var array<int, string>
     */
    private array $deadIndexes = [
        'seo_meta_score_index',
        'seo_meta_analyzed_index',
    ];

    /**
     * The dead analyzer columns. `focus_keywords` is deliberately absent.
     *
     * @var array<int, string>
     */
    private array $deadColumns = [
        'seo_score',
        'analysis_report',
        'analyzed_at',
        'content_snapshot',
        'content_hash',
        'snapshot_at',
    ];

    /**
     * Run the migration.
     */
    public function up(): void
    {
        if (! Schema::hasTable('seo_meta')) {
            return;
        }

        // Drop the dead indexes first, each guarded so a partially-migrated
        // schema (index already gone) does not error.
        $indexesToDrop = array_values(array_filter(
            $this->deadIndexes,
            fn (string $index): bool => Schema::hasIndex('seo_meta', $index),
        ));

        if ($indexesToDrop !== []) {
            Schema::table('seo_meta', function (Blueprint $table) use ($indexesToDrop): void {
                foreach ($indexesToDrop as $index) {
                    $table->dropIndex($index);
                }
            });
        }

        // Then drop the dead columns that are still present.
        $columnsToDrop = array_values(array_filter(
            $this->deadColumns,
            fn (string $column): bool => Schema::hasColumn('seo_meta', $column),
        ));

        if ($columnsToDrop !== []) {
            // Shipped core/Pro never wrote these columns, but the v2 public
            // API (HasSEO::updateScore etc.) was callable, so a customer's own
            // code *could* have populated them. The drop is irreversible and
            // does NOT migrate values (core cannot depend on Pro, which now
            // owns the score), so make any data loss VISIBLE rather than
            // silent — without aborting the upgrade.
            $this->warnIfColumnsHoldData($columnsToDrop);

            Schema::table('seo_meta', function (Blueprint $table) use ($columnsToDrop): void {
                $table->dropColumn($columnsToDrop);
            });
        }
    }

    /**
     * Log a warning (with row counts) if any column about to be dropped holds
     * non-null data. Non-fatal by design: aborting here would block the whole
     * upgrade over columns that were always null in every shipped install.
     *
     * @param  array<int, string>  $columns
     */
    private function warnIfColumnsHoldData(array $columns): void
    {
        // One cheap existence check first (a fresh install is empty, and even
        // an upgraded one almost always has all-null columns), so the
        // per-column counts only run on the rare table that actually holds data.
        $anyData = DB::table('seo_meta')
            ->where(function ($query) use ($columns): void {
                foreach ($columns as $column) {
                    $query->orWhereNotNull($column);
                }
            })
            ->exists();

        if (! $anyData) {
            return;
        }

        $populated = [];

        foreach ($columns as $column) {
            $count = DB::table('seo_meta')->whereNotNull($column)->count();

            if ($count > 0) {
                $populated[] = "{$column} ({$count} row(s))";
            }
        }

        Log::warning(
            '[rankbeam/laravel-seo] Core 3 cleanup migration is permanently dropping '
            .'analyzer columns that contain data: '.implode(', ', $populated).'. '
            .'These values are NOT migrated — the numeric SEO score is now Pro-owned '
            .'(run the Pro scan to regenerate it). See UPGRADING.md.'
        );
    }

    /**
     * Reverse the migration.
     *
     * Intentionally irreversible — see the class docblock. The dropped columns
     * only ever held NULLs, so there is nothing to restore.
     */
    public function down(): void
    {
        // No-op: this migration is irreversible by design.
    }
};
