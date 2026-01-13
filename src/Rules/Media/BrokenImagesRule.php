<?php

declare(strict_types=1);

namespace Fibonoir\LaravelSEO\Rules\Media;

use Fibonoir\LaravelSEO\Contracts\RuleInterface;
use Fibonoir\LaravelSEO\Data\AnalysisContext;
use Fibonoir\LaravelSEO\Data\RuleResult;

/**
 * Checks for broken images in the content.
 *
 * Broken images negatively impact:
 * - User experience (broken image icons)
 * - Professional appearance
 * - Page load performance (failed requests)
 * - SEO (poor quality signal)
 *
 * ## Implementation
 * Uses pre-validated brokenImages from context (populated by async validation).
 * If brokenImages is null, the validation hasn't run yet.
 *
 * ## Scoring
 * - **Pass:** No broken images
 * - **Fail:** Any broken images found
 * - **Skip:** Validation not yet performed
 *
 * ## Note
 * Image validation is performed asynchronously to avoid
 * blocking the main analysis. Results are cached.
 */
class BrokenImagesRule implements RuleInterface
{
    public function getId(): string
    {
        return 'broken_images';
    }

    public function getName(): string
    {
        return 'Broken Images';
    }

    public function getWeight(): int
    {
        return 5;
    }

    public function getCategory(): string
    {
        return 'media';
    }

    public function analyze(AnalysisContext $context): RuleResult
    {
        // Check if validation has been performed
        if ($context->brokenImages === null) {
            return RuleResult::skip(
                $this->getId(),
                'Image validation has not been performed yet. Run async validation to check for broken images.'
            );
        }

        $brokenImages = $context->brokenImages;
        $count = count($brokenImages);

        // Pass: No broken images
        if ($count === 0) {
            return RuleResult::pass(
                $this->getId(),
                'No broken images detected.',
                100
            );
        }

        // Fail: Broken images found
        $details = [
            'broken_images' => array_map(function ($image) {
                if (is_array($image)) {
                    $src = $image['src'] ?? '(unknown)';
                    $status = $image['status'] ?? 'error';

                    // Truncate long URLs
                    if (mb_strlen($src) > 60) {
                        $src = mb_substr($src, 0, 57) . '...';
                    }

                    return "{$src} ({$status})";
                }

                // Truncate string URLs
                if (mb_strlen($image) > 60) {
                    return mb_substr($image, 0, 57) . '...';
                }

                return $image;
            }, array_slice($brokenImages, 0, 10)),
        ];

        return new RuleResult(
            ruleId: $this->getId(),
            status: 'fail',
            score: 0,
            message: "{$count} broken image(s) detected.",
            recommendation: 'Fix or remove broken images. Broken images create a poor user experience and look unprofessional. Re-upload images or update src URLs.',
            actualValue: "{$count} broken images",
            expectedValue: '0 broken images',
            details: $details,
        );
    }
}
