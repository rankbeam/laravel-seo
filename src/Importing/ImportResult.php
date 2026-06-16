<?php

declare(strict_types=1);

namespace Rankbeam\Seo\Importing;

/**
 * A mutable accumulator describing the outcome of an import run.
 *
 * Importers record every meaningful event here (a created / updated /
 * unchanged row, a skipped row with a reason, a truncated over-length value,
 * a source column that has data but no home in the Core 3 schema). The command
 * then renders it as a table or as JSON. Keeping the tallying out of the
 * importer keeps the importer focused on the mapping.
 */
final class ImportResult
{
    /**
     * The most distinct values we keep per unmapped field (e.g. author names).
     * Authors are low-cardinality, so this comfortably holds "every" value for a
     * real migration while bounding memory against a pathological source.
     */
    public const MAX_UNMAPPED_VALUES = 500;

    /** Total source rows examined (after any model-scope filter). */
    public int $scanned = 0;

    /** Rows that produced a brand-new seo_meta record. */
    public int $created = 0;

    /** Rows that updated an existing seo_meta record. */
    public int $updated = 0;

    /** Rows whose mapped values already matched the stored record (true no-ops). */
    public int $unchanged = 0;

    /**
     * Rows skipped specifically because the source row described a URL/page that
     * matched no Eloquent model (or no `--model` was given), so it could not
     * become a polymorphic `seo_meta` row. A first-class count in the
     * verification report — these are the rows a migration most needs to eyeball.
     */
    public int $urlOnly = 0;

    /**
     * Rows that were skipped, each with a stable reason key.
     *
     * @var array<int, array{key: int|string|null, type: string|null, reason: string}>
     */
    public array $skipped = [];

    /**
     * Free-form warnings (one-off conditions worth surfacing once).
     *
     * @var array<int, string>
     */
    public array $warnings = [];

    /**
     * Per-target-field count of values truncated to fit the Core 3 column.
     *
     * @var array<string, int>
     */
    public array $truncations = [];

    /**
     * Per-source-column count of rows that held data the Core 3 schema does
     * not store (so it was deliberately dropped, not silently copied).
     *
     * @var array<string, int>
     */
    public array $unmapped = [];

    /**
     * The distinct *values* dropped for unmapped fields the operator needs to
     * see verbatim — above all `author`, which has no Core 3 column but must
     * never silently vanish in a migration (the operator re-homes it via a
     * `getSEOAuthor()` hook). Counts live in {@see self::$unmapped}; only
     * curated, low-cardinality, actionable fields capture their values here.
     *
     * @var array<string, array<int, string>>
     */
    public array $unmappedValues = [];

    /**
     * Number of redirect candidates emitted to the redirects CSV (the
     * `seo_redirects` table is a Pro feature, so an importer in core never
     * writes it directly — it hands the operator a CSV to import). Zero for
     * importers that do not deal in redirects.
     */
    public int $redirects = 0;

    /**
     * Absolute path of the redirects CSV that was (or would be) written, when
     * the run emitted any redirect candidates. Null otherwise.
     */
    public ?string $redirectsFile = null;

    public function __construct(
        public readonly bool $dryRun = false,
    ) {}

    public function recordScanned(): void
    {
        $this->scanned++;
    }

    /**
     * Record a write outcome by its status string.
     */
    public function recordStatus(string $status): void
    {
        match ($status) {
            'created' => $this->created++,
            'updated' => $this->updated++,
            default => $this->unchanged++,
        };
    }

    public function skip(int|string|null $key, ?string $type, string $reason): void
    {
        $this->skipped[] = ['key' => $key, 'type' => $type, 'reason' => $reason];
    }

    /**
     * Record a row that could not attach to a model (no match / no `--model`).
     * It is both counted as URL-only for the verification report and recorded
     * as a skip so it still appears under skips-by-reason.
     */
    public function urlOnly(int|string|null $key, ?string $type, string $reason): void
    {
        $this->urlOnly++;
        $this->skip($key, $type, $reason);
    }

