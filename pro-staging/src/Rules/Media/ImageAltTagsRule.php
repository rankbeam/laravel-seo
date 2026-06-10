<?php

declare(strict_types=1);

namespace Fibonoir\LaravelSEO\Rules\Media;

use Fibonoir\LaravelSEO\Contracts\RuleInterface;
use Fibonoir\LaravelSEO\Data\AnalysisContext;
use Fibonoir\LaravelSEO\Data\RuleResult;

/**
 * Checks if all images in the content have alt attributes.
 *
 * Alt text is essential for:
 * - Accessibility (screen readers)
 * - SEO (search engines understand image content)
 * - Fallback when images don't load
 *
 * ## Exceptions
 * - Tiny images (<5px) - likely tracking pixels
 * - SVG images - often decorative
 * - Images with role="presentation" - explicitly decorative
 *
 * ## Scoring
 * - **Pass:** All images have alt text
 * - **Fail:** Any image missing alt text
 *
 * ## Best Practices
 * - Be descriptive but concise
 * - Include keywords naturally when relevant
 * - Use empty alt="" for purely decorative images
 * - Don't start with "Image of..." or "Picture of..."
 */
class ImageAltTagsRule implements RuleInterface
{
    /**
     * Minimum dimension to consider an image (filters tracking pixels).
     */
    protected const MIN_DIMENSION = 5;

    public function getId(): string
    {
        return 'image_alt_tags';
    }

    public function getName(): string
    {
        return 'Image Alt Tags';
    }

    public function getWeight(): int
    {
        return 10;
    }

    public function getCategory(): string
    {
        return 'media';
    }

    public function analyze(AnalysisContext $context): RuleResult
    {
        // Parse images from HTML
        $images = $this->extractImages($context->htmlContent);

        if (empty($images)) {
            return RuleResult::skip(
                $this->getId(),
                'No images found in content.'
            );
        }

        // Check each image for alt attribute
        $missingAlt = [];
        $checkedCount = 0;

        foreach ($images as $image) {
            // Skip tiny images (tracking pixels)
            if ($this->isTinyImage($image)) {
                continue;
            }

            // Skip SVGs (often decorative)
            if ($this->isSvg($image)) {
                continue;
            }

            // Skip explicitly decorative images
            if ($this->isDecorative($image)) {
                continue;
            }

            $checkedCount++;

            // Check for alt attribute
            if (! $this->hasAlt($image)) {
                $src = $this->extractSrc($image);
                $missingAlt[] = $src ?: '(unknown source)';
            }
        }

        if ($checkedCount === 0) {
            return RuleResult::skip(
                $this->getId(),
                'No relevant images found (all were decorative or tracking pixels).'
            );
        }

        // Determine result
        if (empty($missingAlt)) {
            return RuleResult::pass(
                $this->getId(),
                "All {$checkedCount} images have alt attributes.",
                100
            );
        }

        $missingCount = count($missingAlt);

        return new RuleResult(
            ruleId: $this->getId(),
            status: 'fail',
            score: 0,
            message: "{$missingCount} image(s) missing alt attribute out of {$checkedCount} checked.",
            recommendation: 'Add descriptive alt text to all images. Alt text helps search engines understand images and improves accessibility.',
            actualValue: "{$missingCount} missing",
            expectedValue: '0 missing',
            details: [
                'missing_alt' => array_slice($missingAlt, 0, 10),
            ],
        );
    }

    /**
     * Extract all img tags from HTML.
     *
     * @return array<int, string>
     */
    protected function extractImages(string $html): array
    {
        $images = [];

        if (preg_match_all('/<img[^>]*>/i', $html, $matches)) {
            $images = $matches[0];
        }

        return $images;
    }

    /**
     * Check if image is tiny (tracking pixel).
     */
    protected function isTinyImage(string $imgTag): bool
    {
        // Check width attribute
        if (preg_match('/width\s*=\s*["\']?(\d+)/i', $imgTag, $matches)) {
            if ((int) $matches[1] < self::MIN_DIMENSION) {
                return true;
            }
        }

        // Check height attribute
        if (preg_match('/height\s*=\s*["\']?(\d+)/i', $imgTag, $matches)) {
            if ((int) $matches[1] < self::MIN_DIMENSION) {
                return true;
            }
        }

        // Check inline style
        if (preg_match('/style\s*=\s*["\'][^"\']*width\s*:\s*(\d+)px/i', $imgTag, $matches)) {
            if ((int) $matches[1] < self::MIN_DIMENSION) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if image is an SVG.
     */
    protected function isSvg(string $imgTag): bool
    {
        if (preg_match('/src\s*=\s*["\'][^"\']*\.svg["\']?/i', $imgTag)) {
            return true;
        }

        if (preg_match('/src\s*=\s*["\']data:image\/svg/i', $imgTag)) {
            return true;
        }

        return false;
    }

    /**
     * Check if image is explicitly decorative.
     */
    protected function isDecorative(string $imgTag): bool
    {
        // role="presentation"
        if (preg_match('/role\s*=\s*["\']presentation["\']/i', $imgTag)) {
            return true;
        }

        // aria-hidden="true"
        if (preg_match('/aria-hidden\s*=\s*["\']true["\']/i', $imgTag)) {
            return true;
        }

        return false;
    }

    /**
     * Check if image has an alt attribute.
     */
    protected function hasAlt(string $imgTag): bool
    {
        // Has alt attribute (even if empty, that's valid for decorative)
        return (bool) preg_match('/\salt\s*=/i', $imgTag);
    }

    /**
     * Extract src from img tag.
     */
    protected function extractSrc(string $imgTag): ?string
    {
        if (preg_match('/src\s*=\s*["\']([^"\']+)["\']/i', $imgTag, $matches)) {
            $src = $matches[1];

            // Truncate long data URIs
            if (str_starts_with($src, 'data:')) {
                return 'data:image/...(base64)';
            }

            // Truncate long URLs
            if (mb_strlen($src) > 60) {
                return mb_substr($src, 0, 57) . '...';
            }

            return $src;
        }

        return null;
    }
}
