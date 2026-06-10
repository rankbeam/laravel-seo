<?php

declare(strict_types=1);

namespace Fibonoir\LaravelSEO\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Fibonoir\LaravelSEO\Models\SEO404Log;
use Fibonoir\LaravelSEO\Models\SEORedirect;

/**
 * Service for managing URL redirects.
 *
 * Provides a high-level API for creating, updating, and managing
 * SEO redirects with validation, loop detection, and cache management.
 *
 * ## Usage
 * ```php
 * $manager = app(RedirectManager::class);
 *
 * // Simple redirect
 * $redirect = $manager->createFromPath('/old-page', '/new-page');
 *
 * // From 404 log
 * $redirect = $manager->createFrom404($log, '/correct-page');
 *
 * // Test a path
 * $result = $manager->testRedirect('/some-path');
 * if ($result['matched']) {
 *     echo "Would redirect to: " . $result['target'];
 * }
 *
 * // Find problems
 * $loops = $manager->findLoops();
 * ```
 */
class RedirectManager
{
    /**
     * Create a new redirect.
     *
     * @param array<string, mixed> $data
     * @throws \InvalidArgumentException If validation fails
     */
    public function create(array $data): SEORedirect
    {
        // Normalize source path
        $data['source_path'] = '/' . ltrim($data['source_path'] ?? '', '/');

        // Validate regex if applicable
        if (! empty($data['is_regex'])) {
            $this->validateRegex($data['source_path']);
        }

        // Validate no loop
        $this->validateNoLoop($data['source_path'], $data['target_url'] ?? '', $data['is_regex'] ?? false);

        // Create the redirect
        $redirect = SEORedirect::create([
            'source_path' => $data['source_path'],
            'target_url' => $data['target_url'],
            'status_code' => $data['status_code'] ?? 301,
            'is_regex' => $data['is_regex'] ?? false,
            'is_active' => $data['is_active'] ?? true,
            'preserve_query' => $data['preserve_query'] ?? true,
            'note' => $data['note'] ?? null,
            'created_by' => $data['created_by'] ?? null,
        ]);

        $this->clearCache();

        return $redirect;
    }

    /**
     * Convenience method for creating simple redirects.
     */
    public function createFromPath(
        string $sourcePath,
        string $targetUrl,
        int $statusCode = 301,
    ): SEORedirect {
        return $this->create([
            'source_path' => $sourcePath,
            'target_url' => $targetUrl,
            'status_code' => $statusCode,
            'is_regex' => false,
            'preserve_query' => true,
        ]);
    }

    /**
     * Create a redirect from a 404 log entry.
     *
     * Updates the log status and links it to the new redirect.
     */
    public function createFrom404(
        SEO404Log $log,
        string $targetUrl,
        int $statusCode = 301,
    ): SEORedirect {
        $redirect = $this->create([
            'source_path' => $log->path,
            'target_url' => $targetUrl,
            'status_code' => $statusCode,
            'note' => "Created from 404 log (hit count: {$log->hit_count})",
        ]);

        // Update the 404 log
        $log->markRedirected($redirect);

        return $redirect;
    }

    /**
     * Update an existing redirect.
     *
     * @param array<string, mixed> $data
     * @throws \InvalidArgumentException If validation fails
     */
    public function update(SEORedirect $redirect, array $data): SEORedirect
    {
        // Normalize source path if provided
        if (isset($data['source_path'])) {
            $data['source_path'] = '/' . ltrim($data['source_path'], '/');
        }

        // Validate regex if changing to regex or updating pattern
        $isRegex = $data['is_regex'] ?? $redirect->is_regex;
        $sourcePath = $data['source_path'] ?? $redirect->source_path;

        if ($isRegex) {
            $this->validateRegex($sourcePath);
        }

        // Validate no loop if paths changed
        $targetUrl = $data['target_url'] ?? $redirect->target_url;
        if (isset($data['source_path']) || isset($data['target_url'])) {
            $this->validateNoLoop($sourcePath, $targetUrl, $isRegex, $redirect->id);
        }

        // Update the redirect
        $redirect->update($data);

        $this->clearCache();

        return $redirect->fresh();
    }

    /**
     * Delete a redirect.
     *
     * Note: Linked 404 logs will have their redirect_id set to null
     * but maintain their 'redirected' status for historical purposes.
     */
    public function delete(SEORedirect $redirect): void
    {
        // Unlink 404 logs (keep their status)
        SEO404Log::where('redirect_id', $redirect->id)
            ->update(['redirect_id' => null]);

        $redirect->delete();

        $this->clearCache();
    }

