<?php

declare(strict_types=1);

namespace Rankbeam\Seo\Importing;

/**
 * Immutable options for an SEO data import run.
 *
 * Shared by every {@see Contracts\Importer}. A concrete importer applies its
 * own sensible defaults for any value left null here (e.g. the source table
 * name), so the command only has to pass through what the operator overrode.
 */
final class ImportOptions
{
    /**
     * @param  bool  $dryRun  Report what would happen without writing anything.
     * @param  array<int, string>  $models  Restrict the import to these model FQCNs (empty = all).
     * @param  string  $locale  The locale the imported seo_meta rows are written for.
     * @param  string|null  $table  Override the source table name (null = importer default).
     * @param  string|null  $connection  Override the connection the source is read from (null = default).
     * @param  int  $limit  Maximum source rows to import (0 = all).
     * @param  array<string, mixed>  $extra  Importer-specific options the command forwards verbatim
     *                                       (e.g. the WordPress importer's `file`, `match_by`,
     *                                       `post_type`, `redirects_csv`, `site_url`). The shared
     *                                       DTO stays source-agnostic; each importer reads what it
     *                                       needs and ignores the rest.
     * @param  bool  $overwrite  Replace existing non-empty seo_meta values with the imported ones.
     *                           Off by default, so an import only ever FILLS empty fields and can
     *                           never clobber hand-edited Rankbeam metadata.
     */
    public function __construct(
        public readonly bool $dryRun = false,
        public readonly array $models = [],
        public readonly string $locale = 'en',
        public readonly ?string $table = null,
        public readonly ?string $connection = null,
        public readonly int $limit = 0,
        public readonly array $extra = [],
        public readonly bool $overwrite = false,
    ) {}

    /**
     * Whether the run is scoped to an explicit set of model classes.
     */
    public function hasModelFilter(): bool
    {
        return $this->models !== [];
    }

    /**
     * Read an importer-specific option, falling back to $default when absent
     * or empty.
     */
    public function extra(string $key, mixed $default = null): mixed
    {
        $value = $this->extra[$key] ?? null;

        if ($value === null || $value === '' || $value === []) {
            return $default;
        }

        return $value;
    }
}
