<?php

declare(strict_types=1);

namespace Rankbeam\Seo\Auditing;

use Illuminate\Database\Eloquent\Model;
use Rankbeam\Seo\Facades\SEO;
use Rankbeam\Seo\Models\SEOMeta;
use Rankbeam\Seo\Services\SEOWarningEvaluator;
use Rankbeam\Seo\Traits\HasSEO;
use Throwable;

/**
 * Runs the metadata-class SEO checks for a single model — the free in-process
 * auditor behind `seo:audit`.
 *
 * Every check here is resolvable from the model + the core SEOResolver with NO
 * page fetch and NO rendered HTML, so the command needs no queue, no license,
 * and no network. This is the free counterpart to the Pro EXEC_METADATA scanner
 * ({@see \Rankbeam\Seo\Pro\Scanning\PageScanner}); the two implement the same
 * codes with the same semantics (see {@see MetadataIssues}).
 *
 * What it deliberately does NOT do: the rendered-HTML checks (H1, image alt,
 * thin content, mixed content) and the live-canonical network checks. Those
 * need the page's served HTML or an outbound fetch and belong to the Pro scan.
 *
 * Checks:
 *  - Duplicate titles / descriptions across the site (cross-model query)
 *  - Missing meta (title, description, og:image, focus keyword), fallback-aware
 *  - Title / description length, reusing the core 60/160 thresholds and the
 *    audit floors — measured against the RESOLVED value, so the audit and the
 *    editor's counters never disagree
 *  - Robots directive conflicts and suspicious noindex
 *  - Canonical validity, cross-domain, shared, and insecure (http on https)
 */
class MetadataAuditor
{
    /**
     * Audit one model for all metadata-class issues.
     *
     * @param  string|null  $locale  Locale to resolve in for the length check (defaults to the app locale)
     * @return array<int, AuditIssue>
     */
    public function audit(Model $model, ?string $locale = null): array
    {
        if (! in_array(HasSEO::class, class_uses_recursive($model), true)) {
            return [];
        }

        $issues = [];

        if ($issue = $this->checkDuplicateTitle($model)) {
            $issues[] = $issue;
        }

        if ($issue = $this->checkDuplicateDescription($model)) {
            $issues[] = $issue;
        }

        $issues = array_merge($issues, $this->checkMissingMeta($model));
        $issues = array_merge($issues, $this->checkLength($model, $locale));

        if ($issue = $this->checkRobotsConsistency($model)) {
            $issues[] = $issue;
        }

        return array_merge($issues, $this->checkCanonicalConsistency($model));
    }

    /**
     * Check for a duplicate title across the site (same locale).
     */
    public function checkDuplicateTitle(Model $model): ?AuditIssue
    {
        $seoMeta = $model->seoMeta;

        if (! $seoMeta || empty($seoMeta->title)) {
            return null;
        }

        $duplicates = SEOMeta::where('title', $seoMeta->title)
            ->where('id', '!=', $seoMeta->id)
            ->where('locale', $seoMeta->locale ?? config('app.locale'))
            ->limit(5)
            ->get();

        if ($duplicates->isEmpty()) {
            return null;
        }

        return MetadataIssues::make(
            'duplicate_title',
            "Title \"{$seoMeta->title}\" is used on ".count($duplicates).' other page(s).',
            [
                'title' => $seoMeta->title,
                'duplicate_urls' => $this->duplicateUrls($duplicates),
            ],
        );
    }

    /**
     * Check for a duplicate meta description across the site (same locale).
     */
    public function checkDuplicateDescription(Model $model): ?AuditIssue
    {
        $seoMeta = $model->seoMeta;

        if (! $seoMeta || empty($seoMeta->description)) {
            return null;
        }

        $duplicates = SEOMeta::where('description', $seoMeta->description)
            ->where('id', '!=', $seoMeta->id)
            ->where('locale', $seoMeta->locale ?? config('app.locale'))
            ->limit(5)
            ->get();

        if ($duplicates->isEmpty()) {
            return null;
        }

        return MetadataIssues::make(
            'duplicate_description',
            'Meta description is duplicated on '.count($duplicates).' other page(s).',
            [
                'description' => mb_substr($seoMeta->description, 0, 100).'...',
                'duplicate_urls' => $this->duplicateUrls($duplicates),
            ],
        );
    }

