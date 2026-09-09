<?php

declare(strict_types=1);

namespace Rankbeam\Seo\I18n;

/**
 * Detects the dominant writing script of a piece of text.
 *
 * Search engines budget titles and descriptions in pixels, not characters, and
 * a full-width CJK glyph is roughly twice as wide as a Latin letter. The
 * length policy, the computed-description truncation and the OG-image font
 * stack all need to know which script they are dealing with; this is the one
 * place that decides. Detection is a weighted letter count per Unicode script
 * property — pure PCRE, no dependency on ext-intl.
 *
 * Only scripts with a *layout* consequence get their own bucket. Han, Kana and
 * Hangul are grouped as CJK because they share the glyph width and the
 * no-spaces (Han/Kana) or spaced-but-wide (Hangul) layout; Cyrillic, Greek,
 * Thai, Arabic, Hebrew and Devanagari get a bucket of their own so a site can
 * give each its own limits. Everything else counts as Latin.
 */
final class Script
{
    public const LATIN = 'latin';

    public const CYRILLIC = 'cyrillic';

    public const GREEK = 'greek';

    public const CJK = 'cjk';

    public const THAI = 'thai';

    public const ARABIC = 'arabic';

    public const HEBREW = 'hebrew';

    public const DEVANAGARI = 'devanagari';

    /**
     * Unicode script properties per bucket, with the weight each letter
     * carries in the dominance vote. CJK glyphs are full-width, so one of
     * them "outvotes" one Latin letter — a mixed title such as "Laravel SEO の
     * 完全ガイド" resolves to the script that owns most of the pixel width.
     *
     * @var array<string, array{0: string, 1: int}>
     */
    private const PATTERNS = [
        self::CJK => ['\p{Han}\p{Hiragana}\p{Katakana}\p{Hangul}', 2],
        self::LATIN => ['\p{Latin}', 1],
        self::CYRILLIC => ['\p{Cyrillic}', 1],
        self::GREEK => ['\p{Greek}', 1],
        self::THAI => ['\p{Thai}', 1],
        self::ARABIC => ['\p{Arabic}', 1],
        self::HEBREW => ['\p{Hebrew}', 1],
        self::DEVANAGARI => ['\p{Devanagari}', 1],
    ];

    /**
     * Language subtag → script bucket, the hint used when the text carries no
     * letters (empty, digits only) and the tie-breaker between two scripts.
     *
     * @var array<string, string>
     */
    private const LANGUAGES = [
        'ja' => self::CJK, 'zh' => self::CJK, 'ko' => self::CJK, 'yue' => self::CJK,
        'ru' => self::CYRILLIC, 'uk' => self::CYRILLIC, 'bg' => self::CYRILLIC, 'sr' => self::CYRILLIC,
        'be' => self::CYRILLIC, 'mk' => self::CYRILLIC, 'kk' => self::CYRILLIC, 'ky' => self::CYRILLIC, 'mn' => self::CYRILLIC,
        'el' => self::GREEK,
        'th' => self::THAI,
        'ar' => self::ARABIC, 'fa' => self::ARABIC, 'ur' => self::ARABIC, 'ps' => self::ARABIC,
        'he' => self::HEBREW, 'iw' => self::HEBREW, 'yi' => self::HEBREW,
        'hi' => self::DEVANAGARI, 'mr' => self::DEVANAGARI, 'ne' => self::DEVANAGARI,
    ];

    /**
     * Every script bucket, in a stable order.
     *
     * @return array<int, string>
     */
    public static function all(): array
    {
        return array_keys(self::PATTERNS);
    }

    /**
     * The dominant script of $text; the locale's script when the text has no
     * letters at all; LATIN when neither says anything.
     */
    public static function detect(?string $text, ?string $locale = null): string
    {
        $text = (string) $text;
        $hint = $locale !== null ? self::fromLocale($locale) : null;

        if ($text === '') {
            return $hint ?? self::LATIN;
        }

        $best = null;
        $bestScore = 0;

        foreach (self::PATTERNS as $script => [$class, $weight]) {
            $count = preg_match_all('/['.$class.']/u', $text);

            if ($count === false || $count === 0) {
                continue;
            }

            $score = $count * $weight;

            // A strict ">" keeps the PATTERNS order as the first tie-breaker;
            // the locale hint then wins an exact tie between two scripts.
            if ($score > $bestScore || ($score === $bestScore && $script === $hint)) {
                $best = $script;
                $bestScore = $score;
            }
        }

        return $best ?? $hint ?? self::LATIN;
    }

