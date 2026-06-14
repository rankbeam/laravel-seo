<?php

declare(strict_types=1);

namespace Rankbeam\Seo\Importing\Contracts;

use Rankbeam\Seo\Importing\ImporterRegistry;
use Rankbeam\Seo\Importing\ImportOptions;
use Rankbeam\Seo\Importing\ImportResult;

/**
 * An importer reads SEO data from a competing package's storage and writes it
 * into core `seo_meta`.
 *
 * Implementations are resolved by their {@see self::key()} via the
 * {@see ImporterRegistry} and driven by the
 * `seo:import-from` command. New sources (e.g. the RT13 WordPress importer)
 * implement this contract and register a key.
 */
interface Importer
{
    /**
     * The stable CLI key for this source, e.g. "ralphjsmit".
     */
    public function key(): string;

    /**
     * A human label for the source package, e.g. "ralphjsmit/laravel-seo".
     */
    public function label(): string;

    /**
     * A one-line description of where the data is read from, given the options
     * (e.g. the resolved table + connection). Shown in the run header.
     */
    public function sourceSummary(ImportOptions $options): string;

    /**
     * Whether the source is present and readable (e.g. the table exists). When
     * false, $reason is filled with an actionable explanation.
     */
    public function isAvailable(ImportOptions $options, ?string &$reason = null): bool;

    /**
     * Perform the import and return the accumulated result. Honours
     * $options->dryRun (report-only) and must be idempotent: a second run over
     * unchanged source data produces no new rows and no churn.
     */
    public function import(ImportOptions $options): ImportResult;
}
