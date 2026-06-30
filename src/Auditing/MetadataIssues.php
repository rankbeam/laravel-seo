<?php

declare(strict_types=1);

namespace Rankbeam\Seo\Auditing;

use InvalidArgumentException;

/**
 * The free core mirror of the Pro scan's metadata-class issue codes.
 *
 * The authoritative, full catalogue is the Pro
 * `Rankbeam\Seo\Pro\Scanning\IssueRegistry` — it also owns the rendered-HTML
 * and network execution classes and the score weighting. The free core cannot
 * depend on Pro, so this is a deliberately tiny mirror of ONLY the
 * `EXEC_METADATA` rows: the checks the free `seo:audit` can run with no page
 * fetch.
 *
 * The code STRINGS and their (severity, field) are kept identical to the Pro
 * registry so the free audit, the Pro scan, the Filament editor, and exports
 * all agree on what each code means. When the registry's metadata rows change,
 * change them here too (and vice-versa); {@see metadataCodes()} is the list
 * this contract guarantees, and a core test pins it.
 *
 * Length thresholds mirror the same split the scan uses: the UPPER bounds are
 * the core {@see \Rankbeam\Seo\Services\SEOWarningEvaluator} 60/160 (so the
 * audit never contradicts the editor's counters); the LOWER bounds below are
 * the audit floors, identical to the Pro `IssueRegistry::TITLE_MIN_LENGTH` /
 * `DESCRIPTION_MIN_LENGTH`.
 */
final class MetadataIssues
{
    public const TITLE_MIN_LENGTH = 30;

    public const DESCRIPTION_MIN_LENGTH = 70;

    /**
     * Code => {severity, field}. Mirrors the Pro IssueRegistry EXEC_METADATA
     * rows exactly.
     *
     * @return array<string, array{severity: string, field: string|null}>
     */
    public static function definitions(): array
    {
        $c = AuditIssue::SEVERITY_CRITICAL;
        $w = AuditIssue::SEVERITY_WARNING;
        $n = AuditIssue::SEVERITY_NOTICE;

        return [
            'missing_title' => ['severity' => $c, 'field' => 'title'],
            'missing_description' => ['severity' => $w, 'field' => 'description'],
            'missing_og_image' => ['severity' => $n, 'field' => 'og_image'],
            'missing_focus_keyword' => ['severity' => $n, 'field' => 'focus_keywords'],
            'duplicate_title' => ['severity' => $w, 'field' => 'title'],
            'duplicate_description' => ['severity' => $w, 'field' => 'description'],
            'title_too_long' => ['severity' => $w, 'field' => 'title'],
            'title_too_short' => ['severity' => $n, 'field' => 'title'],
            'description_too_long' => ['severity' => $w, 'field' => 'description'],
            'description_too_short' => ['severity' => $n, 'field' => 'description'],
            'robots_conflict_indexing' => ['severity' => $c, 'field' => 'robots'],
            'robots_conflict_following' => ['severity' => $w, 'field' => 'robots'],
            'noindex_warning' => ['severity' => $w, 'field' => 'robots'],
            'invalid_canonical' => ['severity' => $c, 'field' => 'canonical'],
            'cross_domain_canonical' => ['severity' => $w, 'field' => 'canonical'],
            'shared_canonical' => ['severity' => $n, 'field' => 'canonical'],
            'insecure_canonical' => ['severity' => $w, 'field' => 'canonical'],

            // ── Answer-readiness (AEO) — article structured-data signals AI
            //    answer engines use for attribution and recency. Advisory:
            //    notice-level, and held OUT of the Pro 0-100 score.
            'aeo_missing_author' => ['severity' => $n, 'field' => 'schema'],
            'aeo_article_missing_date' => ['severity' => $n, 'field' => 'schema'],
        ];
    }

    /**
     * Core-only audit codes that are NOT part of the Pro EXEC_METADATA mirror.
     *
     * These surface conditions the free audit can observe from the model plus
     * the resolver configuration that the Pro scan does not enumerate as a scan
     * code. They are deliberately kept OUT of {@see metadataCodes()} (the list
     * pinned identical to the Pro registry), but {@see make()} can still build
     * them and {@see has()} recognises them.
     *
     * - `blank_explicit_override`: a persisted blank/whitespace `seo_meta`
     *   string silently overrides the computed/default value while
     *   `seo.resolver.blank_is_unset` is off (see
     *   {@see \Rankbeam\Seo\Services\SEOResolver}). It is not tied to a single
     *   field — the blanked columns are listed in the issue context — so its
     *   field is null.
     *
     * @return array<string, array{severity: string, field: string|null}>
     */
    public static function coreDefinitions(): array
    {
        return [
            'blank_explicit_override' => ['severity' => AuditIssue::SEVERITY_WARNING, 'field' => null],
        ];
    }

    /**
     * The full catalogue {@see make()} can build from: the Pro-mirror metadata
     * rows plus the core-only codes. {@see metadataCodes()} intentionally
     * returns ONLY the mirror, so the shared-with-Pro contract is unaffected.
     *
     * @return array<string, array{severity: string, field: string|null}>
     */
    public static function catalogue(): array
    {
        return self::definitions() + self::coreDefinitions();
    }

    /**
     * The stable list of metadata codes the free audit shares with the Pro
     * registry. Core-only codes ({@see coreDefinitions()}) are excluded so this
     * list stays identical to the Pro EXEC_METADATA rows.
     *
     * @return array<int, string>
     */
    public static function metadataCodes(): array
    {
        return array_keys(self::definitions());
    }

    public static function has(string $code): bool
    {
        return isset(self::catalogue()[$code]);
    }

    /**
     * Build an {@see AuditIssue} from a registered code, stamping its severity
     * and field. An unknown code throws — the auditor can only ever emit a code
     * the catalogue defines, exactly like the Pro registry's `make()`.
     *
     * @param  array<string, mixed>  $context
     */
    public static function make(string $code, string $message, array $context = []): AuditIssue
    {
        $definition = self::catalogue()[$code] ?? null;

        if ($definition === null) {
            throw new InvalidArgumentException("Unknown SEO audit issue code [{$code}]. Add it to ".self::class.'.');
        }

        return new AuditIssue($code, $definition['severity'], $definition['field'], $message, $context);
    }
}