    /**
     * Check for missing essential meta, considering computed fallbacks: a
     * missing explicit value is only an issue when the model cannot compute
     * one either.
     *
     * @return array<int, AuditIssue>
     */
    public function checkMissingMeta(Model $model): array
    {
        $issues = [];
        $seoMeta = $model->seoMeta;

        if (! $seoMeta || empty($seoMeta->title)) {
            $modelTitle = method_exists($model, 'getSEOTitle') ? $model->getSEOTitle() : null;

            if (empty($modelTitle)) {
                $issues[] = MetadataIssues::make('missing_title', 'Page is missing a title tag.');
            }
        }

        if (! $seoMeta || empty($seoMeta->description)) {
            $modelDesc = method_exists($model, 'getSEODescription') ? $model->getSEODescription() : null;

            if (empty($modelDesc)) {
                $issues[] = MetadataIssues::make('missing_description', 'Page is missing a meta description.');
            }
        }

        if (! $seoMeta || empty($seoMeta->og_image)) {
            $modelImage = method_exists($model, 'getSEOImage') ? $model->getSEOImage() : null;

            if (empty($modelImage)) {
                $issues[] = MetadataIssues::make('missing_og_image', 'Page is missing an Open Graph image.');
            }
        }

        // The "missing focus keyword" notice is gated behind the focus-keyword
        // workflow flag (default OFF) so an app that never adopts focus keywords
        // is not nagged. The Pro PageScanner reads the SAME core flag, so the
        // audit, the Pro scan, and the Pro editor nag always agree.
        if (config('seo.keywords.enabled', false) && (! $seoMeta || empty($seoMeta->focus_keywords))) {
            $issues[] = MetadataIssues::make('missing_focus_keyword', 'No focus keyword set for this page.');
        }

        return $issues;
    }

    /**
     * Title / description length against the resolved (effective) values.
     *
     * The upper bounds reuse the core SEOWarningEvaluator constants the editor
     * already uses, so the audit never contradicts the editor's warning; the
     * lower bounds are the audit floors. Resolution failures never break the
     * audit — length checks are simply skipped.
     *
     * @return array<int, AuditIssue>
     */
    public function checkLength(Model $model, ?string $locale = null): array
    {
        try {
            $resolved = SEO::resolve($model, null, $locale);
        } catch (Throwable) {
            return [];
        }

        $issues = [];

        $title = trim((string) ($resolved->title ?? ''));
        if ($title !== '') {
            $length = mb_strlen($title);

            if ($length > SEOWarningEvaluator::TITLE_MAX_LENGTH) {
                $issues[] = MetadataIssues::make(
                    'title_too_long',
                    "Title is {$length} characters (recommended max ".SEOWarningEvaluator::TITLE_MAX_LENGTH.'); it may be truncated on Google.',
                    ['length' => $length, 'max' => SEOWarningEvaluator::TITLE_MAX_LENGTH],
                );
            } elseif ($length < MetadataIssues::TITLE_MIN_LENGTH) {
                $issues[] = MetadataIssues::make(
                    'title_too_short',
                    "Title is only {$length} characters (recommended min ".MetadataIssues::TITLE_MIN_LENGTH.').',
                    ['length' => $length, 'min' => MetadataIssues::TITLE_MIN_LENGTH],
                );
            }
        }

        $description = trim((string) ($resolved->description ?? ''));
        if ($description !== '') {
            $length = mb_strlen($description);

            if ($length > SEOWarningEvaluator::DESCRIPTION_MAX_LENGTH) {
                $issues[] = MetadataIssues::make(
                    'description_too_long',
                    "Description is {$length} characters (recommended max ".SEOWarningEvaluator::DESCRIPTION_MAX_LENGTH.'); it may be truncated.',
                    ['length' => $length, 'max' => SEOWarningEvaluator::DESCRIPTION_MAX_LENGTH],
                );
            } elseif ($length < MetadataIssues::DESCRIPTION_MIN_LENGTH) {
                $issues[] = MetadataIssues::make(
                    'description_too_short',
                    "Description is only {$length} characters (recommended min ".MetadataIssues::DESCRIPTION_MIN_LENGTH.').',
                    ['length' => $length, 'min' => MetadataIssues::DESCRIPTION_MIN_LENGTH],
                );
            }
        }

        return $issues;
    }

