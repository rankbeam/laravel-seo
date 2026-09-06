<?php

declare(strict_types=1);

namespace Rankbeam\Seo\I18n;

/**
 * Locale-aware lowercasing and case folding for comparisons.
 *
 * `mb_strtolower()` is script-aware but locale-blind, and three well-known
 * cases ship silent bugs:
 *
 * - **Turkish / Azeri dotted and dotless i.** Lowercase of `I` is `ı`
 *   (dotless), lowercase of `İ` is `i`. `mb_strtolower('İstanbul')` yields
 *   "i̇stanbul" (an `i` plus a combining dot above) on PHP ≥ 8.3, which never
 *   equals "istanbul".
 * - **Greek final sigma.** `Σ` lowercases to `σ` mid-word but `ς` at the end
 *   of a word; "ΟΔΟΣ" and "οδός" must compare equal.
 * - **German sharp s.** "STRASSE" and "Straße" are the same word.
 *
 * {@see lower()} is the display form (locale rules, nothing else changed);
 * {@see fold()} is the comparison form — lowercased *and* with the sigma and
 * sharp-s variants collapsed, so two spellings of one word fold to one string.
 * Both NFC-normalise first when ext-intl is present so a precomposed and a
 * decomposed accent compare equal.
 */
final class CaseFolder
{
    /**
     * Locale-aware lowercase for display.
     */
    public static function lower(string $text, ?string $locale = null): string
    {
        $text = self::normalize($text);

        if (self::isTurkic($locale)) {
            $text = strtr($text, ['I' => 'ı', 'İ' => 'i']);
        }

        return mb_strtolower($text);
    }

    /**
     * Case-fold for comparisons: lowercase, then collapse the variants that
     * spell one word two ways.
     */
    public static function fold(string $text, ?string $locale = null): string
    {
        return strtr(self::lower($text, $locale), [
            "i\u{0307}" => 'i', // İ lowercased by full case mapping (PHP ≥ 8.3) outside Turkish
            'ς' => 'σ',
            'ß' => 'ss',
        ]);
    }

    /**
     * Whether two strings are the same word under folding.
     */
    public static function equals(string $a, string $b, ?string $locale = null): bool
    {
        return self::fold($a, $locale) === self::fold($b, $locale);
    }

    /**
     * Whether $needle occurs in $haystack under folding.
     */
    public static function contains(string $haystack, string $needle, ?string $locale = null): bool
    {
        if ($needle === '') {
            return false;
        }

        return str_contains(self::fold($haystack, $locale), self::fold($needle, $locale));
    }

    /**
     * Whether $needle occurs in $haystack as a whole word under folding —
     * "Acme" matches "Acme Blog" but not "Acmestic". Word characters are any
     * Unicode letter, mark or digit, so accented and non-Latin brands get the
     * same boundary behaviour as ASCII ones.
     */
    public static function containsWord(string $haystack, string $needle, ?string $locale = null): bool
    {
        if (trim($needle) === '') {
            return false;
        }

        $pattern = '/(?<![\p{L}\p{M}\p{N}_])'.preg_quote(self::fold($needle, $locale), '/').'(?![\p{L}\p{M}\p{N}_])/u';

        return preg_match($pattern, self::fold($haystack, $locale)) === 1;
    }

    /**
     * Whether a locale follows the Turkic casing rules (tr, az, and their
     * regional or script variants).
     */
    public static function isTurkic(?string $locale): bool
    {
        if ($locale === null) {
            return false;
        }

        $language = strtolower((string) preg_split('/[-_]/', trim($locale))[0]);

        return in_array($language, ['tr', 'az', 'tt', 'crh', 'kaa'], true);
    }

    /**
     * NFC normalisation when ext-intl is available; identity otherwise.
     */
    private static function normalize(string $text): string
    {
        if ($text === '' || ! class_exists(\Normalizer::class)) {
            return $text;
        }

        $normalized = \Normalizer::normalize($text, \Normalizer::FORM_C);

        return $normalized === false ? $text : $normalized;
    }
}
