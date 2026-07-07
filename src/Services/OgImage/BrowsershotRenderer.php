<?php

declare(strict_types=1);

namespace Rankbeam\Seo\Services\OgImage;

use Rankbeam\Seo\Contracts\OgImageRenderer;
use Spatie\Browsershot\Browsershot;
use Throwable;

/**
 * Renders OG images with a real headless browser via spatie/browsershot.
 *
 * The browser is what makes this the quality driver: it does multi-line text
 * wrapping, complex-script shaping, bidi and — critically — automatic
 * per-script font fallback, so a title mixing Latin accents and CJK renders
 * correctly instead of as .notdef boxes. The generator hands us a
 * self-contained HTML document (fonts inlined as data URIs) and we only turn
 * it into PNG bytes at the requested size.
 *
 * spatie/browsershot is an OPTIONAL dependency — the class is guarded so the
 * free core package installs without Chrome. Any failure (missing package,
 * browser crash, timeout) becomes an {@see OgImageRenderException} the
 * generator catches and fails open on.
 */
class BrowsershotRenderer implements OgImageRenderer
{
    public function render(string $html, int $width, int $height): string
    {
        if (! $this->isAvailable()) {
            throw new OgImageRenderException(
                'spatie/browsershot is not installed. Run: composer require spatie/browsershot'
            );
        }

        // Render to a temp PNG then read it back — mirrors the invocation
        // verified working on Windows/macOS/Linux during the P6 spike.
        $tmp = tempnam(sys_get_temp_dir(), 'seo_og_').'.png';

        try {
            $this->configureBrowsershot($html, $width, $height)->save($tmp);

            $bytes = @file_get_contents($tmp);
        } catch (Throwable $e) {
            throw new OgImageRenderException('Browsershot render failed: '.$e->getMessage(), 0, $e);
        } finally {
            if (is_file($tmp)) {
                @unlink($tmp);
            }
        }

        if ($bytes === false || $bytes === '') {
            throw new OgImageRenderException('Browsershot produced an empty image.');
        }

        return $bytes;
    }

    /**
     * Build the fully-configured Browsershot instance for a render, applying
     * every seo.og_image.* setting (binary paths, sandbox, extra Chromium
     * flags) but stopping short of ->save(), which is what actually launches
     * Chrome. Kept as its own seam so the config→Chrome wiring is verifiable
     * without a browser.
     */
    protected function configureBrowsershot(string $html, int $width, int $height): Browsershot
    {
        $shot = Browsershot::html($html)
            ->windowSize($width, $height)
            ->deviceScaleFactor(1)
            ->waitUntilNetworkIdle()
            ->timeout((int) config('seo.og_image.timeout', 60));

        if ($chrome = config('seo.og_image.chrome_path')) {
            $shot->setChromePath($chrome);
        }
        if ($node = config('seo.og_image.node_binary')) {
            $shot->setNodeBinary($node);
        }
        if ($modules = config('seo.og_image.npm_module_path')) {
            $shot->setNodeModulePath($modules);
        }

        // Launch Chrome with --no-sandbox. Needed on default Ubuntu
        // 22.04+/24.04 hosts where AppArmor's unprivileged-userns
        // restriction otherwise makes Chrome fail with "No usable sandbox!".
        if (config('seo.og_image.no_sandbox', false)) {
            $shot->noSandbox();
        }

        if ($args = $this->chromiumArguments()) {
            $shot->addChromiumArguments($args);
        }

        return $shot;
    }

    /**
     * The configured extra Chromium flags (seo.og_image.browsershot_args),
     * shaped for Browsershot::addChromiumArguments(). That method prepends "--"
     * to every entry, so we strip any leading dashes the user included — both
     * 'disable-gpu' and '--disable-gpu' resolve to the same flag. The map form
     * ('proxy-server' => 'http://…') is preserved for value-bearing flags.
     *
     * @return array<int|string, string>
     */
    protected function chromiumArguments(): array
    {
        $args = (array) config('seo.og_image.browsershot_args', []);

        $normalized = [];

        foreach ($args as $key => $value) {
            if (is_string($key)) {
                $normalized[ltrim($key, '-')] = $value;
            } else {
                $normalized[] = ltrim((string) $value, '-');
            }
        }

        return $normalized;
    }

    /**
     * The spatie/browsershot package is present. Chrome reachability can only
     * be proven by an actual render, so a missing/broken browser surfaces as a
     * render exception (fail-open) rather than here.
     */
    public function isAvailable(): bool
    {
        return class_exists(Browsershot::class);
    }
}