    /**
     * Check for robots directive conflicts and a suspicious noindex on an
     * apparently important (self-canonical) page.
     */
    public function checkRobotsConsistency(Model $model): ?AuditIssue
    {
        $seoMeta = $model->seoMeta;

        if (! $seoMeta) {
            return null;
        }

        $robots = strtolower($seoMeta->robots ?? '');

        // "index" is a substring of "noindex", so check directives as a list.
        $directives = array_map('trim', explode(',', $robots));

        if (in_array('noindex', $directives, true) && in_array('index', $directives, true)) {
            return MetadataIssues::make(
                'robots_conflict_indexing',
                'Robots meta has conflicting index/noindex directives.',
                ['robots' => $seoMeta->robots],
            );
        }

        if (in_array('nofollow', $directives, true) && in_array('follow', $directives, true)) {
            return MetadataIssues::make(
                'robots_conflict_following',
                'Robots meta has conflicting follow/nofollow directives.',
                ['robots' => $seoMeta->robots],
            );
        }

        if (in_array('noindex', $directives, true) && ! empty($seoMeta->canonical)) {
            return MetadataIssues::make(
                'noindex_warning',
                'Page has noindex but appears to be important content.',
                ['robots' => $seoMeta->robots],
            );
        }

        return null;
    }

    /**
     * Check canonical URL validity, consistency, and scheme.
     *
     * @return array<int, AuditIssue>
     */
    public function checkCanonicalConsistency(Model $model): array
    {
        $seoMeta = $model->seoMeta;

        if (! $seoMeta || empty($seoMeta->canonical)) {
            return [];
        }

        $canonical = $seoMeta->canonical;

        if (! filter_var($canonical, FILTER_VALIDATE_URL)) {
            return [MetadataIssues::make(
                'invalid_canonical',
                'Canonical URL is not a valid URL format.',
                ['canonical' => $canonical],
            )];
        }

        $issues = [];

        $pageUrl = method_exists($model, 'getUrlForSEO') ? $model->getUrlForSEO() : null;
        $canonicalHost = parse_url($canonical, PHP_URL_HOST);
        $pageHost = $pageUrl ? parse_url($pageUrl, PHP_URL_HOST) : null;

        if ($canonicalHost && $pageHost && $canonicalHost !== $pageHost) {
            $issues[] = MetadataIssues::make(
                'cross_domain_canonical',
                'Canonical URL points to a different domain.',
                ['canonical' => $canonical, 'page_url' => $pageUrl],
            );
        }

        // Scheme is orthogonal to host: a manually entered http:// canonical
        // on an https site is mixed content and Google treats it as the http
        // variant, whether or not the host matches.
        if ($this->isInsecureCanonical($canonical)) {
            $issues[] = MetadataIssues::make(
                'insecure_canonical',
                'Canonical URL uses http:// on an https site.',
                ['canonical' => $canonical],
            );
        }

        $sameCanonical = SEOMeta::where('canonical', $canonical)
            ->where('id', '!=', $seoMeta->id)
            ->count();

        if ($sameCanonical > 0) {
            $issues[] = MetadataIssues::make(
                'shared_canonical',
                ($sameCanonical + 1).' pages share the same canonical URL.',
                ['canonical' => $canonical],
            );
        }

        return $issues;
    }

    /**
     * An explicit http:// canonical while the site itself is https.
     */
    protected function isInsecureCanonical(string $canonical): bool
    {
        if (strtolower((string) parse_url($canonical, PHP_URL_SCHEME)) !== 'http') {
            return false;
        }

        return strtolower((string) parse_url((string) config('app.url'), PHP_URL_SCHEME)) === 'https';
    }

    /**
     * @param  \Illuminate\Support\Collection<int, SEOMeta>  $duplicates
     * @return array<int, string>
     */
    protected function duplicateUrls($duplicates): array
    {
        return $duplicates->map(function (SEOMeta $meta): ?string {
            $other = $meta->seoable;

            return $other && method_exists($other, 'getUrlForSEO') ? $other->getUrlForSEO() : null;
        })->filter()->values()->toArray();
    }
}
