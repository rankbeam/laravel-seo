<?php

declare(strict_types=1);

namespace Fibonoir\LaravelSEO\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;
use Fibonoir\LaravelSEO\Models\SEO404Log;

/**
 * Logs 404 errors for monitoring and redirect creation.
 *
 * Register this as a terminable middleware (runs after response is sent).
 * This ensures logging doesn't slow down the user experience.
 *
 * ## Features
 * - Upserts: increments hit_count for existing paths
 * - Captures referrer, user agent, IP
 * - Excludes bots (configurable)
 * - Excludes asset extensions
 * - Excludes configurable paths
 *
 * ## Registration
 * Add to web middleware group:
 * ```php
 * ->withMiddleware(function (Middleware $middleware) {
 *     $middleware->web(append: [Log404Middleware::class]);
 * })
 * ```
 *
 * ## Use Cases
 * - Identify broken internal links
 * - Find external sites linking to old URLs
 * - Create redirects for frequently hit 404s
 */
class Log404Middleware
{
    /**
     * Common bot patterns to filter.
     *
     * @var array<int, string>
     */
    protected array $botPatterns = [
        'bot',
        'crawler',
        'spider',
        'slurp',
        'googlebot',
        'bingbot',
        'yandex',
        'baidu',
        'duckduckbot',
        'semrush',
        'ahrefs',
        'mj12bot',
        'dotbot',
        'petalbot',
        'bytespider',
        'gptbot',
        'claudebot',
    ];

    /**
     * Asset extensions to skip.
     *
     * @var array<int, string>
     */
    protected array $assetExtensions = [
        'js', 'css', 'map',
        'jpg', 'jpeg', 'png', 'gif', 'webp', 'svg', 'ico', 'avif',
        'woff', 'woff2', 'ttf', 'eot', 'otf',
        'mp3', 'mp4', 'webm', 'ogg', 'wav',
        'pdf', 'doc', 'docx', 'xls', 'xlsx',
        'zip', 'rar', 'tar', 'gz',
    ];

    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        return $next($request);
    }

    /**
     * Handle tasks after the response has been sent.
     *
     * This is where we log 404s to avoid affecting response time.
     */
    public function terminate(Request $request, Response $response): void
    {
        // Check if feature is enabled
        if (! config('seo.features.404_monitor', true)) {
            return;
        }

        // Only log actual 404s
        if ($response->getStatusCode() !== 404) {
            return;
        }

        // Check if this request should be logged
        if (! $this->shouldLog($request)) {
            return;
        }

        $this->logNotFound($request);
    }

    /**
     * Check if this request should be logged.
     */
    protected function shouldLog(Request $request): bool
    {
        $path = '/' . ltrim($request->path(), '/');

        // Skip excluded paths from config
        $excludePaths = config('seo.404_monitor.exclude_paths', [
            '/api/*',
            '/_debugbar/*',
            '/telescope/*',
            '/horizon/*',
            '/livewire/*',
            '/.well-known/*',
        ]);

        foreach ($excludePaths as $pattern) {
            if (fnmatch($pattern, $path)) {
                return false;
            }
        }

        // Skip asset extensions
        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        $excludeExtensions = config('seo.404_monitor.exclude_extensions', $this->assetExtensions);

        if ($extension && in_array($extension, $excludeExtensions, true)) {
            return false;
        }

        // Skip bots if configured
        if (! config('seo.404_monitor.log_bots', false)) {
            if ($this->isBot($request)) {
                return false;
            }
        }

        // Skip if path is too long (likely an attack)
        if (mb_strlen($path) > 500) {
            return false;
        }

        return true;
    }

    /**
     * Check if request is from a bot.
     */
    protected function isBot(Request $request): bool
    {
        $userAgent = strtolower($request->userAgent() ?? '');

        if (empty($userAgent)) {
            return true; // No user agent = likely a bot
        }

        foreach ($this->botPatterns as $pattern) {
            if (str_contains($userAgent, $pattern)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Log the 404 request using upsert.
     */
    protected function logNotFound(Request $request): void
    {
        try {
            $path = '/' . ltrim($request->path(), '/');
            $now = now();

            // Use database upsert for atomic operation
            // This handles race conditions properly
            DB::table('seo_404_logs')->upsert(
                [
                    'path' => $path,
                    'referrer' => $this->truncate($request->header('referer'), 500),
                    'user_agent' => $this->truncate($request->userAgent(), 500),
                    'ip' => $this->hashIp($request->ip()),
                    'hit_count' => 1,
                    'first_seen_at' => $now,
                    'last_seen_at' => $now,
                    'status' => 'new',
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
                ['path'], // Unique key
                [ // Update on conflict
                    'hit_count' => DB::raw('hit_count + 1'),
                    'last_seen_at' => $now,
                    'referrer' => $this->truncate($request->header('referer'), 500),
                    'user_agent' => $this->truncate($request->userAgent(), 500),
                    'updated_at' => $now,
                ]
            );
        } catch (\Exception $e) {
            // Don't let logging errors affect the response
            // But do log the error for debugging
            Log::warning('SEO: Failed to log 404', [
                'path' => $request->path(),
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Truncate a string to a maximum length.
     */
    protected function truncate(?string $value, int $maxLength): ?string
    {
        if ($value === null) {
            return null;
        }

        if (mb_strlen($value) <= $maxLength) {
            return $value;
        }

        return mb_substr($value, 0, $maxLength - 3) . '...';
    }

    /**
     * Hash IP address for privacy (optional).
     */
    protected function hashIp(?string $ip): ?string
    {
        if ($ip === null) {
            return null;
        }

        // If GDPR/privacy mode is enabled, hash the IP
        if (config('seo.404_monitor.hash_ip', false)) {
            return hash('sha256', $ip . config('app.key'));
        }

        return $ip;
    }
}
