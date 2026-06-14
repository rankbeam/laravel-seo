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
    /** Total source rows examined (after any model-scope filter). */
    public int $scanned = 0;

    /** Rows that produced a brand-new seo_meta record. */
    public int $created = 0;

    /** Rows that updated an existing seo_meta record. */
    public int $updated = 0;

    /** Rows whose mapped values already matched the stored record (true no-ops). */
    public int $unchanged = 0;

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

    public function unmapped(string $field): void
    {
        $this->unmapped[$field] = ($this->unmapped[$field] ?? 0) + 1;
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
            'truncations' => $this->truncations,
            'unmapped' => $this->unmapped,
            'redirects' => [
                'emitted' => $this->redirects,
                'file' => $this->redirectsFile,
            ],
            'skipped' => $this->skipped,
            'warnings' => $this->warnings,
        ];
    }
}
