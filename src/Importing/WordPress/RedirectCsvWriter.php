<?php

declare(strict_types=1);

namespace Rankbeam\Seo\Importing\WordPress;

/**
 * Writes redirect candidates to a CSV the operator imports into Rankbeam Pro.
 *
 * The `seo_redirects` table is a Pro feature, so a core importer must never
 * write it directly (that would couple core to Pro). Instead it emits a CSV
 * with the same shape as the Pro redirects table — `source_path`,
 * `target_url`, `status_code`, `note` — which the operator imports into Pro.
 *
 * The file is opened lazily on the first row, so a run that finds no redirect
 * candidates leaves no empty file behind. A dry run never opens the file.
 */
final class RedirectCsvWriter
{
    /** @var resource|null */
    private $handle = null;

    private bool $opened = false;

    public function __construct(
        public readonly ?string $path,
        private readonly bool $dryRun,
    ) {}

    /**
     * Whether redirect emission is switched on (the operator passed a path).
     */
    public function enabled(): bool
    {
        return $this->path !== null && $this->path !== '';
    }

    /**
     * Append one redirect candidate. No-op when disabled or on a dry run
     * (the caller still tallies it so the report is honest about what *would*
     * be written).
     */
    public function write(string $sourcePath, string $targetUrl, int $statusCode, string $note = ''): void
    {
        if (! $this->enabled() || $this->dryRun) {
            return;
        }

        if (! $this->opened) {
            $this->open();
        }

        if (is_resource($this->handle)) {
            fputcsv($this->handle, [$sourcePath, $targetUrl, $statusCode, $note], escape: '');
        }
    }

    public function close(): void
    {
        if (is_resource($this->handle)) {
            fclose($this->handle);
        }

        $this->handle = null;
    }

    private function open(): void
    {
        $this->opened = true;

        $directory = dirname((string) $this->path);

        if (! is_dir($directory)) {
            @mkdir($directory, 0775, true);
        }

        $handle = fopen((string) $this->path, 'w');

        if ($handle === false) {
            return;
        }

        $this->handle = $handle;
        fputcsv($this->handle, ['source_path', 'target_url', 'status_code', 'note'], escape: '');
    }
}
