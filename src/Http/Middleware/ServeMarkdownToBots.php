<?php

declare(strict_types=1);

namespace Rankbeam\Seo\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Rankbeam\Seo\AiCrawlers\AiCrawlerRegistry;
use Rankbeam\Seo\Services\Markdown\MarkdownRegistry;
use Symfony\Component\HttpFoundation\Response;

/**
 * Content negotiation that serves clean markdown to AI crawlers.
 *
 * After the normal response is produced, this swaps in a markdown representation
 * of the page WHEN both: the request asked for markdown — an explicit
 * `Accept: text/markdown`, a `?format=md` query, or (opt-in) a known AI crawler
 * by user-agent — AND a markdown source resolves for the route
 * ({@see MarkdownRegistry}). Otherwise the original response passes through
 * untouched, so a browser (or any request without markdown) is never affected.
 *
 * Only successful HTML responses are ever replaced; JSON, redirects, downloads,
 * and error responses are left alone. Registration is gated on
 * `seo.markdown_for_bots.enabled` (off by default), so there is zero footprint
 * until the feature is opted into.
 */
class ServeMarkdownToBots
{
    public function __construct(
        protected MarkdownRegistry $registry,
        protected AiCrawlerRegistry $crawlers,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if (! config('seo.markdown_for_bots.enabled', false)) {
            return $response;
        }

        if (! $this->wantsMarkdown($request) || ! $this->isReplaceableHtml($response)) {
            return $response;
        }

        $markdown = $this->registry->resolveForRequest($request);

        if ($markdown === null || trim($markdown) === '') {
            return $response;
        }

        $response->setContent($markdown);
        $response->headers->set('Content-Type', 'text/markdown; charset=UTF-8');
        $response->headers->set('Vary', 'Accept', false);

        return $response;
    }

    /**
     * Whether the request is asking for markdown.
     */
    protected function wantsMarkdown(Request $request): bool
    {
        $param = (string) config('seo.markdown_for_bots.query_param', 'format');
        $value = (string) config('seo.markdown_for_bots.query_value', 'md');

        if ($param !== '' && (string) $request->query($param) === $value) {
            return true;
        }

        // Explicit text/markdown in Accept — NOT matched via a `*/*` wildcard,
        // so an ordinary browser is never served markdown.
        if (str_contains(strtolower((string) $request->header('Accept', '')), 'text/markdown')) {
            return true;
        }

        if (config('seo.markdown_for_bots.serve_to_known_bots', false)
            && $this->crawlers->match($request->userAgent()) !== null) {
            return true;
        }

        return false;
    }

    /**
     * Only a successful, HTML response is eligible — never JSON, a redirect, a
     * download, a streamed response, or an error page.
     */
    protected function isReplaceableHtml(Response $response): bool
    {
        if ($response->getStatusCode() < 200 || $response->getStatusCode() >= 300) {
            return false;
        }

        if (! $response instanceof \Illuminate\Http\Response) {
            return false;
        }

        return str_contains(strtolower((string) $response->headers->get('Content-Type', 'text/html')), 'text/html');
    }
}
