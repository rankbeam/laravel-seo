<?php

declare(strict_types=1);

namespace Fibonoir\LaravelSEO\Support;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Wamania\Snowball\StemmerFactory;
use Wamania\Snowball\Stemmer\Stemmer as StemmerInterface;

/**
 * Word stemmer wrapper with caching.
 *
 * Stemming reduces words to their root form for better keyword matching.
 * For example: "running", "runs", "ran" → "run"
 *
 * Uses the Snowball stemmer algorithm (wamania/php-stemmer package).
 *
 * ## Supported Languages
 *
 * - English (en)
 * - Italian (it)
 * - German (de)
 * - French (fr)
 * - Spanish (es)
 * - Portuguese (pt)
 * - Dutch (nl)
 * - Swedish (sv)
 * - Norwegian (no)
 * - Danish (da)
 * - Finnish (fi)
 * - Russian (ru)
 *
 * ## Caching
 *
 * Stems are cached for 24 hours to improve performance on repeated
 * calls. Cache keys follow the pattern: `seo_stem:{locale}:{md5(word)}`
 *
 * ## Usage
 *
 * ```php
 * $stemmer = app(Stemmer::class);
 *
 * // Single word
 * $stem = $stemmer->stem('running'); // "run"
 *
 * // Multiple words
 * $stems = $stemmer->stemBatch(['running', 'jumped', 'swimming']);
 * // ["run", "jump", "swim"]
 *
 * // Phrase
 * $stem = $stemmer->stemPhrase('content marketing strategies');
 * // "content market strategi"
 * ```
 *
 * @see https://snowballstem.org/ For algorithm details
 * @see \Fibonoir\LaravelSEO\Services\Analyzer\ContentAnalyzer For usage context
 */
class Stemmer
{
    /**
     * Cached stemmer instances by locale.
     *
     * @var array<string, StemmerInterface>
     */
    protected array $stemmers = [];

    /**
     * Locale normalization map.
     *
     * Maps locale variants to their base language codes.
     *
     * @var array<string, string>
     */
    protected array $localeMap = [
        // English variants
        'en' => 'en',
        'en_US' => 'en',
        'en_GB' => 'en',
        'en_AU' => 'en',
        'en_CA' => 'en',
        // Italian variants
        'it' => 'it',
        'it_IT' => 'it',
        'it_CH' => 'it',
        // German variants
        'de' => 'de',
        'de_DE' => 'de',
        'de_AT' => 'de',
        'de_CH' => 'de',
        // French variants
        'fr' => 'fr',
        'fr_FR' => 'fr',
        'fr_CA' => 'fr',
        'fr_BE' => 'fr',
        'fr_CH' => 'fr',
        // Spanish variants
        'es' => 'es',
        'es_ES' => 'es',
        'es_MX' => 'es',
        'es_AR' => 'es',
        // Portuguese variants
        'pt' => 'pt',
        'pt_BR' => 'pt',
        'pt_PT' => 'pt',
        // Other languages
        'nl' => 'nl',
        'nl_NL' => 'nl',
        'nl_BE' => 'nl',
        'sv' => 'sv',
        'sv_SE' => 'sv',
        'no' => 'no',
        'nb' => 'no',
        'nn' => 'no',
        'da' => 'da',
        'da_DK' => 'da',
        'fi' => 'fi',
        'fi_FI' => 'fi',
        'ru' => 'ru',
        'ru_RU' => 'ru',
    ];

    /**
     * Cache TTL in seconds (24 hours).
     */
    protected const CACHE_TTL = 86400;

    /**
     * Stem a single word.
     *
     * @param string $word The word to stem
     * @param string $locale The language locale (defaults to 'en')
     * @return string The stemmed word
     *
     * @example
     * ```php
     * $stemmer->stem('running');     // "run"
     * $stemmer->stem('läuft', 'de'); // "lauf"
     * ```
     */
    public function stem(string $word, string $locale = 'en'): string
    {
        $word = mb_strtolower(trim($word));

        if (empty($word)) {
            return '';
        }

        $normalizedLocale = $this->normalizeLocale($locale);
        $cacheKey = $this->getCacheKey($word, $normalizedLocale);

        return Cache::store($this->getCacheStore())
            ->remember($cacheKey, self::CACHE_TTL, function () use ($word, $normalizedLocale) {
                return $this->getStemmer($normalizedLocale)->stem($word);
            });
    }

