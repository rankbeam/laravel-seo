<?php

declare(strict_types=1);

namespace Rankbeam\Seo\Services;

use Rankbeam\Seo\Services\RobotsTxt\RobotsTxtBuilder;

/**
 * The env-based indexing guard — a non-production safety net.
 *
 * A staging or local copy of a site leaking into a search index is one of the
 * most common and most damaging SEO mistakes (duplicate content, a private
 * environment in Google, weeks of cleanup). This guard makes it structurally
 * hard: when the app runs in an environment that is NOT in the allowed list,
 * it is "active", and three collaborators react to that one signal —
 *
 *  - {@see SEOResolver} forces `noindex,nofollow` on every resolved page,
 *    ABOVE the whole precedence chain (it overrides even an explicit per-page
 *    robots value — a staging DB is usually a production clone, so a stored
 *    `index,follow` must still be held back);
 *  - {@see RobotsTxtBuilder} emits a
 *    disallow-all robots.txt / ai.txt;
 *  - `seo:audit` prints a prominent banner.
 *
 * This class is the SINGLE source of truth for that decision, so the three
 * surfaces can never disagree about whether the guard is on. It reads config
 * live (no cached state), so it is safe to resolve as a shared singleton.
 *
 * On production — the default sole allowed environment — {@see active()}
 * returns false and every collaborator is inert: zero changed output.
 *
 * @see config/seo.php `indexing_guard`
 */
class IndexingGuard
{
    /**
     * The robots directive the guard forces. `noindex` keeps the page out of
     * the index; `nofollow` stops crawlers treating a leaked staging page as a
     * link source.
     */
    public const DIRECTIVE = 'noindex,nofollow';

    /**
     * Whether the guard is armed AND the current environment is not allowed to
     * index — i.e. whether the collaborators should act.
     *
     * A disabled guard is never active. An armed guard is active in every
     * environment that is not on the allowed list.
     */
    public function active(): bool
    {
        if (! $this->enabled()) {
            return false;
        }

        return ! $this->environmentIsAllowed();
    }

    /**
     * Whether the guard master switch is on (`seo.indexing_guard.enabled`).
     *
     * OFF by default: the guard changes what a non-production environment
     * renders, so — like the resolver's `blank_is_unset` and the generated-OG
     * -image feature — it ships disabled and is a one-line opt-in
     * (SEO_INDEXING_GUARD=true). Strongly recommended; see /guide/indexing-guard.
     */
    public function enabled(): bool
    {
        return (bool) config('seo.indexing_guard.enabled', false);
    }

    /**
     * Whether the CURRENT environment is one of the allowed-to-index
     * environments.
     *
     * Matching is done with the framework's own environment() (Str::is under
     * the hood), so wildcard patterns work: `prod*` matches `production` and
     * `prod-eu`. An EMPTY allowed list means no environment may index — the
     * fail-safe direction — so this returns false and the guard is active
     * everywhere.
     */
    public function environmentIsAllowed(): bool
    {
        $allowed = $this->allowedEnvironments();

        if ($allowed === []) {
            return false;
        }

        return app()->environment($allowed);
    }

    /**
     * The environments allowed to be indexed, normalized to a clean list of
     * non-empty strings. Defaults to `['production']`.
     *
     * @return array<int, string>
     */
    public function allowedEnvironments(): array
    {
        /** @var mixed $configured */
        $configured = config('seo.indexing_guard.allowed_environments', ['production']);

        $list = [];

        foreach ((array) $configured as $environment) {
            if (is_string($environment) && trim($environment) !== '') {
                $list[] = trim($environment);
            }
        }

        return array_values(array_unique($list));
    }

    /**
     * The current application environment name (for the audit banner and the
     * robots.txt comment).
     */
    public function currentEnvironment(): string
    {
        return (string) app()->environment();
    }
}
