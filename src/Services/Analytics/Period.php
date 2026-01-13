<?php

declare(strict_types=1);

namespace Fibonoir\LaravelSEO\Services\Analytics;

use Carbon\Carbon;
use Carbon\CarbonImmutable;

/**
 * Represents a date period for analytics queries.
 *
 * Provides fluent factory methods for common date ranges.
 *
 * ## Usage
 * ```php
 * // Last 30 days
 * $period = Period::days(30);
 *
 * // This month
 * $period = Period::thisMonth();
 *
 * // Custom range
 * $period = Period::create(
 *     Carbon::parse('2024-01-01'),
 *     Carbon::parse('2024-01-31')
 * );
 *
 * // Use with analytics
 * $views = $analytics->getPageViews($period);
 * ```
 */
readonly class Period
{
    public function __construct(
        public CarbonImmutable $startDate,
        public CarbonImmutable $endDate,
    ) {}

    /**
     * Create a period for the last N days.
     */
    public static function days(int $days): self
    {
        return new self(
            CarbonImmutable::now()->subDays($days)->startOfDay(),
            CarbonImmutable::now()->endOfDay(),
        );
    }

    /**
     * Create a period for the last N weeks.
     */
    public static function weeks(int $weeks): self
    {
        return new self(
            CarbonImmutable::now()->subWeeks($weeks)->startOfDay(),
            CarbonImmutable::now()->endOfDay(),
        );
    }

    /**
     * Create a period for the last N months.
     */
    public static function months(int $months): self
    {
        return new self(
            CarbonImmutable::now()->subMonths($months)->startOfDay(),
            CarbonImmutable::now()->endOfDay(),
        );
    }

    /**
     * Create a period for the last N years.
     */
    public static function years(int $years): self
    {
        return new self(
            CarbonImmutable::now()->subYears($years)->startOfDay(),
            CarbonImmutable::now()->endOfDay(),
        );
    }

    /**
     * Create a period from a start date until now.
     */
    public static function since(Carbon|CarbonImmutable $start): self
    {
        return new self(
            CarbonImmutable::parse($start)->startOfDay(),
            CarbonImmutable::now()->endOfDay(),
        );
    }

    /**
     * Create a period with specific start and end dates.
     */
    public static function create(Carbon|CarbonImmutable $start, Carbon|CarbonImmutable $end): self
    {
        return new self(
            CarbonImmutable::parse($start)->startOfDay(),
            CarbonImmutable::parse($end)->endOfDay(),
        );
    }

    /**
     * Create a period for today.
     */
    public static function today(): self
    {
        $today = CarbonImmutable::today();

        return new self(
            $today->startOfDay(),
            $today->endOfDay(),
        );
    }

    /**
     * Create a period for yesterday.
     */
    public static function yesterday(): self
    {
        $yesterday = CarbonImmutable::yesterday();

        return new self(
            $yesterday->startOfDay(),
            $yesterday->endOfDay(),
        );
    }

    /**
     * Create a period for this week (Monday to today).
     */
    public static function thisWeek(): self
    {
        return new self(
            CarbonImmutable::now()->startOfWeek(),
            CarbonImmutable::now()->endOfDay(),
        );
    }

    /**
     * Create a period for last week (Monday to Sunday).
     */
    public static function lastWeek(): self
    {
        return new self(
            CarbonImmutable::now()->subWeek()->startOfWeek(),
            CarbonImmutable::now()->subWeek()->endOfWeek(),
        );
    }

    /**
     * Create a period for this month (1st to today).
     */
    public static function thisMonth(): self
    {
        return new self(
            CarbonImmutable::now()->startOfMonth(),
            CarbonImmutable::now()->endOfDay(),
        );
    }

    /**
     * Create a period for last month.
     */
    public static function lastMonth(): self
    {
        return new self(
            CarbonImmutable::now()->subMonth()->startOfMonth(),
            CarbonImmutable::now()->subMonth()->endOfMonth(),
        );
    }

    /**
     * Create a period for this year (Jan 1 to today).
     */
    public static function thisYear(): self
    {
        return new self(
            CarbonImmutable::now()->startOfYear(),
            CarbonImmutable::now()->endOfDay(),
        );
    }

    /**
     * Create a period for last year.
     */
    public static function lastYear(): self
    {
        return new self(
            CarbonImmutable::now()->subYear()->startOfYear(),
            CarbonImmutable::now()->subYear()->endOfYear(),
        );
    }

    /**
     * Get the start date.
     */
    public function getStartDate(): CarbonImmutable
    {
        return $this->startDate;
    }

    /**
     * Get the end date.
     */
    public function getEndDate(): CarbonImmutable
    {
        return $this->endDate;
    }

    /**
     * Get the number of days in the period.
     */
    public function getDays(): int
    {
        return (int) $this->startDate->diffInDays($this->endDate) + 1;
    }

    /**
     * Get start date formatted for GA4 API (YYYY-MM-DD).
     */
    public function getStartForApi(): string
    {
        return $this->startDate->format('Y-m-d');
    }

    /**
     * Get end date formatted for GA4 API (YYYY-MM-DD).
     */
    public function getEndForApi(): string
    {
        return $this->endDate->format('Y-m-d');
    }

    /**
     * Get a cache key suffix for this period.
     */
    public function getCacheKey(): string
    {
        return $this->startDate->format('Ymd') . '_' . $this->endDate->format('Ymd');
    }
}
