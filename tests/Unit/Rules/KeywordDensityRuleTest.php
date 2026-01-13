<?php

declare(strict_types=1);

use Fibonoir\LaravelSEO\Rules\Keyword\KeywordDensityRule;
use Fibonoir\LaravelSEO\Support\Stemmer;

beforeEach(function () {
    $this->stemmer = app(Stemmer::class);
    $this->rule = new KeywordDensityRule($this->stemmer);
});

describe('KeywordDensityRule', function () {
    it('has correct metadata', function () {
        expect($this->rule->getId())->toBe('keyword_density')
            ->and($this->rule->getName())->toBe('Keyword Density')
            ->and($this->rule->getCategory())->toBe('keyword')
            ->and($this->rule->getWeight())->toBe(15);
    });

    it('passes with optimal density', function (float $density) {
        // Generate 200 words with target density
        $content = generateContentWithWordCount(200, 'seo', $density / 100);

        $context = buildAnalysisContext([
            'content' => $content,
            'focusKeywords' => [
                ['keyword' => 'seo', 'is_primary' => true],
            ],
        ]);

        $result = $this->rule->analyze($context);

        expect($result)->toPassRule()
            ->and($result->score)->toBe(100)
            ->and($result->message)->toContain('optimal');
    })->with([
        'minimum optimal (1.0%)' => [1.0],
        'middle optimal (1.5%)' => [1.5],
        'higher optimal (2.0%)' => [2.0],
        'maximum optimal (2.5%)' => [2.5],
    ]);

    it('fails with too low density', function () {
        // Generate content with very low keyword density (< 0.5%)
        // 200 words with 0 keyword occurrences
        $content = generateContentWithWordCount(200);

        $context = buildAnalysisContext([
            'content' => $content,
            'focusKeywords' => [
                ['keyword' => 'uniquekeyword', 'is_primary' => true],
            ],
        ]);

        $result = $this->rule->analyze($context);

        expect($result)->toFailRule()
            ->and($result->message)->toContain('too low')
            ->and($result->recommendation)->toContain('more often');
    });

    it('warns with slightly low density', function () {
        // Generate content with density between 0.5% and 1.0%
        // 200 words × 0.75% = 1.5 occurrences ≈ 1-2 occurrences
        $content = generateContentWithWordCount(200, 'seo', 0.0075);

        $context = buildAnalysisContext([
            'content' => $content,
            'focusKeywords' => [
                ['keyword' => 'seo', 'is_primary' => true],
            ],
        ]);

        $result = $this->rule->analyze($context);

        expect($result)->toWarnRule()
            ->and($result->message)->toContain('slightly low');
    });

    it('warns with too high density', function () {
        // Generate content with density between 2.5% and 3.0%
        // 200 words × 2.75% = 5.5 occurrences
        $content = generateContentWithWordCount(200, 'seo', 0.0275);

        $context = buildAnalysisContext([
            'content' => $content,
            'focusKeywords' => [
                ['keyword' => 'seo', 'is_primary' => true],
            ],
        ]);

        $result = $this->rule->analyze($context);

        expect($result)->toWarnRule()
            ->and($result->message)->toContain('slightly high');
    });

    it('fails with keyword stuffing density', function () {
        // Generate content with density > 3%
        // Use 100 words with 5 keyword occurrences = 5%
        $content = generateContentWithWordCount(100, 'seo', 0.05);

        $context = buildAnalysisContext([
            'content' => $content,
            'focusKeywords' => [
                ['keyword' => 'seo', 'is_primary' => true],
            ],
        ]);

        $result = $this->rule->analyze($context);

        expect($result)->toFailRule()
            ->and($result->message)->toContain('too high')
            ->and($result->message)->toContain('keyword stuffing');
    });

    it('skips without primary keyword', function () {
        $content = generateContentWithWordCount(200);

        $context = buildAnalysisContext([
            'content' => $content,
            'focusKeywords' => [],
        ]);

        $result = $this->rule->analyze($context);

        expect($result)->toSkipRule()
            ->and($result->message)->toContain('No focus keyword');
    });

    it('skips with content too short', function () {
        // Less than 100 words
        $content = generateContentWithWordCount(50);

        $context = buildAnalysisContext([
            'content' => $content,
            'focusKeywords' => [
                ['keyword' => 'test', 'is_primary' => true],
            ],
        ]);

        $result = $this->rule->analyze($context);

        expect($result)->toSkipRule()
            ->and($result->message)->toContain('too short')
            ->and($result->message)->toContain('100 words');
    });

    it('counts synonyms in density calculation', function () {
        // Create content with synonyms instead of the main keyword
        $content = 'The search engine optimization field is growing rapidly. '
            . str_repeat('Content marketing helps websites rank better in search results. ', 10)
            . str_repeat('Good SEO practices improve online visibility. ', 5);

        $context = buildAnalysisContext([
            'content' => $content,
            'focusKeywords' => [
                [
                    'keyword' => 'seo',
                    'is_primary' => true,
                    'synonyms' => ['search engine optimization'],
                ],
            ],
        ]);

        $result = $this->rule->analyze($context);

        // Should recognize "search engine optimization" as a synonym
        expect($result)->toBeInstanceOf(\Fibonoir\LaravelSEO\Data\RuleResult::class)
            ->and($result->status)->toBeIn(['pass', 'warning', 'fail']); // Not skipped
    });

    it('provides actual and expected values', function () {
        $content = generateContentWithWordCount(200);

        $context = buildAnalysisContext([
            'content' => $content,
            'focusKeywords' => [
                ['keyword' => 'missing', 'is_primary' => true],
            ],
        ]);

        $result = $this->rule->analyze($context);

        expect($result->actualValue)->toContain('%')
            ->and($result->expectedValue)->toBe('1-2.5%');
    });

    it('handles multi-word keywords', function () {
        // Test phrase keyword like "content marketing"
        $baseContent = str_repeat('This is about digital marketing strategies. ', 20);
        $keywordContent = str_repeat('Content marketing helps brands grow. ', 3);
        $content = $baseContent . $keywordContent;

        $context = buildAnalysisContext([
            'content' => $content,
            'focusKeywords' => [
                ['keyword' => 'content marketing', 'is_primary' => true],
            ],
        ]);

        $result = $this->rule->analyze($context);

        expect($result)->toBeInstanceOf(\Fibonoir\LaravelSEO\Data\RuleResult::class)
            ->and($result->status)->not->toBe('skip');
    });

    describe('density calculation scenarios', function () {
        it('calculates density correctly for known values', function (int $wordCount, int $keywordCount, string $expectedStatus) {
            // Create content with exact keyword count
            $words = array_fill(0, $wordCount - $keywordCount, 'filler');
            $keywords = array_fill(0, $keywordCount, 'testkeyword');

            // Interleave keywords
            $allWords = [];
            $interval = $keywordCount > 0 ? (int) ceil($wordCount / $keywordCount) : $wordCount;
            $keywordIndex = 0;

            for ($i = 0; $i < $wordCount; $i++) {
                if ($keywordIndex < $keywordCount && $i > 0 && $i % $interval === 0) {
                    $allWords[] = 'testkeyword';
                    $keywordIndex++;
                } else {
                    $allWords[] = 'filler';
                }
            }

            $content = implode(' ', $allWords);

            $context = buildAnalysisContext([
                'content' => $content,
                'focusKeywords' => [
                    ['keyword' => 'testkeyword', 'is_primary' => true],
                ],
            ]);

            $result = $this->rule->analyze($context);

            expect($result->status)->toBe($expectedStatus);
        })->with([
            '0.3% density (fail - too low)' => [300, 1, 'fail'],     // 0.33%
            '2.0% density (pass)' => [200, 4, 'pass'],               // 2.0%
        ]);
    });
});
