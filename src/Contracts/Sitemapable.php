<?php

declare(strict_types=1);

namespace Rankbeam\Seo\Contracts;

use Spatie\Sitemap\Tags\Url;

/**
 * Interface for models that can be included in sitemaps.
 *
 * Models implementing this interface can be automatically
 * added to XML sitemaps with proper metadata.
 */
interface Sitemapable
{
    /**
     * Convert the model to a sitemap URL tag.
     *
     * @return Url|string|array<string, mixed>
     */
    public function toSitemapTag(): Url|string|array;

    /**
     * Determine if this model should be included in the sitemap.
     *
     * Return false for:
     * - Draft/unpublished content
     * - Pages with noindex
     * - Private/password-protected pages
     */
    public function shouldIncludeInSitemap(): bool;
}
