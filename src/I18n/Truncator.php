<?php

declare(strict_types=1);

namespace Rankbeam\Seo\I18n;

/**
 * Grapheme-safe, script-aware text truncation for computed descriptions.
 *
 * The historical rule — cut at the last word boundary within the limit, as
 * long as that boundary is at least 60 % into the limit, else hard-cut, and
 * never add an ellipsis — is kept for every script that separates words with
 * spaces. Two things change for the scripts that do not:
 *
 * - **No spaces (Han, Kana, Thai):** a "word boundary" does not exist, so the
 *   cut prefers the last sentence or clause mark (。！？、，…) inside the limit,
 *   then a space if the text has any (Korean, mixed text), then a hard cut.
 * - **Graphemes, not codepoints:** slicing happens on extended grapheme
 *   clusters, so a cut can never land inside a combining sequence — a Thai
 *   vowel mark, a Devanagari conjunct or an emoji modifier is never separated
 *   from its base.
 *
 * Output for plain Latin text is byte-identical to the previous behaviour.
 */
final class Truncator
{
    /**
     * Minimum fraction of the limit a boundary must reach for the cut to land
     * on it instead of hard-cutting at the limit.
     */
    public const BOUNDARY_MIN_RATIO = 0.6;

    /**
     * Punctuation and whitespace trimmed from the end of a truncated value —
     * ASCII first, then the full-width forms and the ideographic space.
     */
    private const TRAILING = '/[\s,.;:\-\x{3001}\x{3002}\x{FF0C}\x{FF0E}\x{FF1B}\x{FF1A}\x{FF0D}\x{3000}]+$/u';

    /**
     * Sentence and clause marks that make an acceptable cut point in text
     * without word spaces.
     */
    private const CLAUSE_MARKS = ['。', '！', '？', '、', '，', '；', '：', '.', '!', '?', ',', ';', ':'];

    /**
     * Truncate $text to at most $max graphemes.
     *
     * @param  string  $text  Already-cleaned plain text (no HTML, whitespace collapsed)
     * @param  int  $max  The budget in graphemes for this text's script
     * @param  string|null  $script  A {@see Script} bucket; detected from the text when null
     */
    public static function truncate(string $text, int $max, ?string $script = null): string
    {
        $graphemes = Script::graphemes($text);

        if ($max < 1 || count($graphemes) <= $max) {
            return $text;
        }

        $script ??= Script::detect($text);
        $minIndex = (int) floor($max * self::BOUNDARY_MIN_RATIO);

        $cut = Script::usesWordSpaces($script)
            ? self::spaceBoundary($graphemes, $max, $minIndex)
            : (self::clauseBoundary($graphemes, $max, $minIndex) ?? self::spaceBoundary($graphemes, $max, $minIndex));

        $end = self::outsideZwjSequence($graphemes, $cut ?? $max);

        $slice = array_slice($graphemes, 0, $end);

        return (string) preg_replace(self::TRAILING, '', implode('', $slice));
    }

    /**
     * Move a cut point back until it is not inside an emoji ZWJ sequence
     * (👨‍👩‍👧 is U+1F468 ZWJ U+1F469 ZWJ U+1F467).
     *
     * A current PCRE2 keeps a whole ZWJ sequence in one grapheme, so the cut
     * never lands inside one and this is a no-op. An older PCRE2 (without the
     * GB11 rule) may split the sequence at each ZWJ; a cut there would leave
     * a dangling joiner and a broken family. Backing off to the last grapheme
     * that neither ends with a ZWJ nor is followed by one keeps the sequence
     * whole on every host.
     *
     * @param  array<int, string>  $graphemes
     */
    private static function outsideZwjSequence(array $graphemes, int $end): int
    {
        $zwj = "\u{200D}";

        while ($end > 0) {
            $endsWithJoiner = str_ends_with($graphemes[$end - 1], $zwj);
            $nextStartsWithJoiner = isset($graphemes[$end]) && str_starts_with($graphemes[$end], $zwj);

            if (! $endsWithJoiner && ! $nextStartsWithJoiner) {
                return $end;
            }

            $end--;
        }

        return $end;
    }

    /**
     * The index of the last whitespace grapheme within the first $max + 1
     * graphemes, when it lies at or beyond $minIndex; null otherwise. Looking
     * one past the limit lets a word that ends exactly at the limit survive.
     *
     * @param  array<int, string>  $graphemes
     */
    private static function spaceBoundary(array $graphemes, int $max, int $minIndex): ?int
    {
        for ($i = min($max, count($graphemes) - 1); $i >= $minIndex; $i--) {
            if (preg_match('/^\s$/u', $graphemes[$i]) === 1) {
                return $i;
            }
        }

        return null;
    }

    /**
     * The index just past the last clause mark within the first $max
     * graphemes, when it lies at or beyond $minIndex; null otherwise.
     *
     * @param  array<int, string>  $graphemes
     */
    private static function clauseBoundary(array $graphemes, int $max, int $minIndex): ?int
    {
        for ($i = min($max, count($graphemes)) - 1; $i >= $minIndex; $i--) {
            if (in_array($graphemes[$i], self::CLAUSE_MARKS, true)) {
                return $i;
            }
        }

        return null;
    }
}
