<?php

declare(strict_types=1);

namespace Fibonoir\LaravelSEO\Services\Scanner;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Fibonoir\LaravelSEO\Models\SEOMeta;
use Fibonoir\LaravelSEO\Traits\HasSEO;

/**
 * Analyzes a single page/model for SEO issues.
 *
 * The PageScanner performs various checks on a model to identify
 * SEO problems that may impact search visibility:
 * - Duplicate titles and descriptions
 * - Missing meta tags
 * - Robots directive consistency
 * - Canonical URL issues
 *
 * ## Usage
 * ```php
 * $scanner = app(PageScanner::class);
 * $issues = $scanner->scan($post);
 *
 * foreach ($issues as $issue) {
 *     echo $issue['message'];
 * }
 * ```
 *
 * ## Issue Format
 * Each issue is an array with:
 * - issue_type: Identifier (e.g., 'duplicate_title')
 * - severity: critical, warning, info
 * - field: Affected field (title, description, etc.)
 * - message: Human-readable description
 * - context: Additional data (e.g., duplicate URLs)
 */
class PageScanner
{
    /**
     * Scan a model for all SEO issues.
     *
     * @return array<int, array<string, mixed>>
     */
    public function scan(Model $model): array
    {
        if (! in_array(HasSEO::class, class_uses_recursive($model), true)) {
            return [];
        }

        $issues = [];

        // Check for duplicate title
        if ($issue = $this->checkDuplicateTitle($model)) {
            $issues[] = $issue;
        }

        // Check for duplicate description
        if ($issue = $this->checkDuplicateDescription($model)) {
            $issues[] = $issue;
        }

        // Check for missing meta
        $issues = array_merge($issues, $this->checkMissingMeta($model));

        // Check robots consistency
        if ($issue = $this->checkRobotsConsistency($model)) {
            $issues[] = $issue;
        }

        // Check canonical consistency
        if ($issue = $this->checkCanonicalConsistency($model)) {
            $issues[] = $issue;
        }

        return $issues;
    }

    /**
     * Check for duplicate title across the site.
     *
     * @return array<string, mixed>|null
     */
    public function checkDuplicateTitle(Model $model): ?array
    {
        $seoMeta = $model->seoMeta;

        if (! $seoMeta || empty($seoMeta->title)) {
            return null;
        }

        // Find other models with the same title
        $duplicates = SEOMeta::where('title', $seoMeta->title)
            ->where('id', '!=', $seoMeta->id)
            ->where('locale', $seoMeta->locale ?? config('app.locale'))
            ->limit(5)
            ->get();

        if ($duplicates->isEmpty()) {
            return null;
        }

        $duplicateUrls = $duplicates->map(function ($meta) {
            $model = $meta->seoable;

            return $model ? $model->getUrlForSEO() : null;
        })->filter()->values()->toArray();

        return [
            'issue_type' => 'duplicate_title',
            'severity' => 'warning',
            'field' => 'title',
            'message' => "Title \"{$seoMeta->title}\" is used on " . count($duplicates) . ' other page(s).',
            'context' => [
                'title' => $seoMeta->title,
                'duplicate_urls' => $duplicateUrls,
            ],
        ];
    }

    /**
     * Check for duplicate meta description across the site.
     *
     * @return array<string, mixed>|null
     */
    public function checkDuplicateDescription(Model $model): ?array
    {
        $seoMeta = $model->seoMeta;

        if (! $seoMeta || empty($seoMeta->description)) {
            return null;
        }

        // Find other models with the same description
        $duplicates = SEOMeta::where('description', $seoMeta->description)
            ->where('id', '!=', $seoMeta->id)
            ->where('locale', $seoMeta->locale ?? config('app.locale'))
            ->limit(5)
            ->get();

        if ($duplicates->isEmpty()) {
            return null;
        }

        $duplicateUrls = $duplicates->map(function ($meta) {
            $model = $meta->seoable;

            return $model ? $model->getUrlForSEO() : null;
        })->filter()->values()->toArray();

        return [
            'issue_type' => 'duplicate_description',
            'severity' => 'warning',
            'field' => 'description',
            'message' => 'Meta description is duplicated on ' . count($duplicates) . ' other page(s).',
            'context' => [
                'description' => mb_substr($seoMeta->description, 0, 100) . '...',
                'duplicate_urls' => $duplicateUrls,
            ],
        ];
    }