    /**
     * Bulk import redirects.
     *
     * @param array<int, array{source: string, target: string, status?: int}> $redirects
     * @return array{created: int, updated: int, failed: int, errors: array<int, string>}
     */
    public function import(array $redirects): array
    {
        $result = [
            'created' => 0,
            'updated' => 0,
            'failed' => 0,
            'errors' => [],
        ];

        DB::beginTransaction();

        try {
            foreach ($redirects as $index => $data) {
                try {
                    $sourcePath = '/' . ltrim($data['source'] ?? $data['source_path'] ?? '', '/');
                    $targetUrl = $data['target'] ?? $data['target_url'] ?? '';
                    $statusCode = $data['status'] ?? $data['status_code'] ?? 301;

                    if (empty($sourcePath) || empty($targetUrl)) {
                        $result['failed']++;
                        $result['errors'][] = "Row {$index}: Missing source or target";
                        continue;
                    }

                    // Check if exists
                    $existing = SEORedirect::where('source_path', $sourcePath)->first();

                    if ($existing) {
                        $this->update($existing, [
                            'target_url' => $targetUrl,
                            'status_code' => $statusCode,
                        ]);
                        $result['updated']++;
                    } else {
                        $this->create([
                            'source_path' => $sourcePath,
                            'target_url' => $targetUrl,
                            'status_code' => $statusCode,
                        ]);
                        $result['created']++;
                    }
                } catch (\Exception $e) {
                    $result['failed']++;
                    $result['errors'][] = "Row {$index}: {$e->getMessage()}";
                }
            }

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }

        $this->clearCache();

        return $result;
    }

    /**
     * Export all redirects for backup.
     *
     * @return array<int, array<string, mixed>>
     */
    public function export(): array
    {
        return SEORedirect::all()->map(function (SEORedirect $redirect) {
            return [
                'source' => $redirect->source_path,
                'target' => $redirect->target_url,
                'status' => $redirect->status_code,
                'is_regex' => $redirect->is_regex,
                'is_active' => $redirect->is_active,
                'preserve_query' => $redirect->preserve_query,
                'note' => $redirect->note,
                'hit_count' => $redirect->hit_count,
                'created_at' => $redirect->created_at?->toIso8601String(),
            ];
        })->toArray();
    }

    /**
     * Test what would happen for a given path.
     *
     * @return array{matched: bool, redirect: ?SEORedirect, target: ?string, status_code: ?int}
     */
    public function testRedirect(string $path): array
    {
        $path = '/' . ltrim($path, '/');

        $redirects = SEORedirect::active()->orderBy('is_regex')->get();

        // Check exact matches first
        foreach ($redirects->where('is_regex', false) as $redirect) {
            if ($redirect->source_path === $path) {
                return [
                    'matched' => true,
                    'redirect' => $redirect,
                    'target' => $this->resolveTarget($redirect, $path),
                    'status_code' => $redirect->status_code,
                ];
            }
        }

        // Check regex matches
        foreach ($redirects->where('is_regex', true) as $redirect) {
            $pattern = '#^' . $redirect->source_path . '$#';
            if (@preg_match($pattern, $path, $matches)) {
                return [
                    'matched' => true,
                    'redirect' => $redirect,
                    'target' => $this->resolveTarget($redirect, $path, $matches),
                    'status_code' => $redirect->status_code,
                ];
            }
        }

        return [
            'matched' => false,
            'redirect' => null,
            'target' => null,
            'status_code' => null,
        ];
    }

