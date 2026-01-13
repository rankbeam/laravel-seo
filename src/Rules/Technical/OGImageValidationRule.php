<?php

declare(strict_types=1);

namespace Fibonoir\LaravelSEO\Rules\Technical;

use Fibonoir\LaravelSEO\Contracts\RuleInterface;
use Fibonoir\LaravelSEO\Data\AnalysisContext;
use Fibonoir\LaravelSEO\Data\RuleResult;

/**
 * Checks if an Open Graph image is set and valid.
 *
 * OG images appear when content is shared on social media platforms.
 * A compelling image can significantly increase click-through rates.
 *
 * ## Scoring
 * - **Pass:** OG image set and valid
 * - **Warning:** No OG image set
 * - **Fail:** OG image is broken (from async validation)
 *
 * ## Best Practices
 * - Use 1200x630px images for optimal display
 * - Avoid text-heavy images (Facebook may reject)
 * - Use high-quality, relevant images
 * - Host images on your own domain
 *
 * ## Platforms
 * OG images are used by:
 * - Facebook, LinkedIn, Slack, Discord
 * - Twitter (falls back to og:image if twitter:image not set)
 * - Many other apps and platforms
 */
class OGImageValidationRule implements RuleInterface
{
    public function getId(): string
    {
        return 'og_image_validation';
    }

    public function getName(): string
    {
        return 'Open Graph Image';
    }

    public function getWeight(): int
    {
        return 5;
    }

    public function getCategory(): string
    {
        return 'technical';
    }

    public function analyze(AnalysisContext $context): RuleResult
    {
        $ogImage = $context->ogImage ?? '';

        // No OG image set
        if (empty($ogImage)) {
            return RuleResult::warning(
                $this->getId(),
                'No Open Graph image set.',
                'Add an og:image meta tag for better social media sharing. Use a 1200x630px image for optimal display on most platforms.',
                50,
                'Not set',
                'og:image URL'
            );
        }

        // Check if validation has been performed
        if ($context->ogImageBroken === null) {
            // Validation not yet run, check basic URL format
            if (! filter_var($ogImage, FILTER_VALIDATE_URL)) {
                return RuleResult::fail(
                    $this->getId(),
                    'Open Graph image URL is invalid.',
                    'Use an absolute URL for the og:image (e.g., https://example.com/image.jpg).',
                    $this->truncateUrl($ogImage),
                    'Valid absolute URL'
                );
            }

            return new RuleResult(
                ruleId: $this->getId(),
                status: 'pass',
                score: 80, // Slight deduction since not validated
                message: 'Open Graph image is set (not yet validated).',
                recommendation: 'Run async validation to verify the image is accessible.',
                actualValue: null,
                expectedValue: null,
                details: [
                    'url' => $this->truncateUrl($ogImage),
                    'note' => 'Image accessibility not yet verified',
                ],
            );
        }

        // Image is broken
        if ($context->ogImageBroken === true) {
            return new RuleResult(
                ruleId: $this->getId(),
                status: 'fail',
                score: 0,
                message: 'Open Graph image is broken or inaccessible.',
                recommendation: 'Fix the og:image URL or upload a working image. Broken images result in poor social media previews.',
                actualValue: 'Broken/404',
                expectedValue: 'Accessible image',
                details: [
                    'url' => $this->truncateUrl($ogImage),
                ],
            );
        }

        // Image is valid
        return RuleResult::pass(
            $this->getId(),
            'Open Graph image is set and accessible.',
            100
        );
    }

    /**
     * Truncate long URLs for display.
     */
    protected function truncateUrl(string $url): string
    {
        if (mb_strlen($url) > 60) {
            return mb_substr($url, 0, 57) . '...';
        }

        return $url;
    }
}
