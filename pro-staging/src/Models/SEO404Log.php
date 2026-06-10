<?php

declare(strict_types=1);

namespace Fibonoir\LaravelSEO\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * 404 error log entries.
 *
 * @property int $id
 * @property string $path
 * @property string|null $referrer
 * @property string|null $user_agent
 * @property string|null $ip
 * @property int $hit_count
 * @property \Carbon\Carbon $first_seen_at
 * @property \Carbon\Carbon $last_seen_at
 * @property string $status
 * @property int|null $redirect_id
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 */
class SEO404Log extends Model
{
    protected $table = 'seo_404_logs';

    protected $fillable = [
        'path',
        'referrer',
        'user_agent',
        'ip',
        'hit_count',
        'first_seen_at',
        'last_seen_at',
        'status',
        'redirect_id',
    ];

    protected $casts = [
        'hit_count' => 'integer',
        'first_seen_at' => 'datetime',
        'last_seen_at' => 'datetime',
    ];

    protected $attributes = [
        'hit_count' => 1,
        'status' => 'new',
    ];

    /**
     * Get the associated redirect (if resolved).
     */
    public function redirect(): BelongsTo
    {
        return $this->belongsTo(SEORedirect::class, 'redirect_id');
    }

    /**
     * Scope for unresolved (new) entries.
     */
    public function scopeUnresolved($query)
    {
        return $query->where('status', 'new');
    }

    /**
     * Scope to order by hit count.
     */
    public function scopeByHitCount($query, string $direction = 'desc')
    {
        return $query->orderBy('hit_count', $direction);
    }

    /**
     * Increment the hit counter.
     */
    public function incrementHit(): void
    {
        $this->increment('hit_count');
        $this->update(['last_seen_at' => now()]);
    }

    /**
     * Mark as redirected with the given redirect.
     */
    public function markRedirected(SEORedirect $redirect): void
    {
        $this->update([
            'status' => 'redirected',
            'redirect_id' => $redirect->id,
        ]);
    }

    /**
     * Mark as ignored.
     */
    public function markIgnored(): void
    {
        $this->update(['status' => 'ignored']);
    }
}
