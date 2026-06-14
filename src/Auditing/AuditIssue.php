<?php

declare(strict_types=1);

namespace Rankbeam\Seo\Auditing;

/**
 * One finding from the free in-process SEO audit (`seo:audit`).
 *
 * The audit only runs the *metadata* execution class — checks resolvable from
 * a model + the SEOResolver, with no page fetch and no rendered HTML. The Pro
 * scan layers the rendered-HTML and live-canonical (network) classes on top of
 * these same codes; see {@see MetadataIssues} for the catalogue and the
 * capability boundary the command prints on every run.
 *
 * The severity strings are intentionally identical to the Pro
 * `Rankbeam\Seo\Pro\Models\SEOScanIssue::SEVERITY_*` constants and the
 * `Rankbeam\Seo\Pro\Scanning\IssueRegistry` values, so the same issue code
 * means the same thing in the free audit, the Pro scan, the Filament editor,
 * and any export. The free core cannot depend on Pro, so they are re-declared
 * here — keep them in step.
 */
final class AuditIssue
{
    public const SEVERITY_CRITICAL = 'critical';

    public const SEVERITY_WARNING = 'warning';

    public const SEVERITY_NOTICE = 'notice';

    /**
     * @param  array<string, mixed>  $context  Evidence; keys match the audit catalogue's shape
     */
    public function __construct(
        public readonly string $code,
        public readonly string $severity,
        public readonly ?string $field,
        public readonly string $message,
        public readonly array $context = [],
    ) {}

    /**
     * @return array{code: string, severity: string, field: string|null, message: string, context?: array<string, mixed>}
     */
    public function toArray(): array
    {
        $data = [
            'code' => $this->code,
            'severity' => $this->severity,
            'field' => $this->field,
            'message' => $this->message,
        ];

        if ($this->context !== []) {
            $data['context'] = $this->context;
        }

        return $data;
    }
}
