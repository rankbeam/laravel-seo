<?php

declare(strict_types=1);

namespace Rankbeam\Seo\Services;

use Illuminate\Support\Facades\Storage;
use Rankbeam\Seo\Data\SEOData;

/**
 * Evaluates editorial SEO warnings for resolved SEO data.
 *
 * Produces warning entries that admin UIs can surface next to SEO fields,
 * encoding production-proven thresholds:
 *
 * - Title: warn above 60 characters (Google truncates around there);
 *   info when no manual title is set (fallback in use).
 * - Description: warn above 160 characters; info when auto-generated.
 * - Social image: danger when missing entirely; info when falling back to
 *   a content image; danger below 200x200 px (social platforms reject
 *   smaller images); info below the 1200x630 px ideal.
 *
 * Image dimensions are probed only for local files (public disk via
 * /storage/ URLs, or files under public/). Remote images are not fetched.
 *
 * Each warning is array{level: 'danger'|'warning'|'info', key: string,
 * message: string}. Keys are stable identifiers (title_too_long,
 * description_is_fallback, image_too_small, ...) suitable for filtering
 * or translation lookups.
 */
class SEOWarningEvaluator
{
    public const TITLE_MAX_LENGTH = 60;

    public const DESCRIPTION_MAX_LENGTH = 160;

    public const MIN_SOCIAL_IMAGE_WIDTH = 200;

    public const MIN_SOCIAL_IMAGE_HEIGHT = 200;

    public const IDEAL_SOCIAL_IMAGE_WIDTH = 1200;

    public const IDEAL_SOCIAL_IMAGE_HEIGHT = 630;

    /**
     * Evaluate all warnings for resolved SEO data.
     *
     * The $manual SEOData carries only the explicitly entered values (e.g.
     * from the seo_meta record) so fallback usage can be distinguished
     * from manual input.
     *
     * @return array<int, array{level: string, key: string, message: string}>
     */
    public function evaluate(SEOData $resolved, ?SEOData $manual = null): array
    {
        return array_merge(
            $this->evaluateTitle($resolved->title, $manual?->title),
            $this->evaluateDescription($resolved->description, $manual?->description),
            $this->evaluateImage($resolved->ogImage, $manual?->ogImage),
        );
    }

    /**
     * Evaluate title-related warnings from raw values.
     *
     * @return array<int, array{level: string, key: string, message: string}>
     */
    public function evaluateTitle(?string $effectiveTitle, ?string $manualTitle): array
    {
        $warnings = [];

        $title = $effectiveTitle ?? '';

        if (mb_strlen($title) > self::TITLE_MAX_LENGTH) {
            $warnings[] = [
                'level' => 'warning',
                'key' => 'title_too_long',
                'message' => sprintf(
                    'The title is %d characters long (recommended max: %d). It may be truncated on Google.',
                    mb_strlen($title),
                    self::TITLE_MAX_LENGTH,
                ),
            ];
        }

        if ($manualTitle === null || trim($manualTitle) === '') {
            $warnings[] = [
                'level' => 'info',
                'key' => 'title_is_fallback',
                'message' => 'No SEO title set — the content title will be used as a fallback.',
            ];
        }

        return $warnings;
    }

    /**
     * Evaluate description-related warnings from raw values.
     *
     * @return array<int, array{level: string, key: string, message: string}>
     */
    public function evaluateDescription(?string $effectiveDescription, ?string $manualDescription): array
    {
        $warnings = [];

        $description = $effectiveDescription ?? '';

        if (mb_strlen($description) > self::DESCRIPTION_MAX_LENGTH) {
            $warnings[] = [
                'level' => 'warning',
                'key' => 'description_too_long',
                'message' => sprintf(
                    'The description is %d characters long (recommended max: %d). It may be truncated.',
                    mb_strlen($description),
                    self::DESCRIPTION_MAX_LENGTH,
                ),
            ];
        }

        if ($manualDescription === null || trim($manualDescription) === '') {
            $warnings[] = [
                'level' => 'info',
                'key' => 'description_is_fallback',
                'message' => 'No SEO description set — one will be generated automatically from the content.',
            ];
        }

        return $warnings;
    }

    /**
     * Evaluate social-image warnings, probing local files for dimensions.
     *
     * @return array<int, array{level: string, key: string, message: string}>
     */
    public function evaluateImage(?string $effectiveImage, ?string $manualImage): array
    {
        $warnings = [];

        if ($effectiveImage === null || trim($effectiveImage) === '') {
            $warnings[] = [
                'level' => 'danger',
                'key' => 'no_image',
                'message' => 'No image available for social previews. Add an SEO image or a content image.',
            ];

            return $warnings;
        }

        if ($manualImage === null || trim($manualImage) === '') {
            $warnings[] = [
                'level' => 'info',
                'key' => 'image_is_fallback',
                'message' => 'No specific SEO image — the content image will be used as a fallback.',
            ];
        }

        $dimensions = $this->detectDimensions($effectiveImage);

        if ($dimensions !== null) {
            if ($dimensions['width'] < self::MIN_SOCIAL_IMAGE_WIDTH || $dimensions['height'] < self::MIN_SOCIAL_IMAGE_HEIGHT) {
                $warnings[] = [
                    'level' => 'danger',
                    'key' => 'image_too_small',
                    'message' => sprintf(
                        'Image too small (%dx%d). Social platforms require at least %dx%d px.',
                        $dimensions['width'],
                        $dimensions['height'],
                        self::MIN_SOCIAL_IMAGE_WIDTH,
                        self::MIN_SOCIAL_IMAGE_HEIGHT,
                    ),
                ];
            } elseif ($dimensions['width'] < self::IDEAL_SOCIAL_IMAGE_WIDTH || $dimensions['height'] < self::IDEAL_SOCIAL_IMAGE_HEIGHT) {
                $warnings[] = [
                    'level' => 'info',
                    'key' => 'image_not_ideal',
                    'message' => sprintf(
                        'Image is %dx%d px. The ideal size for social platforms is %dx%d px.',
                        $dimensions['width'],
                        $dimensions['height'],
                        self::IDEAL_SOCIAL_IMAGE_WIDTH,
                        self::IDEAL_SOCIAL_IMAGE_HEIGHT,
                    ),
                ];
            }
        }

        return $warnings;
    }

    /**
     * Detect the dimensions of a locally resolvable image.
     *
     * @return array{width: int, height: int}|null Null when the image is
     *         remote, missing, or unreadable
     */
    protected function detectDimensions(string $image): ?array
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
    protected function resolveLocalPath(string $image): ?string
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
