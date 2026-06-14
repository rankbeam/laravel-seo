<?php

declare(strict_types=1);

namespace Rankbeam\Seo\Importing\WordPress;

use Rankbeam\Seo\Importing\ImportResult;

/**
 * Imports SEO data stored by the **Yoast SEO** WordPress plugin
 * (`_yoast_wpseo_*` keys in `wp_postmeta`) into core `seo_meta`.
 *
 * Field mapping (explicit — keys with no Core 3 home are reported, never
 * invented):
 *
 *   _yoast_wpseo_title                 -> title         (template tokens resolved)
 *   _yoast_wpseo_metadesc              -> description   (template tokens resolved)
 *   _yoast_wpseo_canonical             -> canonical
 *   _yoast_wpseo_meta-robots-noindex   -> robots        (1 => "noindex")
 *   _yoast_wpseo_meta-robots-nofollow  -> robots        (1 => "nofollow")
 *   _yoast_wpseo_meta-robots-adv       -> robots        (noarchive, nosnippet, noimageindex)
 *   _yoast_wpseo_focuskw               -> focus_keywords
 *   _yoast_wpseo_opengraph-title       -> og_title      (tokens resolved)
 *   _yoast_wpseo_opengraph-description -> og_description (tokens resolved)
 *   _yoast_wpseo_opengraph-image       -> og_image
 *   _yoast_wpseo_twitter-title         -> twitter_title
 *   _yoast_wpseo_twitter-description   -> twitter_description
 *   _yoast_wpseo_twitter-image         -> twitter_image
 *
 * **Template tokens.** Yoast titles/descriptions are templates like
 * `%%title%% %%sep%% %%sitename%%`. The tokens we can derive (`%%title%%`,
 * `%%sitename%%` from `wp_options`, `%%sep%%`) are resolved; the rest
 * (`%%page%%`, `%%primary_category%%`, …) are stripped. A stored value is never
 * a raw token string. Rows where a token was resolved are flagged once.
 *
 * **Redirects.** The free Yoast SEO plugin has no redirect table (only Yoast
 * Premium does, and its schema is not part of the free package), so this
 * importer emits no redirects — use the CSV importer's canonical-based
 * redirect emission, or migrate Yoast Premium redirects manually.
 */
class YoastImporter extends WordPressDatabaseImporter
{
    public function key(): string
    {
        return 'yoast';
    }

    public function label(): string
    {
        return 'Yoast SEO (WordPress)';
    }

    protected function metaKeyPrefix(): string
    {
        return '_yoast_wpseo_';
    }

    protected function structuralKeys(): array
    {
        return [
            'linkdex',                          // keyword score
            'content_score',                    // readability score
            'estimated-reading-time-minutes',
            'opengraph-image-id',               // attachment id — the URL is imported instead
            'twitter-image-id',
            'primary_category',                 // taxonomy choice, not stored meta
            'is_cornerstone',
            'wordproof_timestamp',
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
        $description = $this->resolveTokens($get('metadesc'), $tokenContext);
        $ogTitle = $this->resolveTokens($get('opengraph-title'), $tokenContext);
        $ogDescription = $this->resolveTokens($get('opengraph-description'), $tokenContext);

        if ($this->hadToken($get('title')) || $this->hadToken($get('metadesc'))) {
            $result->warn($this->tokenNotice());
        }

        $attributes = array_filter([
            'title' => $this->fit($title, 'title', $result),
            'description' => $this->fit($description, 'description', $result),
            'canonical' => $get('canonical'),
            'robots' => $this->fit($this->composeRobots($meta), 'robots', $result),
            'og_title' => $this->fit($ogTitle, 'og_title', $result),
            'og_description' => $this->fit($ogDescription, 'og_description', $result),
            'og_image' => $get('opengraph-image'),
            'twitter_title' => $this->fit($this->resolveTokens($get('twitter-title'), $tokenContext), 'twitter_title', $result),
            'twitter_description' => $this->fit($this->resolveTokens($get('twitter-description'), $tokenContext), 'twitter_description', $result),
            'twitter_image' => $get('twitter-image'),
        ], static fn ($value) => $value !== null);

        $keywords = $this->focusKeywords($get('focuskw'));

        if ($keywords !== null) {
            $attributes['focus_keywords'] = $keywords;
        }

        $this->reportUnmapped($meta, $this->mappedKeys(), $result);

        return $attributes;
    }

    /**
     * Compose a robots directive string from Yoast's separate robots meta keys.
     * Only deviations from the WordPress defaults are emitted (so a normal
     * indexable page leaves `robots` null and inherits the site default).
     *
     * @param  array<string, string>  $meta
     */
    protected function composeRobots(array $meta): ?string
    {
        $prefix = $this->metaKeyPrefix();
        $directives = [];

        if (($meta[$prefix.'meta-robots-noindex'] ?? null) === '1') {
            $directives[] = 'noindex';
        }

        if (($meta[$prefix.'meta-robots-nofollow'] ?? null) === '1') {
            $directives[] = 'nofollow';
        }

        $advanced = $this->clean($meta[$prefix.'meta-robots-adv'] ?? null);

        if ($advanced !== null && strtolower($advanced) !== 'none') {
            foreach (explode(',', $advanced) as $directive) {
                $directive = trim($directive);

                if ($directive !== '') {
                    $directives[] = $directive;
                }
            }
        }

        return $directives === [] ? null : implode(', ', array_unique($directives));
    }

    /**
     * Whether a stored value still contained a template token.
     */
    protected function hadToken(?string $value): bool
    {
        return $value !== null && str_contains($value, '%');
    }

    protected function tokenNotice(): string
    {
        return 'Resolved Yoast template tokens (%%title%%, %%sitename%%, %%sep%%) in one or more '
            .'title/description values; unresolvable tokens (e.g. %%page%%) were stripped. Review imported titles.';
    }

    /**
     * The meta keys (with prefix) {@see self::mapMeta()} consumes, so the rest
     * can be reported as unmapped.
     *
     * @return array<int, string>
     */
    protected function mappedKeys(): array
    {
        $prefix = $this->metaKeyPrefix();

        return array_map(fn (string $short): string => $prefix.$short, [
            'title', 'metadesc', 'canonical',
            'meta-robots-noindex', 'meta-robots-nofollow', 'meta-robots-adv',
            'focuskw',
            'opengraph-title', 'opengraph-description', 'opengraph-image',
            'twitter-title', 'twitter-description', 'twitter-image',
        ]);
    }
}
