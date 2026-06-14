<?php

declare(strict_types=1);

namespace Rankbeam\Seo\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Console\ConfirmableTrait;
use Rankbeam\Seo\Importing\Contracts\Importer;
use Rankbeam\Seo\Importing\ImporterRegistry;
use Rankbeam\Seo\Importing\ImportOptions;
use Rankbeam\Seo\Importing\ImportResult;

/**
 * Import SEO metadata from a competing Laravel package into core `seo_meta`.
 *
 * The data writer is a pluggable {@see Importer}
 * resolved by its source key. The only source shipped today is `ralphjsmit`
 * (the one competitor with a near-identical per-model morph table); the command
 * is built so new sources slot in without touching it.
 *
 * ## Usage
 * ```bash
 * # Preview what would be imported — writes nothing
 * php artisan seo:import-from ralphjsmit --dry-run
 *
 * # Import everything
 * php artisan seo:import-from ralphjsmit
 *
 * # Scope to specific models
 * php artisan seo:import-from ralphjsmit --model="App\Models\Post" --model="App\Models\Page"
 *
 * # Read a renamed table / a legacy connection, write the rows for a locale
 * php artisan seo:import-from ralphjsmit --table=legacy_seo --connection=legacy --locale=en
 *
 * # Machine-readable
 * php artisan seo:import-from ralphjsmit --json
 * ```
 *
 * The import is idempotent — re-running updates the same rows and never creates
 * duplicates — and only ever fills empty fields, so it is safe to run more than
 * once.
 */
class ImportFromCommand extends Command
{
    use ConfirmableTrait;

    /**
     * The name and signature of the console command.
     */
    protected $signature = 'seo:import-from
                            {source : The source package to import from (e.g. ralphjsmit)}
                            {--model=* : Restrict the import to these HasSEO model FQCNs (repeatable; default: all)}
                            {--locale= : Target locale for the imported seo_meta rows (defaults to the app locale)}
                            {--table= : Override the source table name (the importer default is used otherwise)}
                            {--connection= : Read the source table from this database connection (defaults to the default connection)}
                            {--limit=0 : Maximum source rows to import (0 = all)}
                            {--file= : Source file path (the WordPress CSV importer reads this)}
                            {--match-by= : Model column to match WordPress rows against (default: the model route key)}
                            {--post-type=* : Restrict the WordPress DB readers to these post types (repeatable; default: post + page)}
                            {--redirects-csv= : Emit redirect candidates to this CSV path for import into Rankbeam Pro}
                            {--site-url= : The old WordPress site URL, used to derive paths from absolute URLs}
                            {--dry-run : Report what would be imported without writing anything}
                            {--force : Skip the confirmation prompt}
                            {--json : Output the report as JSON instead of a table}';

    /**
     * The console command description.
     */
    protected $description = 'Import SEO metadata from a competing Laravel package (e.g. ralphjsmit) into seo_meta';

    /**
     * Execute the console command.
     */
    public function handle(ImporterRegistry $registry): int
    {
        $source = (string) $this->argument('source');
        $importer = $registry->get($source);

        if ($importer === null) {
            $this->error("Unknown import source [{$source}].");
            $available = $registry->keys();
            $this->line($available === []
                ? '  No importers are registered.'
                : '  Available sources: <comment>'.implode('</comment>, <comment>', $available).'</comment>');

            return self::FAILURE;
        }

        $options = new ImportOptions(
            dryRun: (bool) $this->option('dry-run'),
            models: array_values(array_unique((array) $this->option('model'))),
            locale: (string) ($this->option('locale') ?: $this->laravel->getLocale()),
            table: $this->option('table') ?: null,
            connection: $this->option('connection') ?: null,
            limit: max(0, (int) $this->option('limit')),
            extra: $this->extraOptions(),
        );

        $reason = null;

        if (! $importer->isAvailable($options, $reason)) {
            $this->error("Cannot import from {$importer->label()}: {$reason}");

            return self::FAILURE;
        }

        if (! $this->option('json')) {
            $this->line("Importing from <options=bold>{$importer->label()}</> ({$importer->sourceSummary($options)})");
            $this->line("Target locale: <comment>{$options->locale}</comment>");

            if ($options->dryRun) {
                $this->warn('DRY RUN — no changes will be written.');
            }

            $this->newLine();
        }

        // ConfirmableTrait: prompts only in production (or asks for --force);
        // in local/staging/testing it proceeds without blocking on input.
        if (! $options->dryRun && ! $this->confirmToProceed()) {
            return self::SUCCESS;
        }

        $result = $importer->import($options);

        if ($this->option('json')) {
            $this->line((string) json_encode(
                $result->toArray(),
                JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE,
            ));

            return self::SUCCESS;
        }

        $this->renderReport($result);

        return self::SUCCESS;
    }

