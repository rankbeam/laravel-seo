<?php

declare(strict_types=1);

namespace Fibonoir\LaravelSEO\Support;

/**
 * Transition words for readability analysis.
 *
 * Transition words and phrases help connect ideas and improve the flow
 * of writing. Good content typically has 30%+ of sentences containing
 * or starting with transition words.
 *
 * ## Categories
 *
 * - **addition**: Adding information (also, furthermore, moreover)
 * - **contrast**: Showing opposition (however, but, nevertheless)
 * - **comparison**: Comparing ideas (similarly, likewise)
 * - **cause_effect**: Showing causation (because, therefore, thus)
 * - **emphasis**: Adding emphasis (indeed, certainly, especially)
 * - **example**: Giving examples (for example, such as, including)
 * - **sequence**: Ordering ideas (first, then, finally)
 * - **conclusion**: Summarizing (in conclusion, ultimately)
 * - **time**: Temporal relations (now, meanwhile, eventually)
 * - **clarification**: Explaining (in other words, that is)
 *
 * ## Usage
 *
 * ```php
 * $transitionWords = app(TransitionWords::class);
 *
 * // Get all transition words
 * $all = $transitionWords->getAll('en');
 *
 * // Get by category
 * $contrast = $transitionWords->getByCategory('contrast', 'en');
 *
 * // Check if sentence contains transition
 * if ($transitionWords->containsTransition('However, this is different.', 'en')) {
 *     // Has transition
 * }
 *
 * // Analyze text
 * $stats = $transitionWords->analyzeSentences($text, 'en');
 * // ["total" => 10, "with_transitions" => 4, "percentage" => 40.0]
 * ```
 *
 * @see \Fibonoir\LaravelSEO\Support\ReadabilityCalculator For readability scoring
 * @see \Fibonoir\LaravelSEO\Services\Analyzer\ContentAnalyzer For usage context
 */
