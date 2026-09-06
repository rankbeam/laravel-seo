<?php

declare(strict_types=1);

namespace Rankbeam\Seo\Services\OgImage;

use Rankbeam\Seo\I18n\Script;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;
use Throwable;

/**
 * Asks the host's font system whether a script can be rendered at all.
 *
 * The bundled OG font covers Latin, Cyrillic and Greek; every other script
 * depends on a font installed on the machine that runs `seo:og-images`. A
 * missing one does not fail the render — Chrome draws .notdef boxes ("tofu")
 * and the card ships looking broken. On Linux, `fc-list :lang=ja` answers the
 * question before rendering; this probe wraps that so the command can warn
 * with the exact package to install. Where fontconfig is absent (Windows,
 * macOS, minimal containers) the probe answers "unknown" and stays quiet.
 */
class FontProbe
{
    /**
     * Scripts the bundled Noto Sans Bold already covers.
     *
     * @var array<int, string>
     */
    public const BUNDLED = [Script::LATIN, Script::CYRILLIC, Script::GREEK];

    /**
     * fontconfig language tag per script bucket (`fc-list :lang=<tag>`).
     *
     * @var array<string, string>
     */
    protected const LANG = [
        Script::CJK => 'ja',
        Script::THAI => 'th',
        Script::ARABIC => 'ar',
        Script::HEBREW => 'he',
        Script::DEVANAGARI => 'hi',
    ];

    /**
     * The Debian/Ubuntu package that supplies each script.
     *
     * @var array<string, string>
     */
    protected const PACKAGES = [
        Script::CJK => 'fonts-noto-cjk',
        Script::THAI => 'fonts-noto-core',
        Script::ARABIC => 'fonts-noto-core',
        Script::HEBREW => 'fonts-noto-core',
        Script::DEVANAGARI => 'fonts-noto-core',
    ];

    /** @var array<string, bool|null> */
    protected array $memo = [];

    /**
     * Whether an installed font covers the script: true / false, or null when
     * the host cannot say (no fontconfig) or the script is bundled.
     */
    public function covers(string $script): ?bool
    {
        if (in_array($script, self::BUNDLED, true)) {
            return true;
        }

        $lang = self::LANG[$script] ?? null;

        if ($lang === null) {
            return null;
        }

        if (array_key_exists($script, $this->memo)) {
            return $this->memo[$script];
        }

        return $this->memo[$script] = $this->query($lang);
    }

    /**
     * The one-line install hint for a script, for the command's warning.
     */
    public function installHint(string $script): string
    {
        $package = self::PACKAGES[$script] ?? 'fonts-noto-core';

        return "apt-get install {$package}";
    }

    /**
     * Run `fc-list :lang=<tag> family`; null when fontconfig is unavailable.
     */
    protected function query(string $lang): ?bool
    {
        try {
            $binary = (new ExecutableFinder)->find('fc-list');

            if ($binary === null) {
                return null;
            }

            $process = new Process([$binary, ':lang='.$lang, 'family']);
            $process->setTimeout(10);
            $process->run();

            if (! $process->isSuccessful()) {
                return null;
            }

            return trim($process->getOutput()) !== '';
        } catch (Throwable) {
            return null;
        }
    }
}
