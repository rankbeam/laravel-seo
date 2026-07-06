<?php

declare(strict_types=1);

namespace Rankbeam\Seo\Explaining;

use Illuminate\Database\Eloquent\Model;
use Rankbeam\Seo\Data\SEOData;
use Rankbeam\Seo\Services\IndexingGuard;
use Rankbeam\Seo\Services\SEOResolver;

/**
 * Builds the resolver precedence trace behind `seo:explain`.
 *
 * It does NOT re-implement the merge: it reads the raw per-layer contributions
 * from {@see SEOResolver::layerContributions()} (which reuses the resolver's own
 * layer sources), and it takes the FINAL, post-processed values from the real
 * {@see SEOResolver::resolve()} — so the "who set this" attribution and the
 * "what actually renders" ground truth can never drift from the resolver.
 *
 * For each field it reports: the winning layer + value, every losing layer's
 * value (overridden by "last non-null wins"), the final value, and any
 * post-processing that changed it (title suffix, canonical query-strip, og:url
 * derivation, image absolutization, the non-production indexing guard). It also
 * reports the SITE-LEVEL resolution ledger — site name, default locale, and
 * canonical host — naming which source set each (env / config / programmatic /
 * request), the class of bug canonical-host resolution is notorious for.
 */
class ResolutionExplainer
{
    /**
     * The precedence layers, lowest → highest, matching
     * {@see SEOResolver::layerContributions()}.
     *
     * @var array<int, string>
     */
    public const LAYERS = ['config', 'global', 'model-type', 'route', 'computed', 'explicit'];

    /**
     * The fields explained, in display order (flat snake_case keys, as returned
     * by {@see SEOData::toFlatArray()}).
     *
     * @var array<int, string>
     */
    public const FIELDS = [
        'title', 'description', 'canonical', 'robots',
        'og_title', 'og_description', 'og_image', 'og_type', 'og_site_name', 'og_url',
        'twitter_card', 'twitter_title', 'twitter_description', 'twitter_image', 'twitter_site', 'twitter_creator',
        'author', 'section', 'published_time', 'modified_time', 'tags',
        'locale', 'focus_keywords', 'schema_jsonld', 'alternates',
    ];

    public function __construct(
        protected SEOResolver $resolver,
        protected IndexingGuard $guard,
    ) {}

    /**
     * Produce the full resolution trace for a model.
     *
     * @param  Model  $model  The HasSEO model to explain
     * @param  string|null  $route  Optional route name for the route-defaults layer
     * @param  string|null  $locale  Optional locale (app locale if null)
     * @return array<string, mixed> The structured trace (human formatter + --json both consume this)
     */
    public function explain(Model $model, ?string $route = null, ?string $locale = null): array
    {
        $locale ??= app()->getLocale();

        $layers = $this->resolver->layerContributions($model, $route, $locale);
        $final = $this->resolver->resolve($model, $route, $locale);
        $finalFlat = $final->toFlatArray();

        // Each applicable layer flattened once, in precedence order.
        $layerFlat = [];
        foreach (self::LAYERS as $layer) {
            $contribution = $layers[$layer] ?? null;
            $layerFlat[$layer] = $contribution instanceof SEOData ? $contribution->toFlatArray() : null;
        }

        $fields = [];
        foreach (self::FIELDS as $field) {
            $fields[$field] = $this->explainField($field, $layerFlat, $finalFlat[$field] ?? null, $model);
        }

        return [
            'target' => [
                'model' => $model::class,
                'id' => $model->getKey(),
                'route' => $route,
                'locale' => $locale,
            ],
            'fields' => $fields,
            'site_level' => $this->siteLevel($model, $final, $fields['canonical']['winner'] ?? null),
        ];
    }

    /**
     * Attribute one field across the layers and note any post-processing.
     *
     * @param  array<string, array<string, mixed>|null>  $layerFlat  Each layer's flat contribution (or null)
     * @param  mixed  $final  The final, post-processed value
     * @return array{final: mixed, winner: array{layer: string, value: mixed}|null, losers: array<int, array{layer: string, value: mixed}>, notes: array<int, string>}
     */
    protected function explainField(string $field, array $layerFlat, mixed $final, Model $model): array
    {
        // A layer contributes a field iff its value is non-null — exactly the
        // condition SEOData::merge() uses ("last non-null wins").
        $contributions = [];
        foreach (self::LAYERS as $layer) {
            $flat = $layerFlat[$layer];
            if ($flat === null) {
                continue;
            }

            $value = $flat[$field] ?? null;
            if ($value !== null) {
                $contributions[] = ['layer' => $layer, 'value' => $value];
            }
        }

        $winner = $contributions === [] ? null : $contributions[count($contributions) - 1];
        $losers = $contributions === [] ? [] : array_slice($contributions, 0, -1);

        return [
            'final' => $final,
            'winner' => $winner,
            'losers' => $losers,
            'notes' => $this->postProcessingNotes($field, $winner['value'] ?? null, $final, $model),
        ];
    }