class TransitionWords
{
    /**
     * Transition words organized by locale and category.
     *
     * @var array<string, array<string, array<int, string>>>
     */
    protected array $transitions = [
        'en' => [
            'addition' => [
                'also', 'and', 'besides', 'furthermore', 'in addition', 'moreover',
                'too', 'as well', 'additionally', 'along with', 'not only', 'but also',
                'equally important', 'first', 'second', 'third', 'finally', 'last',
                'first of all', 'in the first place', 'to begin with', 'what is more',
                'coupled with', 'together with', 'plus', 'equally', 'including',
            ],
            'contrast' => [
                'but', 'however', 'yet', 'still', 'nevertheless', 'nonetheless',
                'although', 'though', 'even though', 'while', 'whereas', 'on the other hand',
                'in contrast', 'conversely', 'instead', 'rather', 'despite', 'in spite of',
                'on the contrary', 'notwithstanding', 'unlike', 'different from',
                'at the same time', 'be that as it may', 'even so', 'all the same',
            ],
            'comparison' => [
                'similarly', 'likewise', 'in the same way', 'just as', 'as', 'compared to',
                'in comparison', 'equally', 'by the same token', 'correspondingly',
                'in like manner', 'in a similar fashion', 'analogous to', 'comparable to',
            ],
            'cause_effect' => [
                'because', 'since', 'so', 'therefore', 'thus', 'hence', 'consequently',
                'as a result', 'for this reason', 'accordingly', 'due to', 'owing to',
                'for', 'as', 'so that', 'in order that', 'as a consequence', 'on account of',
                'this means that', 'it follows that', 'resulting in', 'leading to',
            ],
            'emphasis' => [
                'indeed', 'in fact', 'certainly', 'surely', 'undoubtedly', 'without doubt',
                'of course', 'truly', 'especially', 'particularly', 'notably', 'above all',
                'most importantly', 'primarily', 'chiefly', 'definitely', 'clearly',
                'obviously', 'absolutely', 'unquestionably', 'without question',
                'as a matter of fact', 'to be sure', 'by all means', 'in particular',
            ],
            'example' => [
                'for example', 'for instance', 'such as', 'specifically', 'namely',
                'to illustrate', 'in particular', 'including', 'like', 'as an example',
                'as an illustration', 'to demonstrate', 'consider', 'take for example',
                'to show', 'to name a few', 'especially', 'particularly', 'in this case',
            ],
            'sequence' => [
                'first', 'second', 'third', 'next', 'then', 'after', 'before',
                'finally', 'subsequently', 'meanwhile', 'in the meantime', 'at the same time',
                'simultaneously', 'afterward', 'previously', 'formerly', 'initially',
                'to begin with', 'to start with', 'in the end', 'at last', 'lastly',
                'following this', 'at this point', 'hereafter', 'thereafter',
            ],
            'time' => [
                'now', 'today', 'currently', 'presently', 'at present', 'at this time',
                'meanwhile', 'in the meantime', 'soon', 'later', 'eventually', 'finally',
                'immediately', 'suddenly', 'gradually', 'recently', 'formerly', 'previously',
                'afterward', 'afterwards', 'before', 'earlier', 'during', 'while',
                'when', 'whenever', 'until', 'as soon as', 'since then', 'by now',
                'in the past', 'in the future', 'someday', 'one day', 'at once',
            ],
            'conclusion' => [
                'in conclusion', 'to conclude', 'in summary', 'to summarize', 'in short',
                'briefly', 'overall', 'ultimately', 'all in all', 'on the whole',
                'in the end', 'finally', 'lastly', 'to sum up', 'in brief', 'in essence',
                'as shown above', 'as has been noted', 'to put it briefly', 'in closing',
                'taking everything into account', 'all things considered',
            ],
            'clarification' => [
                'in other words', 'that is', 'that is to say', 'to clarify', 'to put it simply',
                'to explain', 'meaning', 'namely', 'specifically', 'to be more precise',
                'put differently', 'simply put', 'in simpler terms', 'to rephrase',
                'what this means is', 'to put it another way', 'i.e.', 'e.g.',
            ],
        ],
        'it' => [
            'addition' => [
                'inoltre', 'anche', 'pure', 'in aggiunta', 'per di più', 'oltre a ciò',
                'non solo', 'ma anche', 'altrettanto', 'primo', 'secondo', 'terzo', 'infine',
                'in primo luogo', 'in secondo luogo', 'allo stesso modo', 'ugualmente',
                'parimenti', 'così come', 'oltre che', 'in più', 'ancora', 'oltretutto',
            ],
            'contrast' => [
                'ma', 'però', 'tuttavia', 'nonostante', 'malgrado', 'sebbene', 'benché',
                'anche se', 'mentre', 'invece', 'al contrario', 'daltro canto', 'piuttosto',
                'nondimeno', 'ciononostante', 'eppure', 'comunque', 'in ogni caso',
                'per contro', 'diversamente', 'contrariamente', 'a differenza di',
            ],
            'comparison' => [
                'similmente', 'allo stesso modo', 'così come', 'come', 'paragonato a',
                'in confronto', 'ugualmente', 'analogamente', 'in maniera simile',
                'nella stessa maniera', 'alla stessa maniera', 'rispetto a',
            ],
            'cause_effect' => [
                'perché', 'poiché', 'siccome', 'quindi', 'dunque', 'perciò', 'pertanto',
                'di conseguenza', 'per questo motivo', 'a causa di', 'dato che',
                'visto che', 'in quanto', 'cosicché', 'ne consegue che', 'per cui',
                'ragion per cui', 'ecco perché', 'in virtù di', 'per effetto di',
            ],
            'emphasis' => [
                'infatti', 'in effetti', 'certamente', 'sicuramente', 'senza dubbio',
                'naturalmente', 'veramente', 'soprattutto', 'particolarmente', 'specialmente',
                'chiaramente', 'evidentemente', 'ovviamente', 'indubbiamente', 'davvero',
                'di fatto', 'in realtà', 'a dire il vero', 'senzaltro', 'assolutamente',
            ],
            'example' => [
                'per esempio', 'ad esempio', 'come', 'quale', 'nello specifico',
                'in particolare', 'cioè', 'tipo', 'tra cui', 'fra cui', 'come nel caso di',
                'a titolo di esempio', 'per citare un esempio', 'basti pensare a',
            ],
            'sequence' => [
                'prima', 'dopo', 'poi', 'successivamente', 'nel frattempo', 'infine',
                'inizialmente', 'in seguito', 'quindi', 'alla fine', 'in principio',
                'al termine', 'per concludere', 'a seguire', 'di seguito', 'anzitutto',
            ],
            'time' => [
                'ora', 'adesso', 'attualmente', 'oggi', 'al momento', 'in questo momento',
                'nel frattempo', 'presto', 'più tardi', 'alla fine', 'eventualmente',
                'immediatamente', 'improvvisamente', 'gradualmente', 'recentemente',
                'precedentemente', 'prima', 'dopo', 'durante', 'mentre', 'quando',
                'finora', 'da allora', 'in passato', 'in futuro', 'un giorno',
            ],
            'conclusion' => [
                'in conclusione', 'per concludere', 'in sintesi', 'riassumendo', 'in breve',
                'complessivamente', 'in definitiva', 'alla fine', 'in sostanza',
                'tutto sommato', 'nel complesso', 'in ultima analisi', 'per finire',
                'tirando le somme', 'insomma', 'alla fin fine', 'concludendo',
            ],
            'clarification' => [
                'in altre parole', 'cioè', 'vale a dire', 'per chiarire', 'in parole semplici',
                'ossia', 'ovvero', 'intendo dire', 'voglio dire', 'per essere precisi',
                'più precisamente', 'per meglio dire', 'in pratica', 'sostanzialmente',
            ],
        ],
    ];

