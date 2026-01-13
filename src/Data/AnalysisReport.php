<?php

declare(strict_types=1);

namespace Fibonoir\LaravelSEO\Data;

use DateTimeImmutable;
use DateTimeInterface;
use Illuminate\Contracts\Support\Arrayable;
use JsonSerializable;

/**
 * Complete analysis report containing all rule results.
 *
 * @implements Arrayable<string, mixed>
 */
final class AnalysisReport implements Arrayable, JsonSerializable
{
    /**
     * @param  int  $totalScore  The overall SEO score (0-100)
     * @param  array<string, RuleResult>  $results  Results keyed by rule ID
     * @param  DateTimeInterface  $analyzedAt  When the analysis was performed
     * @param  string  $locale  The locale used for analysis
     */
    public function __construct(
        public readonly int $totalScore,
        public readonly array $results,
        public readonly DateTimeInterface $analyzedAt,
        public readonly string $locale,
    ) {}

    /**
     * Create a new report from results.
     *
     * @param  array<string, RuleResult>  $results
     * @param  array<string, int>  $weights  Rule weights keyed by rule ID
     */
    public static function fromResults(
        array $results,
        array $weights,
        string $locale = 'en',
    ): self {
        $totalScore = self::calculateTotalScore($results, $weights);

        return new self(
            totalScore: $totalScore,
            results: $results,
            analyzedAt: new DateTimeImmutable,
            locale: $locale,
        );
    }

    /**
     * Calculate the weighted total score.
     *
     * Formula: Σ(rule_score × rule_weight) / Σ(rule_weights) × 100
     *
     * @param  array<string, RuleResult>  $results
     * @param  array<string, int>  $weights
     */
    private static function calculateTotalScore(array $results, array $weights): int
    {
        $weightedSum = 0;
        $totalWeight = 0;

        foreach ($results as $ruleId => $result) {
            // Skip skipped rules in score calculation
            if ($result->isSkipped()) {
                continue;
            }

            $weight = $weights[$ruleId] ?? 1;
            $weightedSum += ($result->score / 100) * $weight;
            $totalWeight += $weight;
        }

        if ($totalWeight === 0) {
            return 0;
        }

        return (int) round(($weightedSum / $totalWeight) * 100);
    }

    /**
     * Get all passing results.
     *
     * @return array<string, RuleResult>
     */
    public function getPassed(): array
    {
        return array_filter($this->results, fn(RuleResult $r) => $r->isPassed());
    }

    /**
     * Get all warning results.
     *
     * @return array<string, RuleResult>
     */
    public function getWarnings(): array
    {
        return array_filter($this->results, fn(RuleResult $r) => $r->isWarning());
    }

    /**
     * Get all failed results.
     *
     * @return array<string, RuleResult>
     */
    public function getFailed(): array
    {
        return array_filter($this->results, fn(RuleResult $r) => $r->isFailed());
    }

    /**
     * Get all skipped results.
     *
     * @return array<string, RuleResult>
     */
    public function getSkipped(): array
    {
        return array_filter($this->results, fn(RuleResult $r) => $r->isSkipped());
    }

    /**
     * Get count of passed rules.
     */
    public function getPassedCount(): int
    {
        return count($this->getPassed());
    }

    /**
     * Get count of warning rules.
     */
    public function getWarningCount(): int
    {
        return count($this->getWarnings());
    }

    /**
     * Get count of failed rules.
     */
    public function getFailedCount(): int
    {
        return count($this->getFailed());
    }

    /**
     * Get all recommendations (from warnings and failures).
     *
     * @return array<int, array{rule_id: string, recommendation: string, priority: string}>
     */
    public function getRecommendations(): array
    {
        $recommendations = [];

        // Failed items first (higher priority)
        foreach ($this->getFailed() as $ruleId => $result) {
            if ($result->recommendation) {
                $recommendations[] = [
                    'rule_id' => $ruleId,
                    'recommendation' => $result->recommendation,
                    'priority' => 'high',
                ];
            }
        }

        // Then warnings
        foreach ($this->getWarnings() as $ruleId => $result) {
            if ($result->recommendation) {
                $recommendations[] = [
                    'rule_id' => $ruleId,
                    'recommendation' => $result->recommendation,
                    'priority' => 'medium',
                ];
            }
        }

        return $recommendations;
    }

    /**
     * Get the score grade.
     *
     * @return string One of: 'excellent', 'good', 'needs_improvement', 'poor'
     */
    public function getGrade(): string
    {
        return match (true) {
            $this->totalScore >= 80 => 'excellent',
            $this->totalScore >= 60 => 'good',
            $this->totalScore >= 40 => 'needs_improvement',
            default => 'poor',
        };
    }

    /**
     * Get results grouped by category.
     *
     * @return array<string, array<string, RuleResult>>
     */
    public function getResultsByCategory(): array
    {
        $grouped = [];

        foreach ($this->results as $ruleId => $result) {
            // Extract category from rule ID (e.g., "keyword_density" -> "keyword")
            $parts = explode('_', $ruleId);
            $category = $parts[0] ?? 'other';

            $grouped[$category][$ruleId] = $result;
        }

        return $grouped;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'total_score' => $this->totalScore,
            'grade' => $this->getGrade(),
            'analyzed_at' => $this->analyzedAt->format('c'),
            'locale' => $this->locale,
            'summary' => [
                'passed' => $this->getPassedCount(),
                'warnings' => $this->getWarningCount(),
                'failed' => $this->getFailedCount(),
                'skipped' => count($this->getSkipped()),
            ],
            'results' => array_map(fn(RuleResult $r) => $r->toArray(), $this->results),
            'recommendations' => $this->getRecommendations(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