    /**
     * Notes describing how post-processing changed a field between its winning
     * layer value and the final rendered value.
     *
     * @return array<int, string>
     */
    protected function postProcessingNotes(string $field, mixed $winnerValue, mixed $final, Model $model): array
    {
        $notes = [];

        // The indexing guard forces robots above every layer.
        if ($field === 'robots'
            && $this->guard->active()
            && $final === IndexingGuard::DIRECTIVE
            && $winnerValue !== IndexingGuard::DIRECTIVE) {
            $notes[] = "indexing guard forced '".IndexingGuard::DIRECTIVE."' (environment '"
                .$this->guard->currentEnvironment()."' is not allowed to index)";
        }

        if ($field === 'title' && is_string($final)) {
            $suffix = (string) config('seo.title_suffix', '');
            if ($suffix !== '' && is_string($winnerValue) && $final === $winnerValue.$suffix) {
                $notes[] = "title suffix '{$suffix}' appended";
            } elseif ($suffix !== '' && is_string($winnerValue) && $final === $winnerValue && str_contains($winnerValue, trim($suffix, ' |·-–—'))) {
                $notes[] = 'title suffix skipped (title already carries the brand)';
            }
        }

        if ($field === 'canonical' && is_string($final)) {
            if ($winnerValue === null) {
                $source = method_exists($model, 'getUrlForSEO') ? 'model getUrlForSEO()' : 'the request URL';
                $note = "derived from {$source}";

                $raw = $this->rawModelUrl($model);
                if (is_string($raw) && str_contains($raw, '?') && ! str_contains($final, '?')) {
                    $note .= ' (query string stripped)';
                }
                $notes[] = $note;
            }
        }

        if ($field === 'og_url' && is_string($final) && $winnerValue === null) {
            $notes[] = 'derived from the canonical URL';
        }

        if (($field === 'og_image' || $field === 'twitter_image')
            && is_string($final)
            && is_string($winnerValue)
            && ! $this->isAbsoluteUrl($winnerValue)
            && $this->isAbsoluteUrl($final)) {
            $notes[] = "absolutized from '{$winnerValue}'";
        }

        return $notes;
    }

    /**
     * The site-level resolution ledger: which source set each site-wide value.
     *
     * @param  array{layer: string, value: mixed}|null  $canonicalWinner  The winning layer for the canonical field, if any
     * @return array<string, array{value: mixed, source: string}>
     */
    protected function siteLevel(Model $model, SEOData $final, ?array $canonicalWinner): array
    {
        return [
            'site_name' => $this->siteName(),
            'default_locale' => $this->defaultLocale(),
            'canonical_host' => $this->canonicalHost($model, $final, $canonicalWinner),
        ];
    }

    /**
     * @return array{value: mixed, source: string}
     */
    protected function siteName(): array
    {
        $value = config('seo.site_name', config('app.name'));
        $envName = env('APP_NAME');

        if ($value !== null && $value === $envName) {
            $source = 'env (APP_NAME)';
        } elseif (config('seo.site_name') !== null) {
            $source = 'config (seo.site_name)';
        } else {
            $source = 'config (app.name)';
        }

        return ['value' => $value, 'source' => $source];
    }

    /**
     * @return array{value: mixed, source: string}
     */
    protected function defaultLocale(): array
    {
        // The effective app locale. Note: a runtime App::setLocale() (locale
        // middleware, a provider) is NOT distinguishable from the configured
        // default here — Laravel's setLocale() writes back to config('app.locale')
        // — so we honestly attribute to env vs config only, never guess
        // "programmatic".
        $value = app()->getLocale();
        $source = env('APP_LOCALE') !== null && $value === env('APP_LOCALE')
            ? 'env (APP_LOCALE)'
            : 'config (app.locale)';

        return ['value' => $value, 'source' => $source];
    }

    /**
     * The canonical host + the source that actually set it.
     *
     * A canonical set by a real layer (a stored seo_meta canonical, or a
     * default-layer canonical) is attributed to that layer — NOT to the model
     * URL just because getUrlForSEO() happens to exist. The model-URL / request
     * attribution applies only when the canonical was DERIVED in post-processing
     * (no layer set it).
     *
     * @param  array{layer: string, value: mixed}|null  $canonicalWinner
     * @return array{value: mixed, source: string}
     */
    protected function canonicalHost(Model $model, SEOData $final, ?array $canonicalWinner): array
    {
        $host = is_string($final->canonical) && $final->canonical !== ''
            ? (parse_url($final->canonical, PHP_URL_HOST) ?: null)
            : (parse_url((string) config('app.url'), PHP_URL_HOST) ?: null);

        if ($canonicalWinner !== null) {
            // A layer explicitly provided the canonical (stored seo_meta, a
            // global/model-type/route default, …).
            $source = "{$canonicalWinner['layer']} layer (explicit canonical)";
        } elseif (is_string($final->canonical) && $final->canonical !== '') {
            // Derived during post-processing from the model URL or the request.
            if (method_exists($model, 'getUrlForSEO')) {
                $source = 'programmatic (model getUrlForSEO())';
            } elseif (request()) {
                $source = 'request';
            } else {
                $source = 'config (app.url)';
            }
        } else {
            $source = 'config (app.url)';
        }

        return ['value' => $host, 'source' => $source];
    }

    /**
     * The model's own SEO URL, if it exposes one — used only to detect a
     * stripped query string on the derived canonical.
     */
    protected function rawModelUrl(Model $model): ?string
    {
        if (! method_exists($model, 'getUrlForSEO')) {
            return null;
        }

        try {
            $url = $model->getUrlForSEO();
        } catch (\Throwable) {
            return null;
        }

        return is_string($url) ? $url : null;
    }

    /**
     * Whether a URL is already absolute (has a scheme or is protocol-relative).
     */
    protected function isAbsoluteUrl(string $url): bool
    {
        return str_starts_with($url, 'http://')
            || str_starts_with($url, 'https://')
            || str_starts_with($url, '//');
    }
}
