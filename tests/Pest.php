<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "uses()" function to bind a different classes or traits.
|
*/

uses(
    Fibonoir\LaravelSEO\Tests\TestCase::class,
)->in('Unit');

uses(
    Fibonoir\LaravelSEO\Tests\TestCase::class,
    Illuminate\Foundation\Testing\RefreshDatabase::class,
)->in('Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeValidSEOData', function () {
    return $this->toBeInstanceOf(\Fibonoir\LaravelSEO\Data\SEOData::class);
});

expect()->extend('toHaveReadabilityLevel', function (string $level) {
    return $this->toBeInstanceOf(\Fibonoir\LaravelSEO\Data\ReadabilityResult::class)
        ->and($this->value->level)->toBe($level);
});

/*
|--------------------------------------------------------------------------
| Rule Result Expectations
|--------------------------------------------------------------------------
*/

expect()->extend('toPassRule', function () {
    return $this->toBeInstanceOf(\Fibonoir\LaravelSEO\Data\RuleResult::class)
        ->and($this->value->status)->toBe('pass');
});

expect()->extend('toFailRule', function () {
    return $this->toBeInstanceOf(\Fibonoir\LaravelSEO\Data\RuleResult::class)
        ->and($this->value->status)->toBe('fail');
});

expect()->extend('toWarnRule', function () {
    return $this->toBeInstanceOf(\Fibonoir\LaravelSEO\Data\RuleResult::class)
        ->and($this->value->status)->toBe('warning');
});

expect()->extend('toSkipRule', function () {
    return $this->toBeInstanceOf(\Fibonoir\LaravelSEO\Data\RuleResult::class)
        ->and($this->value->status)->toBe('skip');
});

