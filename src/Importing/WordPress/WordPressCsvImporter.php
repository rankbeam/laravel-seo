<?php

declare(strict_types=1);

namespace Rankbeam\Seo\Importing\WordPress;

use Illuminate\Database\Eloquent\Model;
use Rankbeam\Seo\Importing\ImportOptions;
use Rankbeam\Seo\Importing\ImportResult;
use Throwable;

/**
 * Imports SEO metadata from a WordPress CSV export into core `seo_meta`.
 *
 * This is the path that covers most agency migrations: an SEO plugin (or a
 * generic exporter) produces a CSV with one row per URL. The importer reads a
 * fixed, documented header:
 *
 *   url, title, description, canonical, robots, focus_keyword
 *
 * Columns may appear in any order; unrecognised columns are ignored (and
 * reported once). Each row's slug (the last path segment of `url`) is matched
 * to a content model — `--model="App\Models\Post"`, matched on the model route
 * key or `--match-by=` — and the metadata is written onto that model's
 * `seo_meta` row. Rows that match nothing are reported as URL-only.
 *
 * When `--redirects-csv=` is set, a row whose `canonical` points to a different
 * path than its own `url` becomes a redirect candidate in that CSV (for import
 * into Rankbeam Pro — core never writes `seo_redirects`).
 *
 * Idempotent and `--dry-run` aware via the inherited write path.
 */
class WordPressCsvImporter extends WordPressImporter
{
    /**
     * The recognised CSV header columns. Anything else is ignored.
     *
     * @var array<int, string>
     */
    public const COLUMNS = ['url', 'title', 'description', 'canonical', 'robots', 'focus_keyword'];

    public function key(): string
    {
        return 'wordpress-csv';
    }

    public function label(): string
    {
        return 'WordPress (CSV export)';
    }

    public function sourceSummary(ImportOptions $options): string
    {
        return sprintf('CSV file `%s`', (string) $options->extra('file', '(none)'));
    }

    public function isAvailable(ImportOptions $options, ?string &$reason = null): bool
    {
        $file = $options->extra('file');

        if ($file === null) {
            $reason = 'no source file given. Pass --file=path/to/export.csv (header: '
                .implode(',', self::COLUMNS).').';

            return false;
        }

        if (! is_file($file) || ! is_readable($file)) {
            $reason = sprintf('the CSV file [%s] does not exist or is not readable.', $file);

            return false;
        }

        return true;
    }

    public function import(ImportOptions $options): ImportResult
    {
        $result = new ImportResult($options->dryRun);
        $writer = new RedirectCsvWriter($options->extra('redirects_csv'), $options->dryRun);

        $modelClass = $this->targetModelClass($options, $result);
        $matchBy = $options->extra('match_by');
        $remaining = $options->limit > 0 ? $options->limit : null;

        $handle = fopen((string) $options->extra('file'), 'r');

        if ($handle === false) {
            $result->warn('Could not open the CSV file for reading.');

            return $result;
        }

        try {
            $header = $this->readHeader($handle, $result);

            if ($header === null) {
                return $result;
            }

            while (($row = $this->readRow($handle)) !== false) {
                if ($this->isBlankRow($row)) {
                    continue;
                }

                $result->recordScanned();

                if (count($row) !== count($header)) {
                    $result->skip(null, null, 'malformed row (column count does not match the header)');

                    continue;
                }

                /** @var array<string, string|null> $data */
                $data = array_combine($header, $row);

                $this->handleRow($data, $options, $modelClass, $matchBy, $writer, $result);

                if ($remaining !== null && --$remaining <= 0) {
                    break;
                }
            }
        } finally {
            fclose($handle);
            $writer->close();
        }

        return $result;
    }

