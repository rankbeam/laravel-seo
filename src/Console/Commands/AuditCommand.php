<?php

declare(strict_types=1);

namespace Rankbeam\Seo\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;
use Rankbeam\Seo\Auditing\AuditIssue;
use Rankbeam\Seo\Auditing\MetadataAuditor;
use Rankbeam\Seo\Traits\HasSEO;
use Throwable;

/**
 * Free, in-process SEO audit — "what's wrong with my SEO right now".
 *
 * Runs ONLY the metadata-class checks (resolvable from the model + the
 * resolver: missing / over- / under-length title & description, missing OG
 * image, robots conflicts, canonical format / cross-domain / shared / insecure,
 * and the optional missing-focus-keyword notice). No queue, no license, no
 * network — it iterates your models in-process and prints a per-page
 * pass / warn / fail table with a summary.
 *
 * It deliberately does NOT cover the rendered-HTML checks (H1, image alt, thin
 * content, mixed content) or the live-canonical network checks — those need the
 * page's served HTML or an outbound fetch and ship in the Pro scan. The command
 * prints that capability boundary on every run so its coverage is never
 * mistaken for the full scan, and it never produces a numerical score (the
 * 0-100 score is a Pro feature).
 *
 * ## Usage
 * ```bash
 * # Audit the models configured under seo.audit.models / seo.sitemap.models
 * php artisan seo:audit
 *
 * # Audit specific models
 * php artisan seo:audit --model="App\Models\Post" --model="App\Models\Page"
 *
 * # Only list pages that have issues
 * php artisan seo:audit --issues-only
 *
 * # CI gate: non-zero exit when any issue is found
 * php artisan seo:audit --strict
 *
 * # Machine-readable output
 * php artisan seo:audit --json
 * ```
 */
class AuditCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'seo:audit
                            {--model=* : FQCN of a HasSEO model to audit (repeatable; defaults to configured models)}
                            {--locale= : Resolve SEO data in this locale (defaults to the app locale)}
                            {--limit=0 : Maximum records to audit per model (0 = all)}
                            {--issues-only : Only list pages that have at least one issue}
                            {--strict : Exit with a non-zero status when any issue is found}
                            {--json : Output the audit as JSON instead of a table}';

    /**
     * The console command description.
     */
    protected $description = 'Audit SEO metadata for your models in-process (free, no queue or license)';

    /**
     * Execute the console command.
     */
    public function handle(MetadataAuditor $auditor): int
    {
        $classes = $this->resolveModelClasses();

        if ($classes === []) {
            $this->error('No models to audit.');
            $this->line('Pass one with <comment>--model="App\\Models\\Post"</comment>, or list models under');
            $this->line('<comment>seo.audit.models</comment> or <comment>seo.sitemap.models</comment> in config/seo.php.');

            return self::FAILURE;
        }

        $locale = $this->option('locale') ?: null;
        $limit = max(0, (int) $this->option('limit'));

        $report = [];
        $skipped = [];

        foreach ($classes as $class) {
            $reason = null;

            if (! $this->isAuditable($class, $reason)) {
                $skipped[] = [$class, (string) $reason];

                continue;
            }

            $report = array_merge($report, $this->auditClass($auditor, $class, $locale, $limit));
        }

        if ($this->option('json')) {
            $this->line((string) json_encode(
                $this->jsonPayload($report, $skipped),
                JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE,
            ));

            return $this->exitCode($report);
        }

        foreach ($skipped as [$class, $reason]) {
            $this->warn("Skipped {$class}: {$reason}");
        }

        $this->renderReport($report);
        $this->renderCapabilityMatrix();

        return $this->exitCode($report);
    }

    /**
     * Resolve which model classes to audit: --model wins, then
     * seo.audit.models, then the registered sitemap models.
     *
     * @return array<int, string>
     */
    protected function resolveModelClasses(): array
    {
        /** @var array<int, string> $models */
        $models = (array) $this->option('model');

        if ($models !== []) {
            return array_values(array_unique($models));
        }

        $configured = array_keys((array) config('seo.audit.models', []));

        if ($configured !== []) {
            return $configured;
        }

        return array_keys((array) config('seo.sitemap.models', []));
    }

    /**
     * A class is auditable when it exists, is an Eloquent model, and uses the
     * core HasSEO trait.
     */
    protected function isAuditable(string $class, ?string &$reason = null): bool
    {
        if (! class_exists($class)) {
            $reason = 'class not found';

            return false;
        }

        if (! is_subclass_of($class, Model::class)) {
            $reason = 'not an Eloquent model';

            return false;
        }

        if (! in_array(HasSEO::class, class_uses_recursive($class), true)) {
            $reason = 'does not use the HasSEO trait';

            return false;
        }

        return true;
    }

    /**
     * Audit every record of one model class.
     *
     * @return array<int, array{model: string, key: mixed, label: string, url: ?string, status: string, issues: array<int, AuditIssue>}>
     */
    protected function auditClass(MetadataAuditor $auditor, string $class, ?string $locale, int $limit): array
    {
        $rows = [];

        /** @var \Illuminate\Database\Eloquent\Builder<Model> $query */
        $query = $class::query();

        if ($limit > 0) {
            $query->limit($limit);
        }

        $query->chunk(100, function ($models) use ($auditor, $class, $locale, &$rows): void {
            foreach ($models as $model) {
                $issues = $auditor->audit($model, $locale);

                $rows[] = [
                    'model' => $class,
                    'key' => $model->getKey(),
                    'label' => $this->label($model),
                    'url' => $this->url($model),
                    'status' => $this->status($issues),
                    'issues' => $issues,
                ];
            }
        });

        return $rows;
    }

    protected function label(Model $model): string
    {
        return class_basename($model).' #'.$model->getKey();
    }

    protected function url(Model $model): ?string
    {
        if (! method_exists($model, 'getUrlForSEO')) {
            return null;
        }

        try {
            return $model->getUrlForSEO() ?: null;
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * Derive a page status: fail on any critical, warn on any other issue,
     * pass when clean.
     *
     * @param  array<int, AuditIssue>  $issues
     */
    protected function status(array $issues): string
    {
        foreach ($issues as $issue) {
            if ($issue->severity === AuditIssue::SEVERITY_CRITICAL) {
                return 'fail';
            }
        }

        return $issues === [] ? 'pass' : 'warn';
    }

    /**
     * @param  array<int, array<string, mixed>>  $report
     */
    protected function renderReport(array $report): void
    {
        if ($report === []) {
            $this->info('No pages found to audit.');

            return;
        }

        $visible = $this->option('issues-only')
            ? array_values(array_filter($report, static fn (array $r): bool => $r['status'] !== 'pass'))
            : $report;

        if ($visible !== []) {
            $this->table(
                ['Page', 'Status', 'Findings'],
                array_map(fn (array $r): array => [
                    $r['label'],
                    $this->statusCell($r['status']),
                    $this->findingsCell($r['issues']),
                ], $visible),
            );
        } elseif ($this->option('issues-only')) {
            $this->info('No issues found — every audited page passed.');
        }

        $this->renderSummary($report);
    }

    protected function statusCell(string $status): string
    {
        return match ($status) {
            'fail' => '<fg=red;options=bold>FAIL</>',
            'warn' => '<fg=yellow;options=bold>WARN</>',
            default => '<fg=green;options=bold>PASS</>',
        };
    }

    /**
     * @param  array<int, AuditIssue>  $issues
     */
    protected function findingsCell(array $issues): string
    {
        if ($issues === []) {
            return '—';
        }

        return collect($issues)
            ->map(fn (AuditIssue $i): string => $this->severityTag($i->severity).' '.$i->code)
            ->implode("\n");
    }

    protected function severityTag(string $severity): string
    {
        return match ($severity) {
            AuditIssue::SEVERITY_CRITICAL => '<fg=red>critical</>',
            AuditIssue::SEVERITY_WARNING => '<fg=yellow>warning</>',
            default => '<fg=cyan>notice</>',
        };
    }

    /**
     * @param  array<int, array<string, mixed>>  $report
     */
    protected function renderSummary(array $report): void
    {
        $counts = $this->statusCounts($report);
        $severities = $this->severityCounts($report);
        $issueTotal = array_sum($severities);

        $this->newLine();
        $this->line(sprintf(
            '<options=bold>%d page(s)</> — <fg=green>%d passed</>, <fg=yellow>%d warned</>, <fg=red>%d failed</>',
            count($report),
            $counts['pass'],
            $counts['warn'],
            $counts['fail'],
        ));
        $this->line(sprintf(
            '<options=bold>%d issue(s)</> — <fg=red>%d critical</>, <fg=yellow>%d warning</>, <fg=cyan>%d notice</>',
            $issueTotal,
            $severities[AuditIssue::SEVERITY_CRITICAL],
            $severities[AuditIssue::SEVERITY_WARNING],
            $severities[AuditIssue::SEVERITY_NOTICE],
        ));
    }

    /**
     * @param  array<int, array<string, mixed>>  $report
     * @return array{pass: int, warn: int, fail: int}
     */
    protected function statusCounts(array $report): array
    {
        return [
            'pass' => count(array_filter($report, static fn (array $r): bool => $r['status'] === 'pass')),
            'warn' => count(array_filter($report, static fn (array $r): bool => $r['status'] === 'warn')),
            'fail' => count(array_filter($report, static fn (array $r): bool => $r['status'] === 'fail')),
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $report
     * @return array<string, int>
     */
    protected function severityCounts(array $report): array
    {
        $counts = [
            AuditIssue::SEVERITY_CRITICAL => 0,
            AuditIssue::SEVERITY_WARNING => 0,
            AuditIssue::SEVERITY_NOTICE => 0,
        ];

        foreach ($report as $r) {
            /** @var AuditIssue $issue */
            foreach ($r['issues'] as $issue) {
                $counts[$issue->severity] = ($counts[$issue->severity] ?? 0) + 1;
            }
        }

        return $counts;
    }

    /**
     * The capability matrix — printed every run so the free audit's coverage
     * is never mistaken for the full Pro scan.
     */
    protected function renderCapabilityMatrix(): void
    {
        $this->newLine();
        $this->line('<options=bold>Coverage</>');
        $this->line('  <fg=green>Audited here</> (model + resolver, no fetch): title & description');
        $this->line('  presence and length, OG image, robots conflicts, canonical format /');
        $this->line('  cross-domain / shared / insecure, focus keyword.');
        $this->line('  <fg=yellow>Needs the Pro scan</> (rendered HTML / outbound fetch): H1, image alt,');
        $this->line('  thin content, mixed content, and live-canonical (404 / redirect / noindex)');
        $this->line('  checks. The numerical 0-100 score is also a Pro feature.');
        $this->line('  Full issue registry: https://rankbeam.dev/pro/scan-issues');
    }

    /**
     * @param  array<int, array<string, mixed>>  $report
     */
    protected function exitCode(array $report): int
    {
        if (! $this->option('strict')) {
            return self::SUCCESS;
        }

        foreach ($report as $r) {
            if ($r['status'] !== 'pass') {
                return self::FAILURE;
            }
        }

        return self::SUCCESS;
    }

    /**
     * @param  array<int, array<string, mixed>>  $report
     * @param  array<int, array{0: string, 1: string}>  $skipped
     * @return array<string, mixed>
     */
    protected function jsonPayload(array $report, array $skipped): array
    {
        $counts = $this->statusCounts($report);
        $severities = $this->severityCounts($report);

        return [
            'pages' => array_map(static fn (array $r): array => [
                'model' => $r['model'],
                'key' => $r['key'],
                'label' => $r['label'],
                'url' => $r['url'],
                'status' => $r['status'],
                'issues' => array_map(static fn (AuditIssue $i): array => $i->toArray(), $r['issues']),
            ], $report),
            'summary' => [
                'pages' => count($report),
                'passed' => $counts['pass'],
                'warned' => $counts['warn'],
                'failed' => $counts['fail'],
                'issues' => array_sum($severities),
                'by_severity' => $severities,
            ],
            'skipped' => array_map(static fn (array $s): array => ['model' => $s[0], 'reason' => $s[1]], $skipped),
            'coverage' => [
                'executes' => 'metadata',
                'note' => 'Model + resolver checks only. Rendered-HTML (H1, image alt, thin/mixed content) and live-canonical network checks require the Pro scan; the numerical score is a Pro feature.',
                'reference' => 'https://rankbeam.dev/pro/scan-issues',
            ],
        ];
    }
}
