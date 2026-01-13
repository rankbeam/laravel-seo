<?php

declare(strict_types=1);

namespace Fibonoir\LaravelSEO\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * SEO scan run tracking.
 *
 * Represents a single execution of the SEO scanner, tracking progress,
 * configuration, and results. Each run can discover multiple issues.
 *
 * Scan Types:
 * - 'full': Complete site scan, checks every page
 * - 'incremental': Only pages modified since last scan
 * - 'targeted': Specific model class or single URL
 *
 * Status Workflow:
 * - pending → running → completed (success)
 * - pending → running → failed (error)
 *
 * @property int $id
 * @property string $type
 * @property string $status
 * @property int $total_pages
 * @property int $scanned_pages
 * @property int $issues_found
 * @property array|null $options
 * @property string|null $error_message
 * @property \Carbon\Carbon|null $started_at
 * @property \Carbon\Carbon|null $completed_at
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 *
 * @see \Fibonoir\LaravelSEO\Services\Scanner\SitewideScanner
 * @see \Fibonoir\LaravelSEO\Jobs\ScanSitewideJob
 */
class SEOScanRun extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'seo_scan_runs';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'type',
        'status',
        'total_pages',
        'scanned_pages',
        'issues_found',
        'options',
        'error_message',
        'started_at',
        'completed_at',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'options' => 'array',
        'total_pages' => 'integer',
        'scanned_pages' => 'integer',
        'issues_found' => 'integer',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    /**
     * The model's default attribute values.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'pending',
        'total_pages' => 0,
        'scanned_pages' => 0,
        'issues_found' => 0,
    ];

    /**
     * Scan type constants.
     */
    public const TYPE_FULL = 'full';
    public const TYPE_INCREMENTAL = 'incremental';
    public const TYPE_TARGETED = 'targeted';

    /**
     * Status constants.
     */
    public const STATUS_PENDING = 'pending';
    public const STATUS_RUNNING = 'running';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_FAILED = 'failed';

    /**
     * Get the issues discovered by this scan run.
     *
     * @return HasMany<SEOScanIssue>
     */
    public function issues(): HasMany
    {
        return $this->hasMany(SEOScanIssue::class, 'scan_run_id');
    }

    /**
     * Mark the scan as started.
     *
     * Sets status to 'running' and records the start time.
     */
    public function start(): void
    {
        $this->update([
            'status' => self::STATUS_RUNNING,
            'started_at' => now(),
        ]);
    }

    /**
     * Mark the scan as completed.
     *
     * Sets status to 'completed' and records the completion time.
     */
    public function complete(): void
    {
        $this->update([
            'status' => self::STATUS_COMPLETED,
            'completed_at' => now(),
        ]);
    }

    /**
     * Mark the scan as failed with an error message.
     *
     * @param string $message The error message (exception message, etc.)
     */
    public function fail(string $message): void
    {
        $this->update([
            'status' => self::STATUS_FAILED,
            'error_message' => $message,
            'completed_at' => now(),
        ]);
    }

    /**
     * Increment the progress counters.
     *
     * @param int $issues Number of issues found in this increment
     */
    public function incrementProgress(int $issues = 0): void
    {
        $this->increment('scanned_pages');

        if ($issues > 0) {
            $this->increment('issues_found', $issues);
        }
    }

    /**
     * Set the total number of pages to scan.
     *
     * @param int $total Total pages count
     */
    public function setTotal(int $total): void
    {
        $this->update(['total_pages' => $total]);
    }

    /**
     * Get the progress percentage.
     *
     * @return float Progress as a percentage (0-100)
     */
    public function getProgressPercentage(): float
    {
        if ($this->total_pages === 0) {
            return 0.0;
        }

        return round(($this->scanned_pages / $this->total_pages) * 100, 2);
    }

    /**
     * Get the scan duration in seconds.
     *
     * @return int|null Duration in seconds, or null if not completed
     */
    public function getDurationInSeconds(): ?int
    {
        if (! $this->started_at || ! $this->completed_at) {
            return null;
        }

        return $this->completed_at->diffInSeconds($this->started_at);
    }

    /**
     * Check if the scan is currently running.
     *
     * @return bool
     */
    public function isRunning(): bool
    {
        return $this->status === self::STATUS_RUNNING;
    }

    /**
     * Check if the scan has completed (successfully or with failure).
     *
     * @return bool
     */
    public function isFinished(): bool
    {
        return in_array($this->status, [self::STATUS_COMPLETED, self::STATUS_FAILED], true);
    }

    /**
     * Scope to filter by status.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param string $status
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeByStatus($query, string $status)
    {
        return $query->where('status', $status);
    }

    /**
     * Scope to filter by type.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param string $type
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeByType($query, string $type)
    {
        return $query->where('type', $type);
    }

    /**
     * Scope to get the latest completed scan.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeLatestCompleted($query)
    {
        return $query->where('status', self::STATUS_COMPLETED)
            ->latest('completed_at');
    }
}
