<?php

declare(strict_types=1);

namespace Rankbeam\Seo\Services;

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
                'message' => __('seo::seo.warnings.title_too_long', [
                    'length' => mb_strlen($title),
                    'max' => self::TITLE_MAX_LENGTH,
                ]),
            ];
        }

        if ($manualTitle === null || trim($manualTitle) === '') {
            $warnings[] = [
                'level' => 'info',
                'key' => 'title_is_fallback',
                'message' => __('seo::seo.warnings.title_is_fallback'),
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
                'message' => __('seo::seo.warnings.description_too_long', [
                    'length' => mb_strlen($description),
                    'max' => self::DESCRIPTION_MAX_LENGTH,
                ]),
            ];
        }

        if ($manualDescription === null || trim($manualDescription) === '') {
            $warnings[] = [
                'level' => 'info',
                'key' => 'description_is_fallback',
                'message' => __('seo::seo.warnings.description_is_fallback'),
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
                'message' => __('seo::seo.warnings.no_image'),
            ];

            return $warnings;
        }

        if ($manualImage === null || trim($manualImage) === '') {
            $warnings[] = [
                'level' => 'info',
                'key' => 'image_is_fallback',
                'message' => __('seo::seo.warnings.image_is_fallback'),
            ];
        }

        $dimensions = $this->detectDimensions($effectiveImage);

        if ($dimensions !== null) {
            if ($dimensions['width'] < self::MIN_SOCIAL_IMAGE_WIDTH || $dimensions['height'] < self::MIN_SOCIAL_IMAGE_HEIGHT) {
                $warnings[] = [
                    'level' => 'danger',
                    'key' => 'image_too_small',
                    'message' => __('seo::seo.warnings.image_too_small', [
                        'width' => $dimensions['width'],
                        'height' => $dimensions['height'],
                        'min_width' => self::MIN_SOCIAL_IMAGE_WIDTH,
                        'min_height' => self::MIN_SOCIAL_IMAGE_HEIGHT,
                    ]),
                ];
            } elseif ($dimensions['width'] < self::IDEAL_SOCIAL_IMAGE_WIDTH || $dimensions['height'] < self::IDEAL_SOCIAL_IMAGE_HEIGHT) {
                $warnings[] = [
                    'level' => 'info',
                    'key' => 'image_not_ideal',
                    'message' => __('seo::seo.warnings.image_not_ideal', [
                        'width' => $dimensions['width'],
                        'height' => $dimensions['height'],
                        'ideal_width' => self::IDEAL_SOCIAL_IMAGE_WIDTH,
                        'ideal_height' => self::IDEAL_SOCIAL_IMAGE_HEIGHT,
                    ]),
                ];
            }
        }

        return $warnings;
    }

    /**
     * Detect the dimensions of a locally resolvable image.
     *
     * Delegates to the shared {@see LocalImageInspector} so the preview and
     * the computed-image "best" selection measure images identically.
     *
     * @return array{width: int, height: int}|null Null when the image is
     *                                             remote, missing, or unreadable
     */
    protected function detectDimensions(string $image): ?array
    {
        return (new LocalImageInspector)->dimensions($image);
    }
}
