<?php

declare(strict_types=1);

namespace Rankbeam\Seo\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Rankbeam\Seo\SEOServiceProvider;
use Rankbeam\Seo\Services\IndexingGuard;
use Rankbeam\Seo\Services\SEOResolver;
use Symfony\Component\HttpFoundation\Response;

/**
 * Send `X-Robots-Tag: noindex,nofollow` on every response while the
 * non-production indexing guard is active.
 *
 * The guard already forces a `<meta name="robots">` on rendered HTML pages (via
 * {@see SEOResolver}), but a meta tag only reaches a
 * crawler that parses HTML. PDFs, RSS/Atom feeds, images and any other
 * non-HTML response served THROUGH Laravel carry no meta tag — the HTTP header
 * is the only noindex signal a crawler gets for them. This middleware closes
 * that gap so a leaked staging environment is held out of the index whatever
 * the content type.
 *
 * It mirrors the resolver's meta exactly ({@see IndexingGuard::DIRECTIVE}), so
 * the header and the tag can never disagree, and it is set unconditionally
 * while the guard is active — the guard is a one-directional floor a stored or
 * app-set value must not punch through, the same inversion the resolver applies
 * to the meta.
 *
 * On production (or with the guard disabled) {@see IndexingGuard::active()} is
 * false and the response passes through untouched — a single cheap config-read
 * per request, nothing else. Registration is itself gated on the guard being
 * enabled (see {@see SEOServiceProvider::registerMiddleware()}),
 * so a package without the guard armed adds no middleware at all.
 *
 * NOTE: a static file the web server returns directly from `public/` never
 * enters PHP and so cannot receive this header — protect those at the edge
 * (web-server config / CDN). This covers everything routed through the app.
 */
class IndexingGuardHeader
{
    public function __construct(protected IndexingGuard $guard) {}

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if ($this->guard->active()) {
            $response->headers->set('X-Robots-Tag', IndexingGuard::DIRECTIVE);
        }

        return $response;
    }
}
