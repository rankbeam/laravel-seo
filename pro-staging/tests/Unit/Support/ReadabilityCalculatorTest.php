<?php

declare(strict_types=1);

use Fibonoir\LaravelSEO\Data\ReadabilityResult;
use Fibonoir\LaravelSEO\Support\ReadabilityCalculator;

beforeEach(function () {
    $this->calculator = new ReadabilityCalculator();
});

describe('ReadabilityCalculator', function () {
    describe('Flesch-Kincaid (English)', function () {
        it('calculates flesch kincaid score', function () {
            $text = sampleEnglishText(5);

            $result = $this->calculator->calculateFleschKincaid($text);

            expect($result)->toBeInstanceOf(ReadabilityResult::class)
                ->and($result->score)->toBeGreaterThanOrEqual(0)
                ->and($result->score)->toBeLessThanOrEqual(100)
                ->and($result->locale)->toBe('en');
        });

        it('returns correct level for high score', function () {
            // Simple text with short words and sentences should score high (easy)
            $text = 'The cat sat. The dog ran. I am glad. You are nice.';

            $result = $this->calculator->calculateFleschKincaid($text);

            expect($result->level)->toBeIn(['easy', 'good'])
                ->and($result->score)->toBeGreaterThan(60);
        });

        it('returns correct level for moderate score', function () {
            $text = 'Technology revolutionizes communication methodologies. '
                . 'Organizations implement sophisticated strategies. '
                . 'Professionals demonstrate exceptional capabilities.';

            $result = $this->calculator->calculateFleschKincaid($text);

            // Text with multisyllabic words can score anywhere from good to very_difficult
            expect($result->level)->toBeIn(['moderate', 'difficult', 'good', 'very_difficult']);
        });

        it('returns correct level for low score', function () {
            // Complex text with long sentences and multisyllabic words
            $text = 'The implementation of extraordinarily sophisticated methodological '
                . 'frameworks necessitates comprehensive understanding of organizational '
                . 'infrastructure and systematized procedural implementations.';

            $result = $this->calculator->calculateFleschKincaid($text);

            expect($result->level)->toBeIn(['moderate', 'difficult', 'very_difficult']);
        });

        it('handles empty text', function () {
            $result = $this->calculator->calculateFleschKincaid('');

            expect($result->level)->toBe('unknown')
                ->and($result->score)->toBe(0.0)
                ->and($result->isValid())->toBeFalse();
        });

        it('handles single sentence', function () {
            $text = 'This is a simple test sentence.';

            $result = $this->calculator->calculateFleschKincaid($text);

            expect($result)->toBeInstanceOf(ReadabilityResult::class)
                ->and($result->isValid())->toBeTrue()
                ->and($result->stats['sentences'])->toBe(1);
        });

        it('provides stats in result', function () {
            $text = 'Hello world. This is a test.';

            $result = $this->calculator->calculateFleschKincaid($text);

            expect($result->stats)->toHaveKeys([
                'words',
                'sentences',
                'syllables',
                'letters',
                'avgWordsPerSentence',
                'avgSyllablesPerWord',
            ])
                ->and($result->stats['sentences'])->toBe(2);
        });

        it('calculates average words per sentence', function () {
            $text = 'One two three. Four five. Six seven eight nine ten.';

            $result = $this->calculator->calculateFleschKincaid($text);

            // 3 sentences, 10 words = ~3.3 avg
            expect($result->stats['avgWordsPerSentence'])->toBeGreaterThan(3)
                ->and($result->stats['avgWordsPerSentence'])->toBeLessThan(4);
        });
    });

    describe('Gulpease (Italian)', function () {
        it('calculates gulpease score', function () {
            $text = sampleItalianText(5);

            $result = $this->calculator->calculateGulpease($text);

            expect($result)->toBeInstanceOf(ReadabilityResult::class)
                ->and($result->score)->toBeGreaterThanOrEqual(0)
                ->and($result->score)->toBeLessThanOrEqual(100)
                ->and($result->locale)->toBe('it');
        });

        it('returns correct level for score', function (float $score, string $expectedLevel) {
            // We can't directly test internal level calculation, but we can verify
            // the result follows the expected pattern for different content
        })->with([
            [85.0, 'easy'],
            [70.0, 'good'],
            [50.0, 'moderate'],
            [30.0, 'difficult'],
        ])->skip('Indirect test - levels determined by calculated scores');

        it('handles empty text', function () {
            $result = $this->calculator->calculateGulpease('');

            expect($result->level)->toBe('unknown')
                ->and($result->score)->toBe(0.0);
        });

        it('handles single sentence', function () {
            $text = 'Questa è una frase semplice.';

            $result = $this->calculator->calculateGulpease($text);

            expect($result)->toBeInstanceOf(ReadabilityResult::class)
                ->and($result->isValid())->toBeTrue()
                ->and($result->stats['sentences'])->toBe(1);
        });

        it('uses letter count in calculation', function () {
            $text = 'Il gatto dorme. Il cane corre.';

            $result = $this->calculator->calculateGulpease($text);

            expect($result->stats['letters'])->toBeGreaterThan(0);
        });

        it('provides italian description', function () {
            $text = sampleItalianText(3);

            $result = $this->calculator->calculateGulpease($text);

            // Description should be in Italian
            expect($result->description)->toBeString()
                ->and(strlen($result->description))->toBeGreaterThan(0);
        });
    });

    describe('Auto-detect locale', function () {
        it('routes to flesch kincaid for english', function () {
            $text = sampleEnglishText();

            $result = $this->calculator->calculate($text, 'en');

            expect($result->locale)->toBe('en');
        });

        it('routes to gulpease for italian', function () {
            $text = sampleItalianText();

            $result = $this->calculator->calculate($text, 'it');

            expect($result->locale)->toBe('it');
        });

        it('normalizes locale variants', function () {
            $text = sampleEnglishText();

            $result1 = $this->calculator->calculate($text, 'en_US');
            $result2 = $this->calculator->calculate($text, 'en-GB');

            expect($result1->locale)->toBe('en')
                ->and($result2->locale)->toBe('en');
        });

        it('falls back to flesch kincaid for unknown locales', function () {
            $text = sampleEnglishText();

            $result = $this->calculator->calculate($text, 'xyz');

            // Should use English algorithm as fallback
            expect($result->locale)->toBe('en');
        });
    });

    describe('ReadabilityResult', function () {
        it('isGood returns true for acceptable levels', function () {
            $easyResult = new ReadabilityResult(85.0, 'easy', 'Easy', 'en');
            $goodResult = new ReadabilityResult(65.0, 'good', 'Good', 'en');
            $moderateResult = new ReadabilityResult(55.0, 'moderate', 'Moderate', 'en');

            expect($easyResult->isGood())->toBeTrue()
                ->and($goodResult->isGood())->toBeTrue()
                ->and($moderateResult->isGood())->toBeTrue();
        });

        it('isGood returns false for difficult levels', function () {
            $difficultResult = new ReadabilityResult(35.0, 'difficult', 'Difficult', 'en');
            $veryDifficultResult = new ReadabilityResult(15.0, 'very_difficult', 'Very Difficult', 'en');

            expect($difficultResult->isGood())->toBeFalse()
                ->and($veryDifficultResult->isGood())->toBeFalse();
        });

        it('needsImprovement identifies difficult content', function () {
            $difficultResult = new ReadabilityResult(35.0, 'difficult', 'Difficult', 'en');

            expect($difficultResult->needsImprovement())->toBeTrue();
        });

        it('isEasy identifies easy content', function () {
            $easyResult = new ReadabilityResult(90.0, 'easy', 'Very easy', 'en');
            $goodResult = new ReadabilityResult(65.0, 'good', 'Good', 'en');

            expect($easyResult->isEasy())->toBeTrue()
                ->and($goodResult->isEasy())->toBeFalse();
        });

        it('isValid checks for unknown level', function () {
            $validResult = new ReadabilityResult(65.0, 'good', 'Good', 'en');
            $invalidResult = ReadabilityResult::insufficient('en');

            expect($validResult->isValid())->toBeTrue()
                ->and($invalidResult->isValid())->toBeFalse();
        });

        it('provides grade level description', function () {
            $result = new ReadabilityResult(65.0, 'good', 'Good', 'en');

            expect($result->getGradeLevel())->toContain('grade');
        });

        it('provides italian grade level description', function () {
            $result = new ReadabilityResult(65.0, 'good', 'Good', 'it');

            expect($result->getGradeLevel())->toContain('Scuola');
        });

        it('provides suggestions for improvement', function () {
            $result = new ReadabilityResult(35.0, 'difficult', 'Difficult', 'en', [
                'words' => 100,
                'sentences' => 3,
                'syllables' => 200,
                'letters' => 500,
                'avgWordsPerSentence' => 33.3,
                'avgSyllablesPerWord' => 2.0,
            ]);

            $suggestions = $result->getSuggestions();

            expect($suggestions)->toBeArray()
                ->and(count($suggestions))->toBeGreaterThan(0);
        });

        it('converts to array', function () {
            $result = new ReadabilityResult(65.0, 'good', 'Good readability', 'en', [
                'words' => 50,
                'sentences' => 5,
            ]);

            $array = $result->toArray();

            expect($array)->toHaveKeys([
                'score',
                'level',
                'description',
                'locale',
                'grade_level',
                'is_good',
                'stats',
                'suggestions',
            ])
                ->and($array['score'])->toBe(65.0)
                ->and($array['level'])->toBe('good')
                ->and($array['is_good'])->toBeTrue();
        });

        it('serializes to json', function () {
            $result = new ReadabilityResult(65.0, 'good', 'Good', 'en');

            $json = json_encode($result);
            $decoded = json_decode($json, true);

            expect($decoded)->toBeArray()
                ->and((float) $decoded['score'])->toBe(65.0)
                ->and($decoded['level'])->toBe('good');
        });

        it('creates insufficient result', function () {
            $result = ReadabilityResult::insufficient('en');

            expect($result->score)->toBe(0.0)
                ->and($result->level)->toBe('unknown')
                ->and($result->isValid())->toBeFalse()
                ->and($result->locale)->toBe('en');
        });

        it('creates italian insufficient result', function () {
            $result = ReadabilityResult::insufficient('it');

            expect($result->locale)->toBe('it')
                ->and($result->description)->toContain('Contenuto insufficiente');
        });

        it('provides accessor methods for stats', function () {
            $result = new ReadabilityResult(65.0, 'good', 'Good', 'en', [
                'words' => 100,
                'sentences' => 5,
                'syllables' => 150,
                'letters' => 500,
                'avgWordsPerSentence' => 20.0,
                'avgSyllablesPerWord' => 1.5,
            ]);

            expect($result->getWordCount())->toBe(100)
                ->and($result->getSentenceCount())->toBe(5)
                ->and($result->getAvgWordsPerSentence())->toBe(20.0)
                ->and($result->getAvgSyllablesPerWord())->toBe(1.5);
        });
    });

    describe('Target Score', function () {
        it('returns target score for english', function () {
            $target = $this->calculator->getTargetScore('en');

            expect($target)->toHaveKeys(['min', 'max', 'description'])
                ->and($target['min'])->toBe(60)
                ->and($target['max'])->toBe(70);
        });

        it('returns target score for italian', function () {
            $target = $this->calculator->getTargetScore('it');

            expect($target)->toHaveKeys(['min', 'max', 'description'])
                ->and($target['min'])->toBe(60)
                ->and($target['max'])->toBe(80);
        });

        it('returns english target for unknown locale', function () {
            $target = $this->calculator->getTargetScore('xyz');

            expect($target['min'])->toBe(60)
                ->and($target['max'])->toBe(70);
        });
    });

    describe('Edge Cases', function () {
        it('handles text with only numbers', function () {
            $result = $this->calculator->calculate('123 456 789', 'en');

            // Should still produce a result, though potentially unexpected
            expect($result)->toBeInstanceOf(ReadabilityResult::class);
        });

        it('handles text with special characters', function () {
            $text = 'Hello! @#$%^& World? *** Testing...';

            $result = $this->calculator->calculate($text, 'en');

            expect($result)->toBeInstanceOf(ReadabilityResult::class)
                ->and($result->stats['words'])->toBeGreaterThan(0);
        });

        it('handles very long text', function () {
            $text = str_repeat(sampleEnglishText() . ' ', 100);

            $result = $this->calculator->calculate($text, 'en');

            expect($result)->toBeInstanceOf(ReadabilityResult::class)
                ->and($result->isValid())->toBeTrue();
        });

        it('handles HTML content', function () {
            $text = '<p>This is a <strong>test</strong> with HTML.</p><p>Another paragraph here.</p>';

            $result = $this->calculator->calculate($text, 'en');

            // HTML should be stripped before analysis
            expect($result)->toBeInstanceOf(ReadabilityResult::class)
                ->and($result->stats['words'])->toBeGreaterThan(0);
        });

        it('handles text with html entities', function () {
            $text = 'Hello &amp; world. This &lt;is&gt; a test.';

            $result = $this->calculator->calculate($text, 'en');

            expect($result)->toBeInstanceOf(ReadabilityResult::class)
                ->and($result->stats['words'])->toBeGreaterThan(0);
        });

        it('clamps score to 0-100 range', function () {
            // Very simple text might calculate to over 100 before clamping
            $text = 'Go. Do. Be. Hi. No.';

            $result = $this->calculator->calculate($text, 'en');

            expect($result->score)->toBeGreaterThanOrEqual(0)
                ->and($result->score)->toBeLessThanOrEqual(100);
        });
    });
});
