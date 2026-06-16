<?php

declare(strict_types=1);

namespace Rankbeam\Seo\Data;

/**
 * A single ordered candidate image for social / Open Graph selection.
 *
 * Models expose an ordered list of these from `getSEOImages()` so the
 * computed-image "best" strategy (config `seo.computed.image_selection`) can
 * pick the candidate whose LOCAL pixel dimensions sit closest to the
 * configured ideal (default 1200×630), skipping any below the configured
 * minimum (default 200×200).
 *
 * Only a URL and a relative priority are carried here. Dimension measurement
 * happens in {@see \Rankbeam\Seo\Services\SEOComputedBuilder} against local
 * files only — Core never fetches a remote image (SSRF / latency / cache
 * concerns); remote dimension checks are the Filament preview's client-side
 * job. Priority breaks ties between equally well-sized candidates and orders
 * the list when no dimensions can be measured.
 *
 * @example
 * ```php
 * public function getSEOImages(): iterable
 * {
 *     return [
 *         SEOImageCandidate::make($this->hero_url)->priority(100),
 *         SEOImageCandidate::make($this->thumbnail_url)->priority(10),
 *     ];
 * }
 * ```
 */
final class SEOImageCandidate
{
    /**
     * @param  string  $url  The candidate image URL (relative or absolute)
     * @param  int  $priority  Higher wins ties; the default ordered list uses 0
     */
    public function __construct(
        public readonly string $url,
        public readonly int $priority = 0,
    ) {}

    /**
     * Create a candidate for the given URL.
     */
    public static function make(string $url, int $priority = 0): self
    {
        return new self($url, $priority);
    }

    /**
     * Return a copy of this candidate with the given priority.
     *
     * Fluent companion to {@see make()}: `SEOImageCandidate::make($url)->priority(100)`.
     */
    public function priority(int $priority): self
    {
        return new self($this->url, $priority);
    }
}