    /**
     * Find redirect chains and loops.
     *
     * @return array<int, array{redirect: SEORedirect, issue: string, chain: array<int, string>}>
     */
    public function findLoops(): array
    {
        $problems = [];
        $redirects = SEORedirect::active()->get();

        foreach ($redirects as $redirect) {
            if ($redirect->is_regex) {
                // Skip regex for loop detection (too complex)
                continue;
            }

            $chain = [$redirect->source_path];
            $target = $redirect->target_url;
            $visited = [$redirect->source_path];

            // Follow the chain
            $depth = 0;
            $maxDepth = 10;

            while ($depth < $maxDepth) {
                $targetPath = parse_url($target, PHP_URL_PATH);

                if (! $targetPath) {
                    break;
                }

                $targetPath = '/' . ltrim($targetPath, '/');

                // Check for loop (back to start)
                if ($targetPath === $redirect->source_path) {
                    $problems[] = [
                        'redirect' => $redirect,
                        'issue' => 'loop',
                        'chain' => [...$chain, $targetPath],
                    ];
                    break;
                }

                // Check if already visited (loop in chain)
                if (in_array($targetPath, $visited, true)) {
                    $problems[] = [
                        'redirect' => $redirect,
                        'issue' => 'loop_in_chain',
                        'chain' => [...$chain, $targetPath],
                    ];
                    break;
                }

                // Find next redirect in chain
                $nextRedirect = $redirects
                    ->where('is_regex', false)
                    ->where('source_path', $targetPath)
                    ->first();

                if (! $nextRedirect) {
                    // Chain ends here
                    if ($depth >= 2) {
                        // Long chain (3+ hops)
                        $problems[] = [
                            'redirect' => $redirect,
                            'issue' => 'long_chain',
                            'chain' => $chain,
                        ];
                    }
                    break;
                }

                $chain[] = $targetPath;
                $visited[] = $targetPath;
                $target = $nextRedirect->target_url;
                $depth++;
            }

            if ($depth >= $maxDepth) {
                $problems[] = [
                    'redirect' => $redirect,
                    'issue' => 'max_depth_exceeded',
                    'chain' => $chain,
                ];
            }
        }

        return $problems;
    }

    /**
     * Validate that a redirect won't create a loop.
     *
     * @throws \InvalidArgumentException
     */
    protected function validateNoLoop(
        string $source,
        string $target,
        bool $isRegex,
        ?int $excludeId = null,
    ): void {
        if ($isRegex) {
            // Can't easily validate regex loops
            return;
        }

        $targetPath = parse_url($target, PHP_URL_PATH);

        if (! $targetPath) {
            return;
        }

        $targetPath = '/' . ltrim($targetPath, '/');
        $source = '/' . ltrim($source, '/');

        // Direct loop
        if ($targetPath === $source) {
            throw new \InvalidArgumentException(
                'Redirect would create a direct loop: source and target paths are the same.'
            );
        }

        // Check if target is already a redirect source (chain)
        $query = SEORedirect::where('source_path', $targetPath)
            ->where('is_regex', false);

        if ($excludeId) {
            $query->where('id', '!=', $excludeId);
        }

        $existingRedirect = $query->first();

        if ($existingRedirect) {
            // Check if it eventually leads back to source
            $visited = [$source, $targetPath];
            $current = $existingRedirect->target_url;

            for ($i = 0; $i < 10; $i++) {
                $currentPath = parse_url($current, PHP_URL_PATH);

                if (! $currentPath) {
                    break;
                }

                $currentPath = '/' . ltrim($currentPath, '/');

                if ($currentPath === $source) {
                    throw new \InvalidArgumentException(
                        'Redirect would create a loop in the redirect chain.'
                    );
                }

                if (in_array($currentPath, $visited, true)) {
                    break; // Loop but not involving our source
                }

                $visited[] = $currentPath;

                $next = SEORedirect::where('source_path', $currentPath)
                    ->where('is_regex', false)
                    ->first();

                if (! $next) {
                    break;
                }

                $current = $next->target_url;
            }
        }
    }

    /**
     * Validate regex pattern.
     *
     * @throws \InvalidArgumentException
     */
    protected function validateRegex(string $pattern): void
    {
        // Test if it's a valid regex
        $testPattern = '#^' . $pattern . '$#';

        if (@preg_match($testPattern, '') === false) {
            $error = preg_last_error_msg();
            throw new \InvalidArgumentException(
                "Invalid regex pattern: {$error}"
            );
        }
    }

    /**
     * Resolve target URL with regex replacements.
     *
     * @param array<int, string>|null $matches
     */
    protected function resolveTarget(SEORedirect $redirect, string $path, ?array $matches = null): string
    {
        if (! $redirect->is_regex || $matches === null) {
            return $redirect->target_url;
        }

        $pattern = '#^' . $redirect->source_path . '$#';

        return preg_replace($pattern, $redirect->target_url, $path) ?? $redirect->target_url;
    }

    /**
     * Clear the redirects cache.
     */
    protected function clearCache(): void
    {
        $cacheKey = config('seo.cache.prefix', 'seo_') . 'redirects';
        Cache::store(config('seo.cache.store'))->forget($cacheKey);
    }
}