    /**
     * Get all transition words for a locale (flat list).
     *
     * @param string $locale The locale code (e.g., 'en', 'it')
     * @return array<int, string> All transition words for the locale
     *
     * @example
     * ```php
     * $all = $transitionWords->getAll('en');
     * // ["also", "and", "besides", "furthermore", ...]
     * ```
     */
    public function getAll(string $locale = 'en'): array
    {
        $normalizedLocale = explode('_', str_replace('-', '_', $locale))[0];
        $localeTransitions = $this->transitions[$normalizedLocale] ?? $this->transitions['en'];

        return array_values(array_unique(array_merge(...array_values($localeTransitions))));
    }

    /**
     * Get transition words by category.
     *
     * @param string $category The category (e.g., 'addition', 'contrast')
     * @param string $locale The locale code
     * @return array<int, string> Transition words in the category
     *
     * @example
     * ```php
     * $contrast = $transitionWords->getByCategory('contrast', 'en');
     * // ["but", "however", "yet", "still", ...]
     * ```
     */
    public function getByCategory(string $category, string $locale = 'en'): array
    {
        $normalizedLocale = explode('_', str_replace('-', '_', $locale))[0];
        $localeTransitions = $this->transitions[$normalizedLocale] ?? $this->transitions['en'];

        return $localeTransitions[$category] ?? [];
    }

    /**
     * Get all available categories.
     *
     * @param string $locale The locale code
     * @return array<int, string> List of category names
     */
    public function getCategories(string $locale = 'en'): array
    {
        $normalizedLocale = explode('_', str_replace('-', '_', $locale))[0];
        $localeTransitions = $this->transitions[$normalizedLocale] ?? $this->transitions['en'];

        return array_keys($localeTransitions);
    }

