<?php

declare(strict_types=1);

namespace Fibonoir\LaravelSEO\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Cache;

/**
 * URL redirect rules.
 *
 * @property int $id
 * @property string $source_path
 * @property string $target_url
 * @property int $status_code
 * @property bool $is_regex
 * @property bool $is_active
 * @property bool $preserve_query
 * @property int $hit_count
 * @property \Carbon\Carbon|null $last_hit_at
 * @property string|null $note
 * @property int|null $created_by
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 */
class SEORedirect extends Model
{
    protected $table = 'seo_redirects';

    protected $fillable = [
        'source_path',
        'target_url',
        'status_code',
        'is_regex',
        'is_active',
        'preserve_query',
        'hit_count',
        'last_hit_at',
        'note',
        'created_by',
    ];

    protected $casts = [
        'is_regex' => 'boolean',
        'is_active' => 'boolean',
        'preserve_query' => 'boolean',
        'status_code' => 'integer',
        'hit_count' => 'integer',
        'last_hit_at' => 'datetime',
    ];

    protected $attributes = [
        'status_code' => 301,
        'is_regex' => false,
        'is_active' => true,
        'preserve_query' => true,
        'hit_count' => 0,
    ];

    /**
     * Get the user who created this redirect.
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(config('auth.providers.users.model', 'App\\Models\\User'), 'created_by');
    }

    /**
     * Get related 404 logs.
     */
    public function logs404(): HasMany
    {
        return $this->hasMany(SEO404Log::class, 'redirect_id');
    }

    /**
     * Scope to get only active redirects.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Increment the hit counter.
     */
    public function incrementHit(): void
    {
        $this->increment('hit_count');
        $this->update(['last_hit_at' => now()]);
    }

    /**
     * Clear redirect cache when saving.
     */
    protected static function booted(): void
    {
        $clearCache = function () {
            Cache::store(config('seo.cache.store'))
                ->forget(config('seo.cache.prefix', 'seo_').'redirects');
        };

        static::saved($clearCache);
        static::deleted($clearCache);
    }
}
