<?php

declare(strict_types=1);

namespace Rankbeam\Seo\Contracts;

use Rankbeam\Seo\Data\SEOData;

/**
 * Interface for models that support SEO features.
 *
 * This interface defines the contract for models that can have
 * SEO metadata attached to them. Use the HasSEO trait for the
 * default implementation.
 */
interface HasSEO
{
    /**
     * Get resolved SEO data for this model.
     *
     * Applies the full precedence chain:
     * global → model-type → route → computed → explicit
     *
     * @param  string|null  $locale  Optional locale for multilingual sites
     */
    public function seoData(?string $locale = null): SEOData;

    /**
     * Save SEO data for this model.
     *
     * @param  array<string, mixed>  $data  The SEO data to save
     * @param  string|null  $locale  Optional locale for multilingual sites
     */
    public function saveSEO(array $data, ?string $locale = null): void;

    /**
     * Get the fields that trigger re-analysis when changed.
     *
     * @return array<int, string>
     */
    public function getSEOContentFields(): array;

    /**
     * Get the computed SEO title.
     *
     * Used as fallback when no explicit title is set.
     */
    public function getSEOTitle(): ?string;

    /**
     * Get the computed SEO description.
     *
     * Used as fallback when no explicit description is set.
     */
    public function getSEODescription(): ?string;

    /**
     * Get the computed SEO image.
     *
     * Used as fallback for og:image when not explicitly set.
     */
    public function getSEOImage(): ?string;

    /**
     * Get the content for SEO analysis.
     *
     * Returns the main content body for analysis by the content analyzer.
     */
    public function getContentForSEO(): string;

    /**
     * Get the URL for this model.
     *
     * Used for canonical URL and sitemap generation.
     */
    public function getUrlForSEO(): string;
}
