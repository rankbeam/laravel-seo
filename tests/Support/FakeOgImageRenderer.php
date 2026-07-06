<?php

declare(strict_types=1);

namespace Rankbeam\Seo\Tests\Support;

use Rankbeam\Seo\Contracts\OgImageRenderer;
use RuntimeException;

/**
 * Test double for the OG-image renderer so the suite never needs a real
 * browser. Records how many times it rendered, and can be told to fail to
 * exercise the generator's fail-open / throw-on-error paths.
 */
class FakeOgImageRenderer implements OgImageRenderer
{
    public static int $calls = 0;

    public static bool $throw = false;

    /** The last HTML it was handed — lets tests assert what the template produced. */
    public static ?string $lastHtml = null;

    public function render(string $html, int $width, int $height): string
    {
        self::$calls++;
        self::$lastHtml = $html;

        if (self::$throw) {
            throw new RuntimeException('fake render failure');
        }

        // A real, valid 1x1 PNG so anything that inspects the bytes still works.
        return (string) base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg=='
        );
    }

    public function isAvailable(): bool
    {
        return true;
    }

    public static function reset(): void
    {
        self::$calls = 0;
        self::$throw = false;
        self::$lastHtml = null;
    }
}
