<?php

declare(strict_types=1);

namespace Fibonoir\LaravelSEO\Contracts;

use Fibonoir\LaravelSEO\Data\AnalysisContext;
use Fibonoir\LaravelSEO\Data\RuleResult;

/**
 * Interface for SEO analyzer rules.
 *
 * Each rule analyzes a specific aspect of the content and returns
 * a result with a score, status, and recommendations.
 */
interface RuleInterface
{
    /**
     * Get the unique identifier for this rule.
     *
     * Used for configuration, exclusions, and result mapping.
     * Example: "keyword_density", "title_length"
     */
    public function getId(): string;

    /**
     * Get the human-readable name of this rule.
     *
     * Displayed in the UI and reports.
     * Example: "Keyword Density", "SEO Title Length"
     */
    public function getName(): string;

    /**
     * Get the weight of this rule in score calculations.
     *
     * Higher weight = more impact on total score.
     * Range: 1-20 (typically)
     */
    public function getWeight(): int;

    /**
     * Get the category this rule belongs to.
     *
     * Used for grouping in reports.
     * Examples: "keyword", "meta", "content", "media", "technical"
     */
    public function getCategory(): string;

    /**
     * Run the analysis and return a result.
     *
     * @param  AnalysisContext  $context  The analysis context with all content data
     * @return RuleResult The result of the analysis
     */
    public function analyze(AnalysisContext $context): RuleResult;
}
