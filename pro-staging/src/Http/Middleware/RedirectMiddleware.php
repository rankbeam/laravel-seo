<?php

declare(strict_types=1);

namespace Fibonoir\LaravelSEO\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;
use Fibonoir\LaravelSEO\Jobs\IncrementRedirectHitJob;
use Fibonoir\LaravelSEO\Models\SEORedirect;

/**
 * Handles URL redirects early in the request lifecycle.
 *
 * Register this middleware EARLY in the stack (before routes)
 * to intercept requests before they hit non-existent routes.
 *
 * ## Features
 * - Exact path matching
 * - Regex pattern matching with capture groups
 * - Query string preservation
 * - Redirect loop prevention
 * - Hit tracking (queued for performance)
 * - Cache for performance
 *
 * ## Registration
 * Add to `bootstrap/app.php` or middleware groups:
 * ```php
 * ->withMiddleware(function (Middleware $middleware) {
 *     $middleware->prepend(RedirectMiddleware::class);
 * })
 * ```
 *
 * ## Regex Examples
 * - Source: `^/old-blog/(.*)$` → Target: `/blog/$1`
 * - Source: `^/products/(\d+)/(.*)$` → Target: `/shop/$1-$2`
 */
class RedirectMiddleware
{
    /**
     * Cache key for redirects.
     */
    protected const CACHE_KEY = 'seo_redirects';

    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Check if redirects feature is enabled
        if (! config('seo.features.redirects', true)) {
            return $next($request);
        }

        // Normalize path with leading slash
        $path = '/' . ltrim($request->path(), '/');

        // Skip excluded paths
        if ($this->shouldSkip($path)) {
            return $next($request);
        }

        // Find matching redirect
        $result = $this->findMatchingRedirect($path);

        if ($result) {
            [$redirect, $matches] = $result;

            return $this->performRedirect($redirect, $request, $matches);
        }

        return $next($request);
    }

    /**
     * Check if path should be skipped.
     */
    protected function shouldSkip(string $path): bool
    {
        $excludePaths = config('seo.redirects.exclude_paths', [
            '/api/*',
            '/_debugbar/*',
            '/telescope/*',
            '/horizon/*',
        ]);

        foreach ($excludePaths as $pattern) {
            if (fnmatch($pattern, $path)) {
                return true;
            }
        }

        // Skip common asset extensions
        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        $skipExtensions = ['js', 'css', 'map', 'ico', 'woff', 'woff2', 'ttf', 'eot'];

        if (in_array($extension, $skipExtensions, true)) {
            return true;
        }

        return false;
    }

    /**
     * Find a matching redirect for the path.
     *
     * @return array{0: SEORedirect, 1: array<int, string>|null}|null
     */
    protected function findMatchingRedirect(string $path): ?array
    {
        $redirects = $this->getRedirects();

        // Check exact matches first (faster, non-regex)
        foreach ($redirects->where('is_regex', false) as $redirect) {
            $match = $this->matchPath($path, $redirect);
            if ($match !== false) {
                return [$redirect, is_array($match) ? $match : null];
            }
        }

        // Check regex patterns
        foreach ($redirects->where('is_regex', true) as $redirect) {
            $match = $this->matchPath($path, $redirect);
            if ($match !== false) {
                return [$redirect, is_array($match) ? $match : null];
            }
        }

        return null;
    }

    /**
     * Match a path against a redirect rule.
     *
     * @return bool|array<int, string> False if no match, true for exact match, array for regex captures
     */
    protected function matchPath(string $path, SEORedirect $redirect): bool|array
    {
        if ($redirect->is_regex) {
            // Regex matching with capture groups
            $pattern = '#^' . $redirect->source_path . '$#';

            if (@preg_match($pattern, $path, $matches)) {
                // Return capture groups (excluding full match at index 0 for replacements)
                return $matches;
            }

            return false;
        }

        // Exact match (case-sensitive)
        return $redirect->source_path === $path;
    }

    /**
     * Get cached redirects.
     *
     * @return Collection<int, SEORedirect>
     */
    protected function getRedirects(): Collection
    {
        // Check if caching is enabled
        if (! config('seo.redirects.cache_enabled', true)) {
            return SEORedirect::active()->orderBy('is_regex')->get();
        }

        $cacheKey = config('seo.cache.prefix', 'seo_') . 'redirects';
        $ttl = config('seo.redirects.cache_ttl', 3600);

        return Cache::store(config('seo.cache.store'))
            ->remember($cacheKey, $ttl, function () {
                // Order by is_regex so exact matches are checked first
                return SEORedirect::active()->orderBy('is_regex')->get();
            });
    }

    /**
     * Perform the redirect.
     *
     * @param array<int, string>|null $matches Regex capture groups
     */
    protected function performRedirect(SEORedirect $redirect, Request $request, ?array $matches): Response
    {
        $targetUrl = $this->buildTargetUrl($redirect, $request, $matches);

        // Prevent redirect loops
        if ($this->preventLoop($targetUrl, $request->path())) {
            return response('Redirect loop detected', 500);
        }

        // Increment hit count asynchronously (queue job)
        if (config('seo.redirects.log_hits', true)) {
            IncrementRedirectHitJob::dispatch($redirect->id);
        }

        // Handle 410 Gone (no redirect, just gone)
        if ($redirect->status_code === 410) {
            abort(410, 'Gone');
        }

        return redirect($targetUrl, $redirect->status_code);
    }

    /**
     * Build the target URL, handling regex captures and query strings.
     *
     * @param array<int, string>|null $matches Regex capture groups
     */
    protected function buildTargetUrl(SEORedirect $redirect, Request $request, ?array $matches): string
    {
        $targetUrl = $redirect->target_url;

        // Handle regex capture group replacements ($1, $2, etc.)
        if ($redirect->is_regex && $matches !== null) {
            // Use preg_replace for proper back-reference handling
            $sourcePath = '/' . ltrim($request->path(), '/');
            $pattern = '#^' . $redirect->source_path . '$#';

            $targetUrl = preg_replace($pattern, $redirect->target_url, $sourcePath) ?? $targetUrl;
        }

        // Handle query string preservation
        if ($redirect->preserve_query && $request->getQueryString()) {
            $separator = str_contains($targetUrl, '?') ? '&' : '?';
            $targetUrl .= $separator . $request->getQueryString();
        }

        return $targetUrl;
    }

    /**
     * Check if redirect would cause a loop.
     */
    protected function preventLoop(string $target, string $source): bool
    {
        // Normalize paths for comparison
        $targetPath = parse_url($target, PHP_URL_PATH) ?? '';
        $sourcePath = '/' . ltrim($source, '/');

        $normalizedTarget = rtrim($targetPath, '/');
        $normalizedSource = rtrim($sourcePath, '/');

        // Same path = loop
        if ($normalizedTarget === $normalizedSource) {
            return true;
        }

        // Empty target path (would redirect to root)
        if (empty($normalizedTarget)) {
            return false;
        }

        // Check if target would also be redirected (chain detection)
        // Only check one level to avoid performance issues
        $redirects = $this->getRedirects();
        foreach ($redirects as $redirect) {
            if (! $redirect->is_regex && $redirect->source_path === $normalizedTarget) {
                // Target is also a redirect source - potential chain
                // Allow it but log warning in production
                return false;
            }
        }

        return false;
    }

    /**
     * Clear the redirects cache.
     *
     * Called when redirects are created/updated/deleted.
     */
    public static function clearCache(): void
    {
        $cacheKey = config('seo.cache.prefix', 'seo_') . 'redirects';
        Cache::store(config('seo.cache.store'))->forget($cacheKey);
    }
}
