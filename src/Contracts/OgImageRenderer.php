<?php

declare(strict_types=1);

namespace Rankbeam\Seo\Contracts;

use Rankbeam\Seo\Services\OgImage\OgImageGenerator;
use Rankbeam\Seo\Services\OgImage\OgImageRenderException;

/**
 * Renders a fully-formed HTML document to a PNG image of exact pixel size.
 *
 * The generator ({@see OgImageGenerator})
 * renders a Blade template to a self-contained HTML string (fonts inlined as
 * data URIs) and hands it here. A driver is responsible only for turning that
 * HTML into PNG bytes at the requested dimensions — it owns no layout or
 * templating logic, so a browser-based driver (Browsershot) and any future
 * canvas-based driver can share this one contract.
 */
interface OgImageRenderer
{
    /**
     * Render an HTML document to raw PNG bytes.
     *
     * @param  string  $html  Self-contained HTML (all assets inlined).
     * @param  int  $width  Output width in pixels.
     * @param  int  $height  Output height in pixels.
     * @return string Raw PNG image data.
     *
     * @throws OgImageRenderException
     */
    public function render(string $html, int $width, int $height): string;

    /**
     * Whether this renderer's runtime dependencies are actually available
     * (e.g. the spatie/browsershot package + a reachable Chrome). Lets the
     * generator degrade gracefully instead of throwing on a misconfigured host.
     */
    public function isAvailable(): bool;
}
