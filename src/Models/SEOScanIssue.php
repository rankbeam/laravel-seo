<?php

declare(strict_types=1);

namespace Fibonoir\LaravelSEO\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Individual SEO issue discovered during scans.
 *
 * Represents a single SEO problem found on a page or model. Issues are
 * linked to scan runs and can optionally be linked to specific models
 * via polymorphic relationship.
 *
 * Issue Types:
 * - duplicate_title, duplicate_description
 * - missing_title, missing_description, missing_h1
 * - title_too_long, title_too_short
 * - broken_link, broken_image
 * - missing_alt_text, missing_canonical
 * - thin_content, keyword_stuffing
 * - invalid_schema, invalid_robots
 *
 * Severity Levels:
 * - 'critical': Must fix immediately (duplicate content, no title)
 * - 'warning': Should fix soon (missing description, thin content)
 * - 'notice': Nice to have (missing power words, short paragraphs)
 *
 * Status Workflow:
 * - open → fixed (resolved by content change)
 * - open → ignored (intentionally skipped)
 *
 * @property int $id
 * @property string|null $scannable_type
 * @property int|null $scannable_id
 * @property string|null $url
 * @property string $issue_type
 * @property string $severity
 * @property string|null $field
 * @property string $message
 * @property array|null $context
 * @property string $status
 * @property \Carbon\Carbon $detected_at
 * @property \Carbon\Carbon|null $resolved_at
 * @property int|null $scan_run_id
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 *
 * @see \Fibonoir\LaravelSEO\Services\Scanner\PageScanner
 */
class SEOScanIssue extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'seo_scan_issues';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'scannable_type',
        'scannable_id',
        'url',
        'issue_type',
        'severity',
        'field',
        'message',
        'context',
        'status',
        'detected_at',
        'resolved_at',
        'scan_run_id',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'context' => 'array',
        'detected_at' => 'datetime',
        'resolved_at' => 'datetime',
    ];

    /**
     * The model's default attribute values.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'open',
    ];

    /**
     * Severity constants.
     */
    public const SEVERITY_CRITICAL = 'critical';
    public const SEVERITY_WARNING = 'warning';
    public const SEVERITY_NOTICE = 'notice';

    /**
     * Status constants.
     */
    public const STATUS_OPEN = 'open';
    public const STATUS_FIXED = 'fixed';
    public const STATUS_IGNORED = 'ignored';

    /**
     * Issue type constants.
     */
    public const TYPE_DUPLICATE_TITLE = 'duplicate_title';
    public const TYPE_DUPLICATE_DESCRIPTION = 'duplicate_description';
    public const TYPE_MISSING_TITLE = 'missing_title';
    public const TYPE_MISSING_DESCRIPTION = 'missing_description';
    public const TYPE_MISSING_H1 = 'missing_h1';
    public const TYPE_TITLE_TOO_LONG = 'title_too_long';
    public const TYPE_TITLE_TOO_SHORT = 'title_too_short';
    public const TYPE_DESCRIPTION_TOO_LONG = 'description_too_long';
    public const TYPE_DESCRIPTION_TOO_SHORT = 'description_too_short';
    public const TYPE_BROKEN_LINK = 'broken_link';
    public const TYPE_BROKEN_IMAGE = 'broken_image';
    public const TYPE_MISSING_ALT_TEXT = 'missing_alt_text';
    public const TYPE_MISSING_CANONICAL = 'missing_canonical';
    public const TYPE_THIN_CONTENT = 'thin_content';
    public const TYPE_KEYWORD_STUFFING = 'keyword_stuffing';
    public const TYPE_INVALID_SCHEMA = 'invalid_schema';
    public const TYPE_INVALID_ROBOTS = 'invalid_robots';

    /**
     * Get the parent scannable model.
     *
     * @return MorphTo
     */
    public function scannable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Get the scan run that discovered this issue.
     *
     * @return BelongsTo<SEOScanRun, self>
     */
    public function scanRun(): BelongsTo
    {
        return $this->belongsTo(SEOScanRun::class, 'scan_run_id');
    }

    /**
     * Mark the issue as fixed.
     *
     * Sets status to 'fixed' and records the resolution time.
     */
    public function markFixed(): void
    {
        $this->update([
            'status' => self::STATUS_FIXED,
            'resolved_at' => now(),
        ]);
    }

    /**
     * Mark the issue as ignored.
     *
     * Sets status to 'ignored' and records the resolution time.
     */
    public function markIgnored(): void
    {
        $this->update([
            'status' => self::STATUS_IGNORED,
            'resolved_at' => now(),
        ]);
    }

    /**
     * Reopen a previously resolved issue.
     */
    public function reopen(): void
    {
        $this->update([
            'status' => self::STATUS_OPEN,
            'resolved_at' => null,
        ]);
    }

    /**
     * Check if the issue is still open.
     *
     * @return bool
     */
    public function isOpen(): bool
    {
        return $this->status === self::STATUS_OPEN;
    }

    /**
     * Check if the issue is critical severity.
     *
     * @return bool
     */
    public function isCritical(): bool
    {
        return $this->severity === self::SEVERITY_CRITICAL;
    }

    /**
     * Scope for open issues only.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeOpen($query)
    {
        return $query->where('status', self::STATUS_OPEN);
    }

    /**
     * Scope to filter by severity.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param string $severity
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeBySeverity($query, string $severity)
    {
        return $query->where('severity', $severity);
    }

    /**
     * Scope to filter by issue type.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param string $type
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeByType($query, string $type)
    {
        return $query->where('issue_type', $type);
    }

    /**
     * Scope to filter by field.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param string $field
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeByField($query, string $field)
    {
        return $query->where('field', $field);
    }

    /**
     * Scope to get critical issues.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeCritical($query)
    {
        return $query->where('severity', self::SEVERITY_CRITICAL);
    }

    /**
     * Scope to get issues for a specific scannable model.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param \Illuminate\Database\Eloquent\Model $model
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeForModel($query, Model $model)
    {
        return $query->where('scannable_type', $model->getMorphClass())
            ->where('scannable_id', $model->getKey());
    }

    /**
     * Boot the model.
     */
    protected static function booted(): void
    {
        static::creating(function (self $issue) {
            if (! $issue->detected_at) {
                $issue->detected_at = now();
            }
        });
    }
}
