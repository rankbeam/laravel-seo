<?php

declare(strict_types=1);

namespace Fibonoir\LaravelSEO\Data;

use Illuminate\Contracts\Support\Arrayable;
use JsonSerializable;

/**
 * Result of a single SEO rule analysis.
 *
 * @implements Arrayable<string, mixed>
 */
final class RuleResult implements Arrayable, JsonSerializable
{
    /**
     * @param  string  $ruleId  The unique ID of the rule
     * @param  string  $status  One of: 'pass', 'warning', 'fail', 'skip'
     * @param  int  $score  Score from 0-100 (only relevant for pass/warning)
     * @param  string  $message  Human-readable result message
     * @param  string|null  $recommendation  Action to improve (for warnings/failures)
     * @param  mixed  $actualValue  The actual value found
     * @param  mixed  $expectedValue  The expected/optimal value
     * @param  array<string, mixed>  $details  Additional context data
     */
    public function __construct(
        public readonly string $ruleId,
        public readonly string $status,
        public readonly int $score,
        public readonly string $message,
        public readonly ?string $recommendation = null,
        public readonly mixed $actualValue = null,
        public readonly mixed $expectedValue = null,
        public readonly array $details = [],
    ) {}

    /**
     * Create a passing result.
     */
    public static function pass(string $ruleId, string $message, int $score = 100): self
    {
        return new self(
            ruleId: $ruleId,
            status: 'pass',
            score: $score,
            message: $message,
        );
    }

    /**
     * Create a warning result.
     */
    public static function warning(
        string $ruleId,
        string $message,
        string $recommendation,
        int $score = 50,
        mixed $actualValue = null,
        mixed $expectedValue = null,
    ): self {
        return new self(
            ruleId: $ruleId,
            status: 'warning',
            score: $score,
            message: $message,
            recommendation: $recommendation,
            actualValue: $actualValue,
            expectedValue: $expectedValue,
        );
    }

    /**
     * Create a failing result.
     */
    public static function fail(
        string $ruleId,
        string $message,
        string $recommendation,
        mixed $actualValue = null,
        mixed $expectedValue = null,
    ): self {
        return new self(
            ruleId: $ruleId,
            status: 'fail',
            score: 0,
            message: $message,
            recommendation: $recommendation,
            actualValue: $actualValue,
            expectedValue: $expectedValue,
        );
    }

    /**
     * Create a skipped result.
     */
    public static function skip(string $ruleId, string $reason): self
    {
        return new self(
            ruleId: $ruleId,
            status: 'skip',
            score: 0,
            message: $reason,
        );
    }

    /**
     * Check if this result represents a pass.
     */
    public function isPassed(): bool
    {
        return $this->status === 'pass';
    }

    /**
     * Check if this result represents a warning.
     */
    public function isWarning(): bool
    {
        return $this->status === 'warning';
    }

    /**
     * Check if this result represents a failure.
     */
    public function isFailed(): bool
    {
        return $this->status === 'fail';
    }

    /**
     * Check if this result was skipped.
     */
    public function isSkipped(): bool
    {
        return $this->status === 'skip';
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return array_filter([
            'rule_id' => $this->ruleId,
            'status' => $this->status,
            'score' => $this->score,
            'message' => $this->message,
            'recommendation' => $this->recommendation,
            'actual_value' => $this->actualValue,
            'expected_value' => $this->expectedValue,
            'details' => $this->details ?: null,
        ], fn($v) => $v !== null);
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
