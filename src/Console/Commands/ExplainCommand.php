<?php

declare(strict_types=1);

namespace Rankbeam\Seo\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;
use Rankbeam\Seo\Explaining\ResolutionExplainer;
use Rankbeam\Seo\Traits\HasSEO;

/**
 * Explain how the SEOResolver resolved a page — the precedence trace.
 *
 * For every field it shows the winning layer and value, each losing layer's
 * value (overridden by "last non-null wins"), and any post-processing that
 * changed the final value (title suffix, canonical query-strip, og:url
 * derivation, image absolutization, the non-production indexing guard). It also
 * prints the SITE-LEVEL ledger — site name, default locale, canonical host —
 * naming which source set each (env / config / programmatic / request).
 *
 * It never resolves the merge itself: attribution comes from the resolver's own
 * layer contributions and the final values from the real resolve(), so the
 * explanation cannot drift from what actually renders. Read-only.
 *
 * ## Usage
 * ```bash
 * # Explain a specific record
 * php artisan seo:explain "App\Models\Post" 1
 *
 * # Explain the first record of a model
 * php artisan seo:explain "App\Models\Post"
 *
 * # With a route-defaults layer and a locale
 * php artisan seo:explain "App\Models\Post" 1 --route=posts.show --locale=de
 *
 * # Machine-readable
 * php artisan seo:explain "App\Models\Post" 1 --json
 * ```
 */
class ExplainCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'seo:explain
                            {model : FQCN of a HasSEO model}
                            {id? : Primary key to explain (defaults to the first record)}
                            {--route= : Route name for the route-defaults layer}
                            {--locale= : Resolve in this locale (defaults to the app locale)}
                            {--json : Output the trace as JSON instead of a table}';

    /**
     * The console command description.
     */
    protected $description = 'Explain the resolver precedence trace for a page (which layer set each field, and why)';

    /**
     * Execute the console command.
     */
    public function handle(ResolutionExplainer $explainer): int
    {
        /** @var string $class */
        $class = $this->argument('model');

        if (! $this->isExplainable($class, $reason)) {
            $this->error("Cannot explain {$class}: {$reason}.");

            return self::FAILURE;
        }

        $model = $this->resolveModel($class);

        if ($model === null) {
            return self::FAILURE;
        }

        $trace = $explainer->explain(
            $model,
            $this->option('route') ?: null,
            $this->option('locale') ?: null,
        );

        if ($this->option('json')) {
            $this->line((string) json_encode($trace, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

            return self::SUCCESS;
        }

        $this->renderTrace($trace);

        return self::SUCCESS;
    }

    /**
     * A class is explainable when it exists, is an Eloquent model, and uses the
     * core HasSEO trait.
     */
    protected function isExplainable(string $class, ?string &$reason = null): bool
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
     * Find the record to explain: the given id, or the first record. Prints its
     * own error and returns null when nothing is found.
     *
     * @param  class-string<Model>  $class
     */
    protected function resolveModel(string $class): ?Model
    {
        $id = $this->argument('id');

        if ($id !== null) {
            /** @var Model|null $model */
            $model = $class::query()->find($id);

            if ($model === null) {
                $this->error("No {$class} found with key {$id}.");
            }

            return $model;
        }

        /** @var Model|null $model */
        $model = $class::query()->first();

        if ($model === null) {
            $this->error("No {$class} records to explain.");

            return null;
        }

        // Never write this human notice under --json — it would precede the
        // payload and make stdout invalid JSON for tooling.
        if (! $this->option('json')) {
            $this->line("<comment>No id given — explaining the first record (#{$model->getKey()}).</comment>");
        }

        return $model;
    }

    /**
     * @param  array<string, mixed>  $trace
     */
    protected function renderTrace(array $trace): void
    {
        $target = $trace['target'];

        $this->newLine();
        $this->line(sprintf(
            '<options=bold>SEO resolution — %s #%s</>  <fg=gray>(locale: %s, route: %s)</>',
            class_basename((string) $target['model']),
            (string) $target['id'],
            (string) $target['locale'],
            $target['route'] !== null ? (string) $target['route'] : '—',
        ));
        $this->line('<fg=gray>Layers, low → high: config · global · model-type · route · computed · explicit</>');
        $this->newLine();

        $rows = [];
        foreach ($trace['fields'] as $field => $info) {
            // Skip fields that neither resolved to a value nor had any contributor.
            if ($info['final'] === null && $info['winner'] === null) {
                continue;
            }

            $final = $this->display($info['final']);
            foreach ($info['notes'] as $note) {
                $final .= "\n<fg=gray>↳ {$note}</>";
            }

            $rows[] = [
                "<fg=cyan>{$field}</>",
                $final,
                $this->setBy($info),
                $this->overrode($info['losers']),
            ];
        }

        $this->table(['Field', 'Final value', 'Set by', 'Overrode'], $rows);

        $this->renderSiteLevel($trace['site_level']);
    }

    /**
     * The "Set by" cell: the winning layer, or how the value arrived when no
     * layer set it (post-processing / default fallthrough).
     *
     * @param  array<string, mixed>  $info
     */
    protected function setBy(array $info): string
    {
        if ($info['winner'] !== null) {
            return "<fg=green>{$info['winner']['layer']}</>";
        }

        // No layer contributed but a value exists ⇒ it came from post-processing
        // (a derived canonical/og:url, an absolutized image, the guard).
        return $info['final'] !== null ? '<fg=yellow>post-processing</>' : '—';
    }

    /**
     * The "Overrode" cell: every losing layer with its value, in order.
     *
     * @param  array<int, array{layer: string, value: mixed}>  $losers
     */
    protected function overrode(array $losers): string
    {
        if ($losers === []) {
            return '—';
        }

        return implode("\n", array_map(
            fn (array $l): string => "<fg=gray>{$l['layer']}: {$this->display($l['value'], 32)}</>",
            $losers,
        ));
    }

    /**
     * @param  array<string, array{value: mixed, source: string}>  $siteLevel
     */
    protected function renderSiteLevel(array $siteLevel): void
    {
        $labels = [
            'site_name' => 'Site name',
            'default_locale' => 'Default locale',
            'canonical_host' => 'Canonical host',
        ];

        $rows = [];
        foreach ($siteLevel as $key => $entry) {
            $rows[] = [
                '<fg=cyan>'.($labels[$key] ?? $key).'</>',
                $this->display($entry['value']),
                "<fg=gray>{$entry['source']}</>",
            ];
        }

        $this->newLine();
        $this->line('<options=bold>Site-level resolution</>');
        $this->table(['Value', 'Resolved', 'Source'], $rows);
    }

    /**
     * Render a value for a table cell: null as an em dash, arrays compactly,
     * long strings truncated.
     */
    protected function display(mixed $value, int $max = 60): string
    {
        if ($value === null) {
            return '—';
        }

        if (is_array($value)) {
            $value = json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            $value = $value === false ? '[array]' : $value;
        }

        $value = (string) $value;

        if (mb_strlen($value) > $max) {
            return mb_substr($value, 0, $max - 1).'…';
        }

        return $value;
    }
}
