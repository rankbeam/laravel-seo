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

        if ($issue = $this->checkBlankExplicitOverride($model)) {
            $issues[] = $issue;
        }

        $issues = array_merge($issues, $this->checkCanonicalConsistency($model));

        return array_merge($issues, $this->checkAnswerReadiness($model, $locale));
    }

    /**
     * Schema.org @types treated as "article" content for answer-readiness.
     *
     * @var array<int, string>
     */
    protected const AEO_ARTICLE_TYPES = [
        'article', 'blogposting', 'newsarticle', 'techarticle',
        'scholarlyarticle', 'report', 'liveblogposting',
    ];

    /**
     * Answer-readiness (AEO) checks: is the page's article content extractable
     * and attributable by AI answer engines?
     *
     * Both checks read the RESOLVED JSON-LD graph (the same one the page
     * renders), so they run in the free audit with no fetch. They fire ONLY
     * when the page declares article-type structured data and that node is
     * missing a signal — never a blanket "you have no schema" nag — so a page
     * without an article is never flagged. Advisory: notice-level, and held out
     * of the Pro 0-100 score.
     *
     * @return array<int, AuditIssue>
     */
    public function checkAnswerReadiness(Model $model, ?string $locale = null): array
    {
        try {
            $resolved = SEO::resolve($model, null, $locale);
        } catch (Throwable) {
            return [];
        }

        $articles = $this->articleSchemaNodes($resolved->schemaJsonld);

        if ($articles === []) {
            return [];
        }

        $missingAuthor = false;
        $missingDate = false;

        foreach ($articles as $node) {
            if (empty($node['author'])) {
                $missingAuthor = true;
            }

            if (empty($node['datePublished']) && empty($node['dateModified'])) {
                $missingDate = true;
            }
        }

        $issues = [];

        if ($missingAuthor) {
            $issues[] = MetadataIssues::make(
                'aeo_missing_author',
                'An article on this page has no author in its structured data; AI answer engines use the author for attribution and E-E-A-T.',
            );
        }

        if ($missingDate) {
            $issues[] = MetadataIssues::make(
                'aeo_article_missing_date',
                'An article on this page has no publish date in its structured data; AI answer engines use it to judge recency.',
            );
        }

        return $issues;
    }

    /**
     * The article-type nodes in a resolved JSON-LD value, handling the single-
     * node, list, and @graph shapes.
     *
     * @return array<int, array<string, mixed>>
     */
    protected function articleSchemaNodes(mixed $schema): array
    {
        if (! is_array($schema) || $schema === []) {
            return [];
        }

        if (isset($schema['@graph']) && is_array($schema['@graph'])) {
            $nodes = $schema['@graph'];
        } elseif (isset($schema['@type']) || isset($schema['@context'])) {
            $nodes = [$schema];
        } else {
            $nodes = $schema;
        }

        $articles = [];

        foreach ($nodes as $node) {
            if (is_array($node) && $this->isArticleNode($node)) {
                $articles[] = $node;
            }
        }

        return $articles;
    }

    /**
     * Whether a JSON-LD node's @type is one of the article types.
     *
     * @param  array<string, mixed>  $node
     */
    protected function isArticleNode(array $node): bool
    {
        $types = $node['@type'] ?? null;
        $types = is_array($types) ? $types : [$types];

        foreach ($types as $type) {
            if (is_string($type) && in_array(strtolower($type), self::AEO_ARTICLE_TYPES, true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * The stored seo_meta string content columns whose blank/whitespace value
     * would silently override a lower layer. This is the resolver's
     * blank_is_unset field set limited to the columns actually persisted on
     * seo_meta and rendered as content; `locale` and `schema_type` are
     * identity / UI columns, not rendered SEO content, so they are excluded.
     *
     * @var array<int, string>
     */
    protected const BLANK_OVERRIDE_COLUMNS = [
        'title', 'description', 'canonical', 'robots',
        'og_title', 'og_description', 'og_image', 'og_type',
        'twitter_title', 'twitter_description', 'twitter_image', 'twitter_card',
    ];

    /**
     * Surface a persisted blank/whitespace SEO string that silently overrides
     * the computed/default value.
     *
     * The resolver merges with "last non-null wins", so a stored '' / '   ' in
     * a seo_meta string column is an explicit value that beats every lower
     * layer — blanking a tag (or suppressing a computed fallback) with no
     * warning. The `seo.resolver.blank_is_unset` policy fixes this by dropping
     * such blanks to null before the merge; while that flag is OFF (the default
     * in 3.x) this check makes the otherwise-silent condition observable. With
     * the flag ON the blanks already fall through, so there is nothing to
     * report.
     *
     * Only stored string content columns are considered: arrays
     * (focus_keywords, schema_jsonld), the literal "0" (a real value), and
     * absent (null) columns are never flagged.
     */
    public function checkBlankExplicitOverride(Model $model): ?AuditIssue
    {
        if (config('seo.resolver.blank_is_unset', false)) {
            return null;
        }

        $seoMeta = $model->seoMeta;

        if (! $seoMeta) {
            return null;
        }

        $blankFields = [];

        foreach (self::BLANK_OVERRIDE_COLUMNS as $column) {
            $value = $seoMeta->{$column};

            // Only a non-null string that is empty after trimming: "0" trims to
            // "0" (a real value, kept), null is absent (correct fall-through,
            // not an override), and arrays never satisfy is_string().
            if (is_string($value) && trim($value) === '') {
                $blankFields[] = $column;
            }
        }

        if ($blankFields === []) {
            return null;
        }

        return MetadataIssues::make(
            'blank_explicit_override',
            count($blankFields).' stored SEO field(s) are blank and override the computed/default value '
                .'(set seo.resolver.blank_is_unset, or clear them to null): '.implode(', ', $blankFields).'.',
            ['fields' => $blankFields],
        );
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
