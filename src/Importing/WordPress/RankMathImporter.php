<?php

declare(strict_types=1);

namespace Rankbeam\Seo\Importing\WordPress;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Rankbeam\Seo\Importing\ImportOptions;
use Rankbeam\Seo\Importing\ImportResult;

/**
 * Imports SEO data stored by the **Rank Math** WordPress plugin
 * (`rank_math_*` keys in `wp_postmeta`, plus its redirections table) into core
 * `seo_meta`.
 *
 * Field mapping (explicit — keys with no Core 3 home are reported, never
 * invented):
 *
 *   rank_math_title             -> title         (template tokens resolved)
 *   rank_math_description       -> description   (template tokens resolved)
 *   rank_math_canonical_url     -> canonical
 *   rank_math_robots            -> robots        (serialized array, defaults dropped)
 *   rank_math_focus_keyword     -> focus_keywords
 *   rank_math_facebook_title    -> og_title
 *   rank_math_facebook_description -> og_description
 *   rank_math_facebook_image    -> og_image
 *   rank_math_twitter_title     -> twitter_title
 *   rank_math_twitter_description -> twitter_description
 *   rank_math_twitter_image     -> twitter_image
 *   rank_math_twitter_card_type -> twitter_card
 *
 * **Template tokens.** Rank Math uses the `%title%` / `%sep%` / `%sitename%`
 * single-percent syntax; the resolver handles both that and Yoast's
 * double-percent form. Derivable tokens are resolved, the rest stripped.
 *
 * **Redirects.** Rank Math stores redirects in `{prefix}rank_math_redirections`.
 * Active rules with an exact-match source are emitted to the `--redirects-csv`
 * for import into Rankbeam Pro (`seo_redirects` is a Pro table — core never
 * writes it). Non-exact rules (regex/contains/start/end) are reported as
 * skipped, since they do not map to a single exact path.
 */
class RankMathImporter extends WordPressDatabaseImporter
{
    public function key(): string
    {
        return 'rank-math';
    }

    public function label(): string
    {
        return 'Rank Math (WordPress)';
    }

    protected function metaKeyPrefix(): string
    {
        return 'rank_math_';
    }

    protected function structuralKeys(): array
    {
        return [
            'seo_score',
            'pillar_content',
            'internal_links_processed',
            'facebook_image_id',
            'twitter_image_id',
            'twitter_use_facebook',
            'primary_category',
            'primary_product_cat',
            'rich_snippet',           // schema type marker (the schema graph lives elsewhere)
        ];
    }

    protected function mapMeta(array $meta, array $context, ImportResult $result): array
    {
        $prefix = $this->metaKeyPrefix();
        $get = fn (string $short): ?string => $this->clean($meta[$prefix.$short] ?? null);

        $tokenContext = [
            'title' => $context['title'] ?? '',
            'sitename' => $context['sitename'] ?? '',
            'sep' => $context['sep'] ?? '-',
            'excerpt' => $context['excerpt'] ?? '',
        ];

        $title = $this->resolveTokens($get('title'), $tokenContext);
        $description = $this->resolveTokens($get('description'), $tokenContext);

        if ($this->hadToken($get('title')) || $this->hadToken($get('description'))) {
            $result->warn($this->tokenNotice());
        }

        $attributes = array_filter([
            'title' => $this->fit($title, 'title', $result),
            'description' => $this->fit($description, 'description', $result),
            'canonical' => $get('canonical_url'),
            'robots' => $this->fit($this->composeRobots($meta), 'robots', $result),
            'og_title' => $this->fit($this->resolveTokens($get('facebook_title'), $tokenContext), 'og_title', $result),
            'og_description' => $this->fit($this->resolveTokens($get('facebook_description'), $tokenContext), 'og_description', $result),
            'og_image' => $get('facebook_image'),
            'twitter_title' => $this->fit($this->resolveTokens($get('twitter_title'), $tokenContext), 'twitter_title', $result),
            'twitter_description' => $this->fit($this->resolveTokens($get('twitter_description'), $tokenContext), 'twitter_description', $result),
            'twitter_image' => $get('twitter_image'),
            'twitter_card' => $this->fit($get('twitter_card_type'), 'twitter_card', $result),
        ], static fn ($value) => $value !== null);

        $keywords = $this->focusKeywords($get('focus_keyword'));

        if ($keywords !== null) {
            $attributes['focus_keywords'] = $keywords;
        }

        $this->reportUnmapped($meta, $this->mappedKeys(), $result);

        return $attributes;
    }

