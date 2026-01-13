<?php

namespace Fibonoir\LaravelSEO\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Fibonoir\LaravelSEO\Models\SEORedirect;

class IncrementRedirectHitJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public readonly int $redirectId
    ) {}

    public function handle(): void
    {
        SEORedirect::where('id', $this->redirectId)
            ->increment('hit_count');

        SEORedirect::where('id', $this->redirectId)
            ->update(['last_hit_at' => now()]);
    }
}
