<?php

declare(strict_types=1);

namespace Fibonoir\LaravelSEO\Contracts;

/**
 * Interface for models that can be analyzed by the content analyzer.
 *
 * Models implementing this interface provide structured content
 * data for SEO analysis.
 */
interface Analyzable
{
    /**
     * Get the HTML content for analysis.
     *
     * This should return the main content body as HTML.
     */
    public function getAnalyzableContent(): string;

    /**
     * Get the SEO title for analysis.
     */
    public function getAnalyzableTitle(): string;

    /**
     * Get the meta description for analysis.
     */
    public function getAnalyzableDescription(): ?string;

    /**
     * Get the URL/slug for analysis.
     */
    public function getAnalyzableUrl(): ?string;

    /**
     * Get the focus keywords for analysis.
     *
     * @return array<int, array{keyword: string, is_primary: bool, synonyms?: array<int, string>}>
     */
    public function getAnalyzableFocusKeywords(): array;

    /**
     * Get the locale for language-specific analysis.
     */
    public function getAnalyzableLocale(): string;
}