    /**
     * Process one decoded CSV row.
     *
     * @param  array<string, string|null>  $data
     * @param  class-string<Model>|null  $modelClass
     */
    protected function handleRow(
        array $data,
        ImportOptions $options,
        ?string $modelClass,
        ?string $matchBy,
        RedirectCsvWriter $writer,
        ImportResult $result,
    ): void {
        $url = $this->clean($data['url'] ?? null);

        if ($url === null) {
            $result->skip(null, null, 'malformed row (missing url)');

            return;
        }

        // A canonical that points elsewhere is a redirect candidate regardless
        // of whether the row also attaches to a model.
        $this->maybeEmitCanonicalRedirect(
            $writer,
            $result,
            $url,
            $this->clean($data['canonical'] ?? null),
            'WordPress CSV canonical for '.$url,
        );

        $attributes = $this->mapAttributes($data, $result);

        if ($attributes === []) {
            $result->skip($url, null, 'empty source row (nothing to import)');

            return;
        }

        $slug = $this->slugFromUrl($url);

        try {
            $model = $this->matchModel($modelClass, $matchBy, $slug);
        } catch (Throwable $e) {
            $result->skip($url, $modelClass, 'match error: '.$this->shortMessage($e));

            return;
        }

        if ($model === null) {
            $result->skip($url, $modelClass, $this->urlOnlyReason($modelClass, $slug));

            return;
        }

        $this->writeSeoMeta($model, $options->locale, $attributes, $options, $result);
    }

    /**
     * Map a CSV row to core `seo_meta` attributes, including only fields that
     * carry data (so existing core values are never cleared).
     *
     * @param  array<string, string|null>  $data
     * @return array<string, mixed>
     */
    protected function mapAttributes(array $data, ImportResult $result): array
    {
        $attributes = array_filter([
            'title' => $this->fit($this->clean($data['title'] ?? null), 'title', $result),
            'description' => $this->fit($this->clean($data['description'] ?? null), 'description', $result),
            'canonical' => $this->clean($data['canonical'] ?? null),
            'robots' => $this->fit($this->clean($data['robots'] ?? null), 'robots', $result),
        ], static fn ($value) => $value !== null);

        $keywords = $this->focusKeywords($this->clean($data['focus_keyword'] ?? null));

        if ($keywords !== null) {
            $attributes['focus_keywords'] = $keywords;
        }

        return $attributes;
    }

    /**
     * Read and validate the header row. Returns the normalised column names, or
     * null when the file is empty or has no usable `url` column.
     *
     * @param  resource  $handle
     * @return array<int, string>|null
     */
    protected function readHeader($handle, ImportResult $result): ?array
    {
        while (($row = $this->readRow($handle)) !== false) {
            if ($this->isBlankRow($row)) {
                continue;
            }

            $header = array_map(static fn ($value): string => strtolower(trim((string) $value)), $row);

            $unknown = array_values(array_diff($header, self::COLUMNS));

            if ($unknown !== []) {
                $result->warn('Ignoring unrecognised CSV column(s): '.implode(', ', $unknown).'.');
            }

            if (! in_array('url', $header, true)) {
                $result->warn(
                    'The CSV has no "url" column, so rows cannot be matched to pages. '
                    .'Expected header: '.implode(',', self::COLUMNS).'.',
                );

                return null;
            }

            return $header;
        }

        $result->warn('The CSV file is empty.');

        return null;
    }

    /**
     * Read one CSV record with a disabled escape character (the modern,
     * deterministic behaviour — no proprietary backslash escaping).
     *
     * @param  resource  $handle
     * @return array<int, string|null>|false
     */
    protected function readRow($handle): array|false
    {
        return fgetcsv($handle, 0, ',', '"', '');
    }

    /**
     * A row that is entirely empty (e.g. a trailing blank line) is not a record.
     *
     * @param  array<int, string|null>  $row
     */
    protected function isBlankRow(array $row): bool
    {
        foreach ($row as $value) {
            if ($value !== null && trim((string) $value) !== '') {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  class-string<Model>|null  $modelClass
     */
    protected function urlOnlyReason(?string $modelClass, ?string $slug): string
    {
        if ($modelClass === null) {
            return 'url-only (no --model given to attach metadata to)';
        }

        return sprintf('url-only (no %s matched slug "%s")', class_basename($modelClass), (string) $slug);
    }
}
