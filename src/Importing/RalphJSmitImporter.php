<?php

declare(strict_types=1);

namespace Rankbeam\Seo\Importing;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Rankbeam\Seo\Traits\HasSEO;
use Throwable;

/**
 * Imports SEO data from {@link https://github.com/ralphjsmit/laravel-seo
 * ralphjsmit/laravel-seo} (~533k installs) into core `seo_meta`.
 *
 * ralphjsmit stores one polymorphic row per model in a `seo` table whose shape
 * is close to ours, which makes a clean bulk import possible:
 *
 *   ralphjsmit `seo` column   ->  core `seo_meta` column
 *   ----------------------------------------------------
 *   model_type / model_id     ->  seoable_type / seoable_id (re-resolved, see below)
 *   title                     ->  title          (trimmed to 70)
 *   description               ->  description    (trimmed to 160)
 *   canonical_url             ->  canonical
 *   robots                    ->  robots         (trimmed to 50)
 *   image                     ->  og_image       (twitter:image inherits it via the resolver)
 *   author                    ->  (no column — reported as unmapped, see below)
 *   id / created_at / updated_at ->  (structural — not copied)
 *
 * **Author is intentionally not copied.** Core 3's `seo_meta` has no author
 * column — the article author is a resolver-level concern, not stored social
 * meta. Rows that carry an author are counted and surfaced so the operator can
 * decide where (if anywhere) it should live, rather than the importer
 * inventing a column.
 *
 * **The morph type is re-resolved, not copied.** Each source row is resolved
 * to its real Eloquent model and the seoable keys are taken from that model's
 * own `getMorphClass()` / key. This makes the relation correct under the
 * *current* app's morph map even if ralphjsmit stored a different convention,
 * and lets us skip rows whose model has since been deleted.
 *
 * The import is idempotent (re-running updates the same row, never duplicates),
 * supports `--dry-run`, and only ever fills fields that have data — it never
 * clears existing core values.
 */
class RalphJSmitImporter extends AbstractImporter
{
    /**
     * ralphjsmit's default table name.
     */
    public const DEFAULT_TABLE = 'seo';

    public function key(): string
    {
        return 'ralphjsmit';
    }

    public function label(): string
    {
        return 'ralphjsmit/laravel-seo';
    }

    public function sourceSummary(ImportOptions $options): string
    {
        $connection = $options->connection ?? 'default';

        return sprintf('table `%s` on the %s connection', $this->table($options), $connection);
    }

    public function isAvailable(ImportOptions $options, ?string &$reason = null): bool
    {
        $table = $this->table($options);

        if (! Schema::connection($options->connection)->hasTable($table)) {
            $reason = sprintf(
                'source table `%s` was not found%s. Pass --table= if it was renamed, '
                .'or --connection= if the legacy data lives on another connection.',
                $table,
                $options->connection ? " on connection [{$options->connection}]" : '',
            );

            return false;
        }

        return true;
    }

    public function import(ImportOptions $options): ImportResult
    {
        $result = new ImportResult($options->dryRun);
        $warnedClasses = [];

        $acceptableTypes = null;

        if ($options->hasModelFilter()) {
            $acceptableTypes = $this->morphTypesFor($options->models);

            if ($acceptableTypes === []) {
                $result->warn('No valid model classes resolved from --model; nothing to import.');

                return $result;
            }
        }

        $handle = function (object $row) use ($options, $result, &$warnedClasses): void {
            $result->recordScanned();

            $data = (array) $row;
            $type = $data['model_type'] ?? null;
            $id = $data['model_id'] ?? null;

            try {
                $class = $this->resolveModelClass(is_string($type) ? $type : null);

                if ($class === null) {
                    $result->skip($id, is_string($type) ? $type : null, 'unresolved model type');

                    return;
                }

                /** @var Model|null $model */
                $model = $class::query()->find($id);

                if ($model === null) {
                    $result->skip($id, $class, 'source model no longer exists');

                    return;
                }

                if (! in_array(HasSEO::class, class_uses_recursive($model), true) && ! isset($warnedClasses[$class])) {
                    $warnedClasses[$class] = true;
                    $result->warn(sprintf(
                        '%s does not use the Rankbeam HasSEO trait — its seo_meta was written directly; '
                        .'add the trait to read it back via $model->seoMeta.',
                        $class,
                    ));
                }

                $attributes = $this->mapAttributes($data, $result);

                if ($attributes === []) {
                    $result->skip($id, $class, 'empty source row (nothing to import)');

                    return;
                }

                $this->writeSeoMeta($model, $options->locale, $attributes, $options, $result);
            } catch (Throwable $e) {
                $result->skip($id, is_string($type) ? $type : null, 'error: '.$this->shortMessage($e));
            }
        };

        // chunkById buffers each page before the callback writes, so the source
        // read never overlaps a seo_meta write on the same (e.g. SQLite)
        // connection. The limit is enforced per-row so it cannot collide with
        // chunkById's own pagination window.
        $remaining = $options->limit > 0 ? $options->limit : null;

        $this->query($options, $acceptableTypes)->chunkById(500, function ($rows) use ($handle, &$remaining): bool {
            foreach ($rows as $row) {
                $handle($row);

                if ($remaining !== null && --$remaining <= 0) {
                    return false;
                }
            }

            return true;
        }, 'id');

        return $result;
    }

    /**
     * Map a raw ralphjsmit row to core `seo_meta` attributes, including only
     * fields that actually have data (so existing core values are never
     * cleared), and counting the unmapped `author` column.
     *
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    protected function mapAttributes(array $row, ImportResult $result): array
    {
        $attributes = array_filter([
            'title' => $this->fit($this->clean($row['title'] ?? null), 'title', $result),
            'description' => $this->fit($this->clean($row['description'] ?? null), 'description', $result),
            'canonical' => $this->clean($row['canonical_url'] ?? null),
            'robots' => $this->fit($this->clean($row['robots'] ?? null), 'robots', $result),
            'og_image' => $this->clean($row['image'] ?? null),
        ], static fn ($value) => $value !== null);

        // author has no home in the Core 3 schema — report the value (so the
        // operator can re-home it via getSEOAuthor()), never copy it.
        $author = $this->clean($row['author'] ?? null);

        if ($author !== null) {
            $result->unmapped('author', $author);
        }

        return $attributes;
    }

    /**
     * Build the source query, scoped to the accepted morph types. Ordering and
     * the row limit are handled by the chunked iteration in import().
     *
     * @param  array<int, string>|null  $acceptableTypes
     */
    protected function query(ImportOptions $options, ?array $acceptableTypes): Builder
    {
        $query = DB::connection($options->connection)
            ->table($this->table($options));

        if ($acceptableTypes !== null) {
            $query->whereIn('model_type', $acceptableTypes);
        }

        return $query;
    }

    protected function table(ImportOptions $options): string
    {
        return $options->table ?? self::DEFAULT_TABLE;
    }
}