    /**
     * Stem multiple words at once.
     *
     * @param array<int, string> $words The words to stem
     * @param string $locale The language locale
     * @return array<int, string> The stemmed words
     *
     * @example
     * ```php
     * $stemmer->stemBatch(['running', 'jumped', 'swimming']);
     * // ["run", "jump", "swim"]
     * ```
     */
    public function stemBatch(array $words, string $locale = 'en'): array
    {
        return array_map(fn ($word) => $this->stem($word, $locale), $words);
    }

    /**
     * Stem a phrase (multi-word keyword).
     *
     * Splits the phrase into words, stems each, and rejoins.
     *
     * @param string $phrase The phrase to stem
     * @param string $locale The language locale
     * @return string The stemmed phrase
     *
     * @example
     * ```php
     * $stemmer->stemPhrase('content marketing strategies');
     * // "content market strategi"
     * ```
     */
    public function stemPhrase(string $phrase, string $locale = 'en'): string
    {
        $words = preg_split('/\s+/', mb_strtolower(trim($phrase)));

        if (empty($words)) {
            return '';
        }

        return implode(' ', $this->stemBatch($words, $locale));
    }

    /**
     * Get or create a stemmer instance for a locale.
     *
     * @param string $locale The normalized locale code
     * @return StemmerInterface The stemmer instance
     */
    protected function getStemmer(string $locale): StemmerInterface
    {
        if (! isset($this->stemmers[$locale])) {
            try {
                $this->stemmers[$locale] = StemmerFactory::create($locale);
            } catch (\Exception $e) {
                Log::warning("Unknown locale '{$locale}' for stemming, using English fallback", [
                    'locale' => $locale,
                    'exception' => $e->getMessage(),
                ]);
                $this->stemmers[$locale] = StemmerFactory::create('en');
            }
        }

        return $this->stemmers[$locale];
    }

    /**
     * Normalize a locale to a supported language code.
     *
     * @param string $locale The locale to normalize (e.g., 'en_US', 'de_AT')
     * @return string The normalized locale (e.g., 'en', 'de')
     */
    protected function normalizeLocale(string $locale): string
    {
        // Try exact match first
        if (isset($this->localeMap[$locale])) {
            return $this->localeMap[$locale];
        }

        // Try base language (e.g., "en_US" → "en")
        $baseLocale = explode('_', str_replace('-', '_', $locale))[0];
        if (isset($this->localeMap[$baseLocale])) {
            return $this->localeMap[$baseLocale];
        }

        // Fallback to English with warning
        Log::warning("Unknown locale '{$locale}' for stemming, using English fallback");

        return 'en';
    }

    /**
     * Get the cache key for a word/locale combination.
     *
     * @param string $word The word
     * @param string $locale The locale
     * @return string The cache key
     */
    protected function getCacheKey(string $word, string $locale): string
    {
        $prefix = config('seo.cache.prefix', 'seo_');

        return "{$prefix}stem:{$locale}:" . md5($word);
    }

    /**
     * Get the cache store name.
     *
     * @return string|null The cache store name
     */
    protected function getCacheStore(): ?string
    {
        return config('seo.cache.store');
    }

    /**
     * Get list of supported locales.
     *
     * @return array<int, string> Unique supported locale codes
     */
    public function getSupportedLocales(): array
    {
        return array_values(array_unique(array_values($this->localeMap)));
    }

    /**
     * Check if a locale is supported.
     *
     * @param string $locale The locale to check
     * @return bool True if supported
     */
    public function isLocaleSupported(string $locale): bool
    {
        return isset($this->localeMap[$locale])
            || isset($this->localeMap[explode('_', $locale)[0]]);
    }

    /**
     * Clear the stem cache for a specific locale or all locales.
     *
     * Note: This only clears the in-memory cache.
     * Laravel cache entries expire after 24 hours.
     *
     * @param string|null $locale The locale to clear, or null for all
     */
    public function clearCache(?string $locale = null): void
    {
        if ($locale) {
            unset($this->stemmers[$this->normalizeLocale($locale)]);
        } else {
            $this->stemmers = [];
        }
    }
}