    /**
     * Check for missing essential meta tags.
     *
     * @return array<int, array<string, mixed>>
     */
    public function checkMissingMeta(Model $model): array
    {
        $issues = [];
        $seoMeta = $model->seoMeta;

        // Check title
        if (! $seoMeta || empty($seoMeta->title)) {
            // Try to get from model
            $modelTitle = method_exists($model, 'getSEOTitle') ? $model->getSEOTitle() : null;

            if (empty($modelTitle)) {
                $issues[] = [
                    'issue_type' => 'missing_title',
                    'severity' => 'critical',
                    'field' => 'title',
                    'message' => 'Page is missing a title tag.',
                ];
            }
        }

        // Check description
        if (! $seoMeta || empty($seoMeta->description)) {
            $modelDesc = method_exists($model, 'getSEODescription') ? $model->getSEODescription() : null;

            if (empty($modelDesc)) {
                $issues[] = [
                    'issue_type' => 'missing_description',
                    'severity' => 'warning',
                    'field' => 'description',
                    'message' => 'Page is missing a meta description.',
                ];
            }
        }

        // Check OG image
        if (! $seoMeta || empty($seoMeta->og_image)) {
            $modelImage = method_exists($model, 'getSEOImage') ? $model->getSEOImage() : null;

            if (empty($modelImage)) {
                $issues[] = [
                    'issue_type' => 'missing_og_image',
                    'severity' => 'info',
                    'field' => 'og_image',
                    'message' => 'Page is missing an Open Graph image.',
                ];
            }
        }

        // Check focus keywords
        if (! $seoMeta || empty($seoMeta->focus_keywords)) {
            $issues[] = [
                'issue_type' => 'missing_focus_keyword',
                'severity' => 'info',
                'field' => 'focus_keywords',
                'message' => 'No focus keyword set for this page.',
            ];
        }

        return $issues;
    }

    /**
     * Check for robots directive consistency.
     *
     * @return array<string, mixed>|null
     */
    public function checkRobotsConsistency(Model $model): ?array
    {
        $seoMeta = $model->seoMeta;

        if (! $seoMeta) {
            return null;
        }

        $robots = strtolower($seoMeta->robots ?? '');

        // Check for conflicting directives
        if (str_contains($robots, 'noindex') && str_contains($robots, 'index')) {
            return [
                'issue_type' => 'robots_conflict',
                'severity' => 'critical',
                'field' => 'robots',
                'message' => 'Robots meta has conflicting index/noindex directives.',
                'context' => ['robots' => $seoMeta->robots],
            ];
        }

        if (str_contains($robots, 'nofollow') && str_contains($robots, 'follow')) {
            return [
                'issue_type' => 'robots_conflict',
                'severity' => 'warning',
                'field' => 'robots',
                'message' => 'Robots meta has conflicting follow/nofollow directives.',
                'context' => ['robots' => $seoMeta->robots],
            ];
        }

        // Check for noindex on important pages (has canonical or high score)
        if (str_contains($robots, 'noindex')) {
            if (($seoMeta->seo_score ?? 0) >= 70 || ! empty($seoMeta->canonical)) {
                return [
                    'issue_type' => 'noindex_warning',
                    'severity' => 'warning',
                    'field' => 'robots',
                    'message' => 'Page has noindex but appears to be important content.',
                    'context' => [
                        'robots' => $seoMeta->robots,
                        'score' => $seoMeta->seo_score,
                    ],
                ];
            }
        }

        return null;
    }

    /**
     * Check for canonical URL consistency.
     *
     * @return array<string, mixed>|null
     */
    public function checkCanonicalConsistency(Model $model): ?array
    {
        $seoMeta = $model->seoMeta;

        if (! $seoMeta || empty($seoMeta->canonical)) {
            return null;
        }

        $canonical = $seoMeta->canonical;
        $pageUrl = $model->getUrlForSEO();

        // Check if canonical is valid URL
        if (! filter_var($canonical, FILTER_VALIDATE_URL)) {
            return [
                'issue_type' => 'invalid_canonical',
                'severity' => 'critical',
                'field' => 'canonical',
                'message' => 'Canonical URL is not a valid URL format.',
                'context' => ['canonical' => $canonical],
            ];
        }

        // Check if canonical points to a different domain
        $canonicalHost = parse_url($canonical, PHP_URL_HOST);
        $pageHost = parse_url($pageUrl, PHP_URL_HOST);

        if ($canonicalHost && $pageHost && $canonicalHost !== $pageHost) {
            return [
                'issue_type' => 'cross_domain_canonical',
                'severity' => 'warning',
                'field' => 'canonical',
                'message' => 'Canonical URL points to a different domain.',
                'context' => [
                    'canonical' => $canonical,
                    'page_url' => $pageUrl,
                ],
            ];
        }

        // Check if multiple pages point to same canonical (canonicalization loop potential)
        $sameCanonical = SEOMeta::where('canonical', $canonical)
            ->where('id', '!=', $seoMeta->id)
            ->count();

        if ($sameCanonical > 0) {
            return [
                'issue_type' => 'shared_canonical',
                'severity' => 'info',
                'field' => 'canonical',
                'message' => ($sameCanonical + 1) . ' pages share the same canonical URL.',
                'context' => ['canonical' => $canonical],
            ];
        }

        return null;
    }
}
