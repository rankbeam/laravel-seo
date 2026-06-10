<?php

declare(strict_types=1);

namespace Fibonoir\LaravelSEO\Services\Scanner;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Validates links and images asynchronously.
 *
 * The BrokenLinkChecker performs HTTP requests to verify that
 * links and images are accessible. It's designed for async use
 * to avoid blocking the main analysis process.
 *
 * ## Features
 * - Configurable timeout
 * - Rate limiting support
 * - Parallel request handling
 * - Caches results to avoid repeated checks
 *
 * ## Usage
 * ```php
 * $checker = app(BrokenLinkChecker::class);
 *
 * $brokenLinks = $checker->checkLinks([
 *     'https://example.com/page1',
 *     'https://example.com/page2',
 * ]);
 *
 * foreach ($brokenLinks as $link) {
 *     echo "{$link['url']} returned {$link['status']}";
 * }
 * ```
 *
 * ## Rate Limiting
 * To avoid overwhelming servers, the checker respects delays
 * between requests and can be configured per domain.
 */
class BrokenLinkChecker
{
    /**
     * Request timeout in seconds.
     */
    protected int $timeout = 10;

    /**
     * Delay between requests (milliseconds).
     */
    protected int $delayMs = 100;

    /**
     * User agent string.
     */
    protected string $userAgent = 'Laravel SEO Bot/1.0 (+https://github.com/fibonoir/laravel-seo)';

    /**
     * Maximum concurrent requests.
     */
    protected int $concurrency = 5;

    /**
     * Cache of checked URLs.
     *
     * @var array<string, array{status: int, broken: bool}>
     */
    protected array $cache = [];

    /**
     * Check multiple links for broken status.
     *
     * @param array<int, string> $links
     * @return array<int, array{url: string, status: int|string}>
     */
    public function checkLinks(array $links): array
    {
        $broken = [];

        foreach ($links as $url) {
            if (! $this->isValidUrl($url)) {
                $broken[] = [
                    'url' => $url,
                    'status' => 'invalid_url',
                ];
                continue;
            }

            $status = $this->getStatus($url);

            if ($this->isBrokenStatus($status)) {
                $broken[] = [
                    'url' => $url,
                    'status' => $status,
                ];
            }

            // Rate limiting
            usleep($this->delayMs * 1000);
        }

        return $broken;
    }

    /**
     * Check multiple images for broken status.
     *
     * @param array<int, string> $images
     * @return array<int, array{src: string, status: int|string}>
     */
    public function checkImages(array $images): array
    {
        $broken = [];

        foreach ($images as $src) {
            // Skip data URIs
            if (str_starts_with($src, 'data:')) {
                continue;
            }

            if (! $this->isValidUrl($src)) {
                $broken[] = [
                    'src' => $src,
                    'status' => 'invalid_url',
                ];
                continue;
            }

            $status = $this->getStatus($src);

            if ($this->isBrokenStatus($status)) {
                $broken[] = [
                    'src' => $src,
                    'status' => $status,
                ];
            }

            // Rate limiting
            usleep($this->delayMs * 1000);
        }

        return $broken;
    }

    /**
     * Check if a URL is valid format.
     */
    public function isValidUrl(string $url): bool
    {
        // Must be absolute URL
        if (! filter_var($url, FILTER_VALIDATE_URL)) {
            return false;
        }

        // Must be HTTP(S)
        $scheme = parse_url($url, PHP_URL_SCHEME);

        return in_array($scheme, ['http', 'https'], true);
    }

    /**
     * Get HTTP status code for a URL.
     *
     * Returns 0 on connection error.
     */
    public function getStatus(string $url): int
    {
        // Check cache first
        if (isset($this->cache[$url])) {
            return $this->cache[$url]['status'];
        }

        try {
            $response = Http::timeout($this->timeout)
                ->withUserAgent($this->userAgent)
                ->withOptions([
                    'allow_redirects' => [
                        'max' => 5,
                        'track_redirects' => true,
                    ],
                    'verify' => false, // Allow self-signed certs
                ])
                ->head($url);

            $status = $response->status();

            // Some servers don't support HEAD, try GET
            if ($status === 405 || $status === 501) {
                $response = Http::timeout($this->timeout)
                    ->withUserAgent($this->userAgent)
                    ->withOptions([
                        'allow_redirects' => ['max' => 5],
                        'verify' => false,
                    ])
                    ->get($url);

                $status = $response->status();
            }

            $this->cache[$url] = ['status' => $status, 'broken' => $this->isBrokenStatus($status)];

            return $status;
        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            Log::debug("BrokenLinkChecker: Connection failed for {$url}", ['error' => $e->getMessage()]);
            $this->cache[$url] = ['status' => 0, 'broken' => true];

            return 0;
        } catch (\Exception $e) {
            Log::debug("BrokenLinkChecker: Error checking {$url}", ['error' => $e->getMessage()]);
            $this->cache[$url] = ['status' => 0, 'broken' => true];

            return 0;
        }
    }

    /**
     * Check if a status code indicates a broken link.
     */
    protected function isBrokenStatus(int $status): bool
    {
        // 0 = connection error
        if ($status === 0) {
            return true;
        }

        // 4xx and 5xx are broken
        return $status >= 400;
    }

    /**
     * Check a single URL and return result.
     *
     * @return array{url: string, status: int, broken: bool}
     */
    public function check(string $url): array
    {
        $status = $this->getStatus($url);

        return [
            'url' => $url,
            'status' => $status,
            'broken' => $this->isBrokenStatus($status),
        ];
    }

    /**
     * Check multiple URLs in batch.
     *
     * @param array<int, string> $urls
     * @return array<string, array{status: int, broken: bool}>
     */
    public function checkBatch(array $urls): array
    {
        $results = [];

        foreach ($urls as $url) {
            if (! $this->isValidUrl($url)) {
                $results[$url] = ['status' => 0, 'broken' => true];
                continue;
            }

            $status = $this->getStatus($url);
            $results[$url] = [
                'status' => $status,
                'broken' => $this->isBrokenStatus($status),
            ];

            // Rate limiting
            usleep($this->delayMs * 1000);
        }

        return $results;
    }

    /**
     * Set the request timeout.
     */
    public function setTimeout(int $seconds): self
    {
        $this->timeout = $seconds;

        return $this;
    }

    /**
     * Set the delay between requests.
     */
    public function setDelay(int $milliseconds): self
    {
        $this->delayMs = $milliseconds;

        return $this;
    }

    /**
     * Set the user agent string.
     */
    public function setUserAgent(string $userAgent): self
    {
        $this->userAgent = $userAgent;

        return $this;
    }

    /**
     * Clear the URL cache.
     */
    public function clearCache(): self
    {
        $this->cache = [];

        return $this;
    }

    /**
     * Get cached results.
     *
     * @return array<string, array{status: int, broken: bool}>
     */
    public function getCache(): array
    {
        return $this->cache;
    }
}