    /**
     * Check if a sentence contains a transition word.
     *
     * Checks both at the beginning of the sentence and within it.
     *
     * @param string $sentence The sentence to check
     * @param string $locale The locale code
     * @return bool True if the sentence contains a transition word
     *
     * @example
     * ```php
     * $transitionWords->containsTransition('However, this is different.', 'en'); // true
     * $transitionWords->containsTransition('The cat sat on the mat.', 'en'); // false
     * ```
     */
    public function containsTransition(string $sentence, string $locale = 'en'): bool
    {
        $sentence = mb_strtolower(trim($sentence));
        $transitions = $this->getAll($locale);

        // Sort by length descending to match longer phrases first
        usort($transitions, fn ($a, $b) => mb_strlen($b) - mb_strlen($a));

        foreach ($transitions as $transition) {
            $lowerTransition = mb_strtolower($transition);

            // Check if sentence starts with transition
            if (str_starts_with($sentence, $lowerTransition . ' ') ||
                str_starts_with($sentence, $lowerTransition . ',')) {
                return true;
            }

            // Check if transition appears as a phrase (word boundaries)
            $pattern = '/\b' . preg_quote($lowerTransition, '/') . '\b/u';
            if (preg_match($pattern, $sentence)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Find which transition word is in a sentence.
     *
     * @param string $sentence The sentence to check
     * @param string $locale The locale code
     * @return string|null The found transition word or null
     */
    public function findTransition(string $sentence, string $locale = 'en'): ?string
    {
        $sentence = mb_strtolower(trim($sentence));
        $transitions = $this->getAll($locale);

        // Sort by length descending to match longer phrases first
        usort($transitions, fn ($a, $b) => mb_strlen($b) - mb_strlen($a));

        foreach ($transitions as $transition) {
            $lowerTransition = mb_strtolower($transition);
            $pattern = '/\b' . preg_quote($lowerTransition, '/') . '\b/u';

            if (preg_match($pattern, $sentence)) {
                return $transition;
            }
        }

        return null;
    }

    /**
     * Count sentences with transition words in text.
     *
     * @param string $text The text to analyze
     * @param string $locale The locale code
     * @return array{total: int, with_transitions: int, percentage: float}
     *
     * @example
     * ```php
     * $stats = $transitionWords->analyzeSentences($text, 'en');
     * // ["total" => 10, "with_transitions" => 4, "percentage" => 40.0]
     * ```
     */
    public function analyzeSentences(string $text, string $locale = 'en'): array
    {
        // Handle common abbreviations to avoid false sentence splits
        $text = preg_replace('/\b(Mr|Mrs|Ms|Dr|Prof|Sr|Jr|vs|etc|i\.e|e\.g)\./i', '$1<PERIOD>', $text);

        // Split into sentences
        $sentences = preg_split('/[.!?]+\s*/u', $text, -1, PREG_SPLIT_NO_EMPTY);

        // Restore abbreviations
        $sentences = array_map(fn ($s) => str_replace('<PERIOD>', '.', trim($s)), $sentences);
        $sentences = array_filter($sentences, fn ($s) => mb_strlen($s) > 3);

        $total = count($sentences);
        $withTransitions = 0;

        foreach ($sentences as $sentence) {
            if ($this->containsTransition($sentence, $locale)) {
                $withTransitions++;
            }
        }

        return [
            'total' => $total,
            'with_transitions' => $withTransitions,
            'percentage' => $total > 0 ? round(($withTransitions / $total) * 100, 1) : 0.0,
        ];
    }

    /**
     * Get detailed analysis with sentences categorized.
     *
     * @param string $text The text to analyze
     * @param string $locale The locale code
     * @return array{sentences_with: array, sentences_without: array, stats: array}
     */
    public function analyzeDetailed(string $text, string $locale = 'en'): array
    {
        // Handle abbreviations
        $text = preg_replace('/\b(Mr|Mrs|Ms|Dr|Prof|Sr|Jr|vs|etc|i\.e|e\.g)\./i', '$1<PERIOD>', $text);

        $sentences = preg_split('/[.!?]+\s*/u', $text, -1, PREG_SPLIT_NO_EMPTY);
        $sentences = array_map(fn ($s) => str_replace('<PERIOD>', '.', trim($s)), $sentences);
        $sentences = array_filter($sentences, fn ($s) => mb_strlen($s) > 3);

        $with = [];
        $without = [];

        foreach ($sentences as $sentence) {
            $transition = $this->findTransition($sentence, $locale);

            if ($transition) {
                $with[] = [
                    'sentence' => $sentence,
                    'transition' => $transition,
                ];
            } else {
                $without[] = $sentence;
            }
        }

        $total = count($sentences);

        return [
            'sentences_with' => $with,
            'sentences_without' => $without,
            'stats' => [
                'total' => $total,
                'with_transitions' => count($with),
                'without_transitions' => count($without),
                'percentage' => $total > 0 ? round((count($with) / $total) * 100, 1) : 0.0,
            ],
        ];
    }

    /**
     * Get available locales.
     *
     * @return array<int, string> List of supported locales
     */
    public function getAvailableLocales(): array
    {
        return array_keys($this->transitions);
    }
}