    /**
     * Collect the importer-specific options, omitting any the operator did not
     * pass so each importer can apply its own defaults.
     *
     * @return array<string, mixed>
     */
    protected function extraOptions(): array
    {
        return array_filter([
            'file' => $this->option('file') ?: null,
            'match_by' => $this->option('match-by') ?: null,
            'post_type' => array_values(array_unique((array) $this->option('post-type'))),
            'redirects_csv' => $this->option('redirects-csv') ?: null,
            'site_url' => $this->option('site-url') ?: null,
        ], static fn ($value) => $value !== null && $value !== []);
    }

    protected function renderReport(ImportResult $result): void
    {
        $this->table(
            ['Outcome', 'Rows'],
            [
                ['<fg=green>Created</>', $result->created],
                ['<fg=cyan>Updated</>', $result->updated],
                ['<fg=gray>Unchanged</>', $result->unchanged],
                ['<fg=yellow>Skipped</>', $result->skippedCount()],
                ['<options=bold>Scanned</>', $result->scanned],
            ],
        );

        $this->line(sprintf(
            '<options=bold>%d</> source row(s) scanned — <fg=green>%d created</>, '
            .'<fg=cyan>%d updated</>, <fg=gray>%d unchanged</>, <fg=yellow>%d skipped</>.',
            $result->scanned,
            $result->created,
            $result->updated,
            $result->unchanged,
            $result->skippedCount(),
        ));

        $this->renderTruncations($result);
        $this->renderUnmapped($result);
        $this->renderRedirects($result);
        $this->renderSkips($result);
        $this->renderWarnings($result);

        if ($result->dryRun) {
            $this->newLine();
            $this->line('<options=bold>Dry run — nothing was written.</> Re-run without <comment>--dry-run</comment> to apply.');
        }
    }

    protected function renderTruncations(ImportResult $result): void
    {
        if ($result->truncations === []) {
            return;
        }

        $this->newLine();
        $this->line('<fg=yellow>Truncated to fit Core 3 columns</> (review these):');

        foreach ($result->truncations as $field => $count) {
            $this->line("  • {$field}: {$count} value(s)");
        }
    }

    protected function renderUnmapped(ImportResult $result): void
    {
        if ($result->unmapped === []) {
            return;
        }

        $this->newLine();
        $this->line('<fg=yellow>Not imported — no column in the Core 3 schema</>:');

        foreach ($result->unmapped as $field => $count) {
            $this->line("  • {$field}: {$count} row(s) had a value");
        }
    }

    protected function renderRedirects(ImportResult $result): void
    {
        if ($result->redirects === 0) {
            return;
        }

        $this->newLine();
        $this->line(sprintf(
            '<fg=cyan>%d redirect candidate(s)</> %s <comment>%s</comment>.',
            $result->redirects,
            $result->dryRun ? 'would be written to' : 'written to',
            $result->redirectsFile ?? '(no file)',
        ));
        $this->line('  Import them into Rankbeam <options=bold>Pro</> — `seo_redirects` is a Pro feature, so core never writes it directly.');
    }

    protected function renderSkips(ImportResult $result): void
    {
        if ($result->skipped === []) {
            return;
        }

        $this->newLine();
        $this->line('<fg=yellow>Skipped rows by reason</>:');

        foreach ($result->skipsByReason() as $reason => $count) {
            $this->line("  • {$reason}: {$count}");
        }
    }

    protected function renderWarnings(ImportResult $result): void
    {
        if ($result->warnings === []) {
            return;
        }

        $this->newLine();

        foreach ($result->warnings as $warning) {
            $this->warn($warning);
        }
    }
}