    /**
     * The script a locale (`ja`, `zh_CN`, `zh-Hant-TW`, `pt_BR`) is written
     * in — from its language subtag, or from an explicit ISO 15924 script
     * subtag when one is present (`sr-Latn` is Latin although Serbian defaults
     * to Cyrillic). Null for a blank or unrecognisable locale.
     */
    public static function fromLocale(?string $locale): ?string
    {
        if ($locale === null || trim($locale) === '') {
            return null;
        }

        $parts = preg_split('/[-_]/', strtolower(trim($locale))) ?: [];
        $language = $parts[0] ?? '';

        foreach (array_slice($parts, 1) as $part) {
            $bucket = self::fromCode($part);

            if ($bucket !== null) {
                return $bucket;
            }
        }

        if (isset(self::LANGUAGES[$language])) {
            return self::LANGUAGES[$language];
        }

        return preg_match('/^[a-z]{2,3}$/', $language) === 1 ? self::LATIN : null;
    }

    /** Map a known ISO 15924 script to a supported group; unknown stays null. */
    public static function fromCode(?string $code): ?string
    {
        return match (strtolower($code ?? '')) {
            'latn' => self::LATIN,
            'cyrl' => self::CYRILLIC,
            'grek' => self::GREEK,
            'hans', 'hant', 'jpan', 'kore', 'hani' => self::CJK,
            'thai' => self::THAI,
            'arab' => self::ARABIC,
            'hebr' => self::HEBREW,
            'deva' => self::DEVANAGARI,
            default => null,
        };
    }

    /**
     * Whether text in this script separates words with spaces. Han and Kana
     * do not, Thai does not; Korean does, but Hangul shares the CJK bucket,
     * so the truncator looks at the text itself before trusting this.
     */
    public static function usesWordSpaces(string $script): bool
    {
        return ! in_array($script, [self::CJK, self::THAI], true);
    }

    /**
     * The number of user-perceived characters (extended grapheme clusters).
     *
     * A Thai syllable with its vowel marks, a Devanagari conjunct, an emoji
     * with a skin-tone modifier or a Latin letter with a combining accent each
     * count as ONE — the unit an editor sees and the unit search engines
     * effectively budget. Pure PCRE (`\X`), so it needs no ext-intl. For plain
     * Latin text it equals mb_strlen(). See {@see graphemes()} for the one
     * PCRE2 quirk this corrects.
     */
    public static function length(?string $text): int
    {
        if ($text === null || $text === '') {
            return 0;
        }

        return count(self::graphemes($text));
    }

    /**
     * Split text into its grapheme clusters.
     *
     * PCRE2 builds before 10.44 (Ubuntu 24.04 ships 10.42, PHP 8.2/8.3 bundle
     * 10.40/10.42) join consecutive emoji into ONE `\X` cluster — "🔥🔥🔥"
     * comes back as a single character, and thirty 👋🏽 in a row as one —
     * although the Unicode rule (GB11) only joins two pictographs across a
     * zero-width joiner. Every cluster that holds a pictograph is therefore
     * re-split before each pictograph that is not preceded by a joiner, which
     * leaves ZWJ sequences (👨‍👩‍👧), modifiers (👋🏽), variation selectors,
     * keycaps and flags intact and makes the count identical on every host.
     * A PCRE2 too old to know the Extended_Pictographic property keeps its own
     * clusters.
     *
     * @return array<int, string>
     */
    public static function graphemes(?string $text): array
    {
        if ($text === null || $text === '') {
            return [];
        }

        if (preg_match_all(self::GRAPHEME, $text, $matches) === false) {
            // Invalid UTF-8 makes preg_match_all fail; degrade to codepoints
            // rather than reporting no characters at all.
            return mb_str_split($text);
        }

        if (! self::knowsPictographs()) {
            return $matches[0];
        }

        $graphemes = [];

        foreach ($matches[0] as $cluster) {
            // A lone pictograph is 4 bytes; anything longer that contains one
            // may be an over-joined run.
            if (strlen($cluster) > 4 && preg_match(self::PICTOGRAPH, $cluster) === 1) {
                foreach (preg_split(self::EMOJI_RUN, $cluster, -1, PREG_SPLIT_NO_EMPTY) ?: [$cluster] as $part) {
                    $graphemes[] = $part;
                }

                continue;
            }

            $graphemes[] = $cluster;
        }

        return $graphemes;
    }

    /**
     * Whether this PCRE2 knows the Extended_Pictographic property (10.40+).
     */
    private static function knowsPictographs(): bool
    {
        static $known = null;

        return $known ??= @preg_match(self::PICTOGRAPH, "\u{1F600}") === 1;
    }

    /**
     * One extended grapheme cluster. `(*NO_JIT)` keeps the match in the PCRE2
     * interpreter: `\X` compiles to a deep pattern and the JIT's fixed stack
     * can overflow on long emoji runs (PREG_JIT_STACKLIMIT_ERROR reads as
     * "false"). Titles and descriptions are short; the interpreter costs
     * nothing.
     */
    private const GRAPHEME = '/(*NO_JIT)\X/u';

    private const PICTOGRAPH = '/\p{Extended_Pictographic}/u';

    /**
     * A split point before every pictograph not preceded by a zero-width
     * joiner — the boundary GB11 requires between two emoji.
     */
    private const EMOJI_RUN = '/(?<!\x{200D})(?=\p{Extended_Pictographic})/u';
}