expect()->extend('toHaveRuleScore', function (int $expectedScore) {
    return $this->toBeInstanceOf(\Fibonoir\LaravelSEO\Data\RuleResult::class)
        ->and($this->value->score)->toBe($expectedScore);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

/**
 * Create a mock model with specified attributes.
 */
function createMockModel(array $attributes = [], ?array $seoMeta = null): \Illuminate\Database\Eloquent\Model
{
    $model = new class extends \Illuminate\Database\Eloquent\Model
    {
        protected $guarded = [];

        public ?object $seoMeta = null;

        public function setSeoMeta(?array $meta): void
        {
            if ($meta === null) {
                $this->seoMeta = null;
                return;
            }

            $this->seoMeta = (object) $meta;
        }
    };

    foreach ($attributes as $key => $value) {
        $model->setAttribute($key, $value);
    }

    if ($seoMeta !== null) {
        $model->setSeoMeta($seoMeta);
    }

    return $model;
}

/**
 * Generate sample English text for readability testing.
 */
function sampleEnglishText(int $sentences = 5): string
{
    $sampleSentences = [
        'The quick brown fox jumps over the lazy dog.',
        'Technology is rapidly advancing and changing our daily lives.',
        'Reading helps improve vocabulary and critical thinking skills.',
        'The weather forecast predicts rain for tomorrow afternoon.',
        'Learning a new language opens doors to different cultures.',
        'Exercise is essential for maintaining good physical health.',
        'Scientists continue to make groundbreaking discoveries in medicine.',
        'The internet has revolutionized how we communicate globally.',
        'Music has the power to evoke strong emotional responses.',
        'Education is the foundation for personal and professional growth.',
    ];

    return implode(' ', array_slice($sampleSentences, 0, min($sentences, count($sampleSentences))));
}

/**
 * Generate sample Italian text for readability testing.
 */
function sampleItalianText(int $sentences = 5): string
{
    $sampleSentences = [
        'Il sole splende luminoso nel cielo azzurro.',
        'La tecnologia sta rapidamente cambiando le nostre vite.',
        'La lettura aiuta a migliorare il vocabolario.',
        'Le previsioni indicano pioggia per domani pomeriggio.',
        'Imparare una nuova lingua apre nuove opportunità.',
        "L'esercizio fisico è essenziale per la salute.",
        'Gli scienziati continuano a fare scoperte importanti.',
        'Internet ha rivoluzionato il modo di comunicare.',
        'La musica ha il potere di evocare emozioni.',
        "L'istruzione è la base della crescita personale.",
    ];

    return implode(' ', array_slice($sampleSentences, 0, min($sentences, count($sampleSentences))));
}

/**
 * Build an AnalysisContext for testing rules.
 *
 * @param array<string, mixed> $overrides Values to override defaults
 * @return \Fibonoir\LaravelSEO\Data\AnalysisContext
 */
function buildAnalysisContext(array $overrides = []): \Fibonoir\LaravelSEO\Data\AnalysisContext
{
    $stemmer = app(\Fibonoir\LaravelSEO\Support\Stemmer::class);
    $tokenizer = new \Fibonoir\LaravelSEO\Support\Tokenizer();

    $defaults = [
        'title' => 'Test Page Title for SEO Analysis',
        'description' => 'This is a test meta description for the page.',
        'content' => '',
        'htmlContent' => '',
        'tokens' => [],
        'stemmedTokens' => [],
        'focusKeywords' => [],
        'headings' => ['h1' => [], 'h2' => [], 'h3' => []],
        'links' => [],
        'images' => [],
        'wordCount' => 0,
        'sentenceCount' => 0,
        'paragraphCount' => 0,
        'locale' => 'en',
        'url' => null,
        'robots' => null,
        'canonical' => null,
        'ogImage' => null,
        'htmlLang' => null,
        'headHtml' => null,
        'ogImageBroken' => null,
        'brokenLinks' => null,
        'brokenImages' => null,
    ];

    $merged = array_merge($defaults, $overrides);

    // Auto-generate tokens and stemmed tokens from content if not provided
    if (! empty($merged['content']) && empty($merged['tokens'])) {
        $merged['tokens'] = $tokenizer->tokenize($merged['content']);
        $merged['wordCount'] = count($merged['tokens']);
        $merged['sentenceCount'] = count($tokenizer->splitSentences($merged['content']));
        $merged['paragraphCount'] = max(1, count($tokenizer->splitParagraphs($merged['content'])));
    }

    if (! empty($merged['tokens']) && empty($merged['stemmedTokens'])) {
        $merged['stemmedTokens'] = $stemmer->stemBatch($merged['tokens'], $merged['locale']);
    }

    // Process focus keywords to add stemmed versions
    if (! empty($merged['focusKeywords'])) {
        $merged['focusKeywords'] = array_map(function ($kw) use ($stemmer, $merged) {
            if (is_string($kw)) {
                return [
                    'original' => $kw,
                    'stemmed' => $stemmer->stemPhrase($kw, $merged['locale']),
                    'is_primary' => true,
                ];
            }
            if (! isset($kw['stemmed'])) {
                $kw['stemmed'] = $stemmer->stemPhrase($kw['original'] ?? $kw['keyword'], $merged['locale']);
            }
            if (! isset($kw['original']) && isset($kw['keyword'])) {
                $kw['original'] = $kw['keyword'];
            }
            return $kw;
        }, $merged['focusKeywords']);
    }

    return new \Fibonoir\LaravelSEO\Data\AnalysisContext(
        title: $merged['title'],
        description: $merged['description'],
        content: $merged['content'],
        htmlContent: $merged['htmlContent'],
        tokens: $merged['tokens'],
        stemmedTokens: $merged['stemmedTokens'],
        focusKeywords: $merged['focusKeywords'],
        headings: $merged['headings'],
        links: $merged['links'],
        images: $merged['images'],
        wordCount: $merged['wordCount'],
        sentenceCount: $merged['sentenceCount'],
        paragraphCount: $merged['paragraphCount'],
        locale: $merged['locale'],
        url: $merged['url'],
        robots: $merged['robots'],
        canonical: $merged['canonical'],
        ogImage: $merged['ogImage'],
        htmlLang: $merged['htmlLang'],
        headHtml: $merged['headHtml'],
        ogImageBroken: $merged['ogImageBroken'],
        brokenLinks: $merged['brokenLinks'],
        brokenImages: $merged['brokenImages'],
    );
}

/**
 * Generate content with specific word count.
 *
 * @param int $wordCount Target word count
 * @param string $keyword Optional keyword to include at specific density
 * @param float $density Keyword density (0.0 - 1.0)
 * @return string Generated content
 */
function generateContentWithWordCount(int $wordCount, ?string $keyword = null, float $density = 0.0): string
{
    $filler = [
        'the',
        'content',
        'page',
        'website',
        'information',
        'article',
        'helpful',
        'useful',
        'important',
        'great',
        'best',
        'quality',
        'discover',
        'learn',
        'understand',
        'explore',
        'find',
        'read',
        'about',
        'with',
        'from',
        'this',
        'that',
        'which',
        'your',
        'online',
        'digital',
        'modern',
        'effective',
        'successful',
    ];

    $words = [];
    $keywordOccurrences = $keyword ? (int) floor($wordCount * $density) : 0;
    $keywordInterval = $keywordOccurrences > 0 ? (int) floor($wordCount / $keywordOccurrences) : 0;

    for ($i = 0; $i < $wordCount; $i++) {
        // Insert keyword at intervals
        if ($keyword && $keywordOccurrences > 0 && $keywordInterval > 0 && $i % $keywordInterval === 0) {
            $words[] = $keyword;
            $keywordOccurrences--;
        } else {
            $words[] = $filler[array_rand($filler)];
        }
    }

    // Break into sentences (roughly every 15 words)
    $sentences = [];
    $currentSentence = [];

    foreach ($words as $i => $word) {
        $currentSentence[] = $word;
        if (count($currentSentence) >= 15 || $i === count($words) - 1) {
            $sentences[] = ucfirst(implode(' ', $currentSentence)) . '.';
            $currentSentence = [];
        }
    }

    return implode(' ', $sentences);
}