    /**
     * Read the Rank Math redirections table (when present) and emit each active,
     * exact-match rule as a redirect candidate. Non-exact rules are reported.
     */
    protected function importRedirects(ImportOptions $options, RedirectCsvWriter $writer, ImportResult $result): void
    {
        if (! $writer->enabled()) {
            return;
        }

        $table = $this->prefix($options).'rank_math_redirections';

        if (! Schema::connection($options->connection)->hasTable($table)) {
            return;
        }

        DB::connection($options->connection)
            ->table($table)
            ->where('status', 'active')
            ->orderBy('id')
            ->each(function (object $rule) use ($writer, $result): void {
                $target = $this->clean($rule->url_to ?? null);

                if ($target === null) {
                    return;
                }

                $status = (int) ($rule->header_code ?? 301);
                $status = $status > 0 ? $status : 301;

                foreach ($this->redirectSources($rule->sources ?? null, $result) as $sourcePath) {
                    $this->emitRedirect($writer, $result, $sourcePath, $target, $status, 'Rank Math redirection');
                }
            });
    }

    /**
     * Extract exact-match source paths from a Rank Math `sources` value
     * (a serialized array of ['pattern' => …, 'comparison' => …]). Non-exact
     * comparisons are reported as skipped rather than mis-imported.
     *
     * @return array<int, string>
     */
    protected function redirectSources(?string $sources, ImportResult $result): array
    {
        if ($sources === null || $sources === '') {
            return [];
        }

        $decoded = @unserialize($sources, ['allowed_classes' => false]);

        if (! is_array($decoded)) {
            return [];
        }

        $paths = [];

        foreach ($decoded as $source) {
            if (! is_array($source)) {
                continue;
            }

            $pattern = isset($source['pattern']) && is_scalar($source['pattern'])
                ? trim((string) $source['pattern'])
                : '';
            $comparison = isset($source['comparison']) && is_scalar($source['comparison'])
                ? (string) $source['comparison']
                : 'exact';

            if ($pattern === '') {
                continue;
            }

            if ($comparison !== 'exact') {
                $result->skip($pattern, null, sprintf('redirect rule uses "%s" matching (only exact rules are imported)', $comparison));

                continue;
            }

            $paths[] = $this->pathFromUrl($pattern) ?? '/'.ltrim($pattern, '/');
        }

        return $paths;
    }

    /**
     * Compose a robots string from Rank Math's serialized robots array, dropping
     * the index/follow defaults so only deviations are stored.
     *
     * @param  array<string, string>  $meta
     */
    protected function composeRobots(array $meta): ?string
    {
        $values = $this->unserializeList($meta[$this->metaKeyPrefix().'robots'] ?? null);

        $directives = [];

        foreach ($values as $value) {
            $value = strtolower(trim($value));

            if ($value === '' || $value === 'index' || $value === 'follow') {
                continue;
            }

            $directives[] = $value;
        }

        return $directives === [] ? null : implode(', ', array_unique($directives));
    }

    protected function hadToken(?string $value): bool
    {
        return $value !== null && str_contains($value, '%');
    }

    protected function tokenNotice(): string
    {
        return 'Resolved Rank Math template tokens (%title%, %sitename%, %sep%) in one or more '
            .'title/description values; unresolvable tokens (e.g. %page%) were stripped. Review imported titles.';
    }

    /**
     * @return array<int, string>
     */
    protected function mappedKeys(): array
    {
        $prefix = $this->metaKeyPrefix();

        return array_map(fn (string $short): string => $prefix.$short, [
            'title', 'description', 'canonical_url', 'robots', 'focus_keyword',
            'facebook_title', 'facebook_description', 'facebook_image',
            'twitter_title', 'twitter_description', 'twitter_image', 'twitter_card_type',
        ]);
    }
}