    public function warn(string $message): void
    {
        if (! in_array($message, $this->warnings, true)) {
            $this->warnings[] = $message;
        }
    }

    public function truncated(string $field): void
    {
        $this->truncations[$field] = ($this->truncations[$field] ?? 0) + 1;
    }

    /**
     * Record that a source field held data with no Core 3 home. Always counts
     * the occurrence; when $value is given (e.g. an author name), the distinct
     * value is also retained — bounded by {@see self::MAX_UNMAPPED_VALUES} —
     * so the verification report can list exactly what was dropped.
     */
    public function unmapped(string $field, ?string $value = null): void
    {
        $this->unmapped[$field] = ($this->unmapped[$field] ?? 0) + 1;

        if ($value === null) {
            return;
        }

        $value = trim($value);

        if ($value === '') {
            return;
        }

        $existing = $this->unmappedValues[$field] ?? [];

        if (in_array($value, $existing, true) || count($existing) >= self::MAX_UNMAPPED_VALUES) {
            return;
        }

        $existing[] = $value;
        $this->unmappedValues[$field] = $existing;
    }

    /**
     * Record a redirect candidate emitted to the redirects CSV.
     */
    public function redirectEmitted(): void
    {
        $this->redirects++;
    }

    /**
     * Total rows that resulted in (or would result in) a database write.
     */
    public function writes(): int
    {
        return $this->created + $this->updated;
    }

    /**
     * Rows that attached to a model — created, updated, or already current.
     * The complement of url-only/skipped, for the verification report.
     */
    public function matched(): int
    {
        return $this->created + $this->updated + $this->unchanged;
    }

    /**
     * The verification report: a single, self-describing structure an operator
     * can read (or archive via `--json`) to confirm exactly what the import did
     * BEFORE removing the legacy package/table — how many rows attached to a
     * model, how many were URL-only, what was truncated to fit, and what was
     * dropped for lack of a Core 3 column (with every distinct author value).
     *
     * @return array{
     *     matched: int, created: int, updated: int, unchanged: int,
     *     url_only: int, skipped: int,
     *     truncated: array<string, int>,
     *     unmapped: array<string, int>,
     *     unmapped_values: array<string, array<int, string>>,
     * }
     */
    public function verification(): array
    {
        return [
            'matched' => $this->matched(),
            'created' => $this->created,
            'updated' => $this->updated,
            'unchanged' => $this->unchanged,
            'url_only' => $this->urlOnly,
            'skipped' => $this->skippedCount(),
            'truncated' => $this->truncations,
            'unmapped' => $this->unmapped,
            'unmapped_values' => $this->unmappedValues,
        ];
    }

    public function skippedCount(): int
    {
        return count($this->skipped);
    }

    /**
     * Skip counts grouped by their reason, for a compact summary.
     *
     * @return array<string, int>
     */
    public function skipsByReason(): array
    {
        $grouped = [];

        foreach ($this->skipped as $skip) {
            $grouped[$skip['reason']] = ($grouped[$skip['reason']] ?? 0) + 1;
        }

        arsort($grouped);

        return $grouped;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'dry_run' => $this->dryRun,
            'summary' => [
                'scanned' => $this->scanned,
                'created' => $this->created,
                'updated' => $this->updated,
                'unchanged' => $this->unchanged,
                'skipped' => $this->skippedCount(),
                'writes' => $this->writes(),
            ],
            'verification' => $this->verification(),
            'truncations' => $this->truncations,
            'unmapped' => $this->unmapped,
            'unmapped_values' => $this->unmappedValues,
            'redirects' => [
                'emitted' => $this->redirects,
                'file' => $this->redirectsFile,
            ],
            'skipped' => $this->skipped,
            'warnings' => $this->warnings,
        ];
    }
}
