<?php

declare(strict_types=1);

namespace Rankbeam\Seo\Services\OgImage;

use RuntimeException;

/**
 * Thrown when a renderer cannot produce a PNG (missing dependency, browser
 * crash/timeout, empty output). The generator catches this and fails open —
 * a page keeps its static default_og_image rather than 500-ing.
 */
class OgImageRenderException extends RuntimeException {}
