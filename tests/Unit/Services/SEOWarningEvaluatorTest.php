<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Storage;
use Rankbeam\Seo\Services\SEOWarningEvaluator;

/*
|--------------------------------------------------------------------------
| Characterization: editorial SEO warnings
|--------------------------------------------------------------------------
|
| Ported from production SeoWarningEvaluator behavior:
| - title > 60 chars → warning; no manual title → info (fallback indicator)
| - description > 160 chars → warning; no manual description → info
| - no social image → danger; fallback image → info
| - local image below 200x200 → danger; below 1200x630 ideal → info
| - remote images are never probed
|
*/

function evaluator(): SEOWarningEvaluator
{
    return new SEOWarningEvaluator();
}

/**
 * Write a minimal PNG with the given dimensions to the fake public disk.
 * getimagesize() reads width/height straight from the IHDR header, so no
 * image extension is needed.
 */
function fakePng(string $relativePath, int $width, int $height): void
{
    $ihdrData = pack('N', $width) . pack('N', $height) . "\x08\x02\x00\x00\x00";

    $png = "\x89PNG\r\n\x1a\n"
        . pack('N', 13) . 'IHDR' . $ihdrData . pack('N', crc32('IHDR' . $ihdrData))
        . pack('N', 0) . 'IEND' . pack('N', crc32('IEND'));

    Storage::disk('public')->put($relativePath, $png);
}

function warningKeys(array $warnings): array
{
    return array_column($warnings, 'key');
}

beforeEach(function () {
    Storage::fake('public');
    config(['app.url' => 'http://localhost']);
});

describe('title warnings', function () {
    it('warns when the effective title exceeds 60 characters', function () {
        $warnings = evaluator()->evaluateTitle(str_repeat('a', 61), 'manual');

        expect(warningKeys($warnings))->toContain('title_too_long')
            ->and($warnings[0]['level'])->toBe('warning');
    });

    it('does not warn at exactly 60 characters', function () {
        $warnings = evaluator()->evaluateTitle(str_repeat('a', 60), 'manual');

        expect(warningKeys($warnings))->not->toContain('title_too_long');
    });

    it('flags fallback usage when no manual title is set', function () {
        $warnings = evaluator()->evaluateTitle('Computed Title', null);

        expect(warningKeys($warnings))->toContain('title_is_fallback');

        $fallback = collect($warnings)->firstWhere('key', 'title_is_fallback');
        expect($fallback['level'])->toBe('info');
    });

    it('treats a whitespace-only manual title as fallback', function () {
        $warnings = evaluator()->evaluateTitle('Computed Title', '   ');

        expect(warningKeys($warnings))->toContain('title_is_fallback');
    });
});

describe('description warnings', function () {
    it('warns when the effective description exceeds 160 characters', function () {
        $warnings = evaluator()->evaluateDescription(str_repeat('a', 161), 'manual');

        expect(warningKeys($warnings))->toContain('description_too_long');
    });

    it('flags fallback usage when no manual description is set', function () {
        $warnings = evaluator()->evaluateDescription('Auto-generated.', null);

        expect(warningKeys($warnings))->toContain('description_is_fallback');
    });
});

describe('image warnings', function () {
    it('flags a missing image as danger and stops there', function () {
        $warnings = evaluator()->evaluateImage(null, null);

        expect($warnings)->toHaveCount(1)
            ->and($warnings[0]['key'])->toBe('no_image')
            ->and($warnings[0]['level'])->toBe('danger');
    });

    it('flags fallback usage when no manual image is set', function () {
        fakePng('images/fallback.png', 1200, 630);

        $warnings = evaluator()->evaluateImage('/storage/images/fallback.png', null);

        expect(warningKeys($warnings))->toContain('image_is_fallback');
    });

    it('flags images below 200x200 as danger', function () {
        fakePng('images/tiny.png', 150, 120);

        $warnings = evaluator()->evaluateImage('/storage/images/tiny.png', '/storage/images/tiny.png');

        $tooSmall = collect($warnings)->firstWhere('key', 'image_too_small');

        expect($tooSmall)->not->toBeNull()
            ->and($tooSmall['level'])->toBe('danger')
            ->and($tooSmall['message'])->toContain('150x120')
            ->and($tooSmall['message'])->toContain('200x200');
    });

    it('flags images between minimum and ideal as info', function () {
        fakePng('images/mid.png', 800, 400);

        $warnings = evaluator()->evaluateImage('/storage/images/mid.png', '/storage/images/mid.png');

        $notIdeal = collect($warnings)->firstWhere('key', 'image_not_ideal');

        expect($notIdeal)->not->toBeNull()
            ->and($notIdeal['level'])->toBe('info')
            ->and($notIdeal['message'])->toContain('1200x630');
    });

    it('emits no size warning at or above the 1200x630 ideal', function () {
        fakePng('images/ideal.png', 1200, 630);

        $warnings = evaluator()->evaluateImage('/storage/images/ideal.png', '/storage/images/ideal.png');

        expect(warningKeys($warnings))->not->toContain('image_too_small')
            ->and(warningKeys($warnings))->not->toContain('image_not_ideal');
    });

    it('probes same-host absolute URLs through the /storage/ mapping', function () {
        fakePng('images/hosted.png', 100, 100);

        $url = 'http://localhost/storage/images/hosted.png';

        $warnings = evaluator()->evaluateImage($url, $url);

        expect(warningKeys($warnings))->toContain('image_too_small');
    });

    it('never probes images on foreign hosts', function () {
        $url = 'https://cdn.example.org/images/external.png';

        $warnings = evaluator()->evaluateImage($url, $url);

        expect(warningKeys($warnings))->not->toContain('image_too_small')
            ->and(warningKeys($warnings))->not->toContain('image_not_ideal');
    });
});

describe('evaluate() on SEOData', function () {
    it('aggregates title, description, and image warnings', function () {
        $resolved = new \Rankbeam\Seo\Data\SEOData(
            title: str_repeat('t', 70),
            description: 'Short description.',
        );

        $warnings = evaluator()->evaluate($resolved, manual: \Rankbeam\Seo\Data\SEOData::empty());

        expect(warningKeys($warnings))->toContain('title_too_long')
            ->and(warningKeys($warnings))->toContain('title_is_fallback')
            ->and(warningKeys($warnings))->toContain('description_is_fallback')
            ->and(warningKeys($warnings))->toContain('no_image');
    });
});
