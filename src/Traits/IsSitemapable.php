<?php

declare(strict_types=1);

namespace Fibonoir\LaravelSEO\Traits;

use Spatie\Sitemap\Tags\Url;

/**
 * Default implementation for sitemap inclusion.
 *
 * Requires spatie/laravel-sitemap package.
 */
trait IsSitemapable
{
    /**
     * Convert the model to a sitemap URL tag.
     *
     * @return Url|string|array<string, mixed>
     */
    public function toSitemapTag(): Url|string|array
    {
        $url = Url::create($this->getUrlForSEO());

        // Set last modification date
        if ($this->updated_at) {
            $url->setLastModificationDate($this->updated_at);
        }

        // Set change frequency based on model type
        $changeFreq = $this->getSitemapChangeFrequency();
        if ($changeFreq) {
            $url->setChangeFrequency($changeFreq);
        }

        // Set priority
        $priority = $this->getSitemapPriority();
        if ($priority !== null) {
            $url->setPriority($priority);
        }

        return $url;
    }

    /**
     * Determine if this model should be included in the sitemap.
     */
    public function shouldIncludeInSitemap(): bool
    {
        // Check if noindex
        $robots = $this->seoMeta?->robots ?? 'index,follow';
        if (str_contains(strtolower($robots), 'noindex')) {
            return false;
        }

        // Check if published (common patterns)
        if (isset($this->is_published) && ! $this->is_published) {
            return false;
        }

        if (isset($this->status) && $this->status !== 'published') {
            return false;
        }

        if (isset($this->published_at) && $this->published_at > now()) {
            return false;
        }

        return true;
    }

    /**
     * Get the change frequency for sitemap.
     *
     * Override in your model for custom logic.
     */
    public function getSitemapChangeFrequency(): ?string
    {
        // Determine based on model type
        $className = class_basename($this);

        return match (true) {
            str_contains(strtolower($className), 'post') => Url::CHANGE_FREQUENCY_WEEKLY,
            str_contains(strtolower($className), 'page') => Url::CHANGE_FREQUENCY_MONTHLY,
            str_contains(strtolower($className), 'product') => Url::CHANGE_FREQUENCY_DAILY,
            default => Url::CHANGE_FREQUENCY_WEEKLY,
        };
    }

    /**
     * Get the priority for sitemap (0.0 to 1.0).
     *
     * Override in your model for custom logic.
     */
    public function getSitemapPriority(): ?float
    {
        return 0.7;
    }
}
