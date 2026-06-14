<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Rankbeam\Seo\Models\SEOMeta;

/**
 * Core 3.0 resolution correction: make stored social-card defaults optional.
 *
 * The published create migration is immutable, so this migration runs on both
 * paths:
 * - Fresh install: the create migration builds the columns with v2 defaults,
 *   then this makes them nullable and drops those defaults.
 * - Upgrade from v2: existing stored values are preserved while new rows may
 *   leave the columns NULL so computed resolver values can survive.
 *
 * Idempotent on missing or partially-migrated schemas: the table and each
 * column are checked before alteration.
 *
 * IRREVERSIBLE: down() is intentionally a no-op. Restoring defaults would
 * reintroduce the resolver behavior this migration exists to correct.
 *
 * @see SEOMeta
 */
return new class extends Migration
{
    /**
     * Run the migration.
     */
    public function up(): void
    {
        if (! Schema::hasTable('seo_meta')) {
            return;
        }

        if (Schema::hasColumn('seo_meta', 'og_type')) {
            Schema::table('seo_meta', function (Blueprint $table): void {
                $table->string('og_type', 30)->nullable()->default(null)->change();
            });
        }

        if (Schema::hasColumn('seo_meta', 'twitter_card')) {
            Schema::table('seo_meta', function (Blueprint $table): void {
                $table->string('twitter_card', 30)->nullable()->default(null)->change();
            });
        }
    }

    /**
     * Reverse the migration.
     *
     * Intentionally irreversible - see the class docblock.
     */
    public function down(): void
    {
        // No-op: restoring defaults would restore the bug.
    }
};
