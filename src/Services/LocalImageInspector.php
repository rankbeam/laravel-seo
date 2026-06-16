<?php

declare(strict_types=1);

namespace Rankbeam\Seo\Services;

use Illuminate\Support\Facades\Storage;

/**
 * Resolves and measures LOCAL images by their on-disk pixel dimensions.
 *
 * This is the single source of truth for "how big is this image, if we can
 * read it locally?" shared by the editorial preview / warnings
 * ({@see SEOWarningEvaluator}) and the computed-image "best" selection
 * strategy ({@see SEOComputedBuilder}). Keeping one resolver means the size a
 * preview flags as too-small is exactly the one the selector skips.
 *
 * Core never fetches a remote image (SSRF / latency / cache): only files that
 * resolve to a local path are measured. URLs under `/storage/` map to the
 * public disk; other rooted/relative paths map into `public/`; absolute URLs
 * are resolved only when their host matches `app.url`.
 */
class LocalImageInspector
{
    /**
     * Detect the dimensions of a locally resolvable image.
     *
     * @return array{width: int, height: int}|null Null when the image is
     *                                              remote, missing, or unreadable
     */
    public function dimensions(string $image): ?array
    {
        $path = $this->resolveLocalPath($image);

        if ($path === null || ! is_file($path)) {
            return null;
        }

        $size = @getimagesize($path);

        if (! is_array($size)) {
            return null;
        }

        return [
            'width' => (int) ($size[0] ?? 0),
            'height' => (int) ($size[1] ?? 0),
        ];
    }

    /**
     * Map an image URL/path to a local filesystem path, if possible.
     *
     * Absolute URLs are only resolved when their host matches app.url
     * (no remote fetches). URLs under /storage/ map to the public disk;
     * other paths map into public/.
     */
    public function resolveLocalPath(string $image): ?string
    {
        if (str_starts_with($image, 'http://') || str_starts_with($image, 'https://')) {
            $parsed = parse_url($image);
            $appParsed = parse_url((string) config('app.url'));

            if (($parsed['host'] ?? null) !== ($appParsed['host'] ?? null)) {
                return null;
            }

            $path = $parsed['path'] ?? null;

            if (! is_string($path) || $path === '') {
                return null;
            }

            if (str_starts_with($path, '/storage/')) {
                return Storage::disk('public')->path(substr($path, strlen('/storage/')));
            }

            return public_path(ltrim($path, '/'));
        }

        if (str_starts_with($image, '/')) {
            if (str_starts_with($image, '/storage/')) {
                return Storage::disk('public')->path(substr($image, strlen('/storage/')));
            }

            return public_path(ltrim($image, '/'));
        }

        return Storage::disk('public')->path($image);
    }
}
