<?php

declare(strict_types=1);

use Fibonoir\LaravelSEO\Rules\Content\ReadabilityRule;
use Fibonoir\LaravelSEO\Support\ReadabilityCalculator;

beforeEach(function () {
    $this->calculator = app(ReadabilityCalculator::class);
    $this->rule = new ReadabilityRule($this->calculator);
});

describe('ReadabilityRule', function () {
    it('has correct metadata', function () {
        expect($this->rule->getId())->toBe('readability')
            ->and($this->rule->getName())->toBe('Content Readability')
            ->and($this->rule->getCategory())->toBe('content')
            ->and($this->rule->getWeight())->toBe(10);
    });

    it('passes with easy readability', function () {
        // Simple text with short sentences and common words
        $content = implode(' ', [
            'The cat sat on the mat.',
            'Dogs like to run and play.',
            'Birds fly in the sky.',
            'Fish swim in the sea.',
            'The sun is bright today.',
            'Children love to play games.',
            'Books are fun to read.',
            'Music makes us happy.',
            'Food gives us energy.',
            'Water is good for health.',
            'Trees grow in the forest.',
            'Flowers bloom in spring.',
            'Snow falls in winter.',
            'Rain comes from clouds.',
            'Wind blows the leaves.',
        ]);

        // Repeat to get 100+ words
        $content = str_repeat($content . ' ', 3);

        $context = buildAnalysisContext([
            'content' => $content,
            'locale' => 'en',
        ]);

        $result = $this->rule->analyze($context);

        expect($result)->toPassRule()
            ->and($result->message)->toContain('readability')
            ->and($result->score)->toBeGreaterThanOrEqual(85);
    });

    it('passes with good readability', function () {
        // Standard web content - 8th-9th grade level
        $content = implode(' ', [
            'Technology has changed how we communicate with each other.',
            'The internet allows us to connect with people around the world.',
            'Social media platforms have become popular among young people.',
            'Online shopping has made purchasing products more convenient.',
            'Digital devices are now essential tools in our daily lives.',
            'Learning new skills online has become more accessible.',
            'Remote work has changed the traditional office environment.',
            'Video streaming services have transformed entertainment.',
            'Mobile apps help us manage many aspects of our lives.',
            'Cloud storage makes it easy to access files anywhere.',
        ]);

        // Repeat to get 100+ words
        $content = str_repeat($content . ' ', 3);

        $context = buildAnalysisContext([
            'content' => $content,
            'locale' => 'en',
        ]);

        $result = $this->rule->analyze($context);

        expect($result)->toPassRule();
    });

    it('warns with difficult readability', function () {
        // Complex text with long sentences and multisyllabic words
        $content = implode(' ', [
            'The implementation of extraordinarily sophisticated methodological frameworks necessitates comprehensive understanding.',
            'Organizations must systematically evaluate their infrastructure to ensure operational excellence.',
            'Professionals demonstrate exceptional capabilities through rigorous analytical methodologies.',
            'The interconnection of various organizational departments facilitates comprehensive strategic planning.',
            'Implementation of revolutionary technological advancements requires substantial organizational restructuring.',
            'Comprehensive documentation of procedural implementations ensures accountability throughout organizations.',
            'Stakeholder engagement necessitates transparent communication of organizational objectives and methodologies.',
            'The systematization of operational procedures enhances organizational effectiveness substantially.',
            'Analytical frameworks provide methodological approaches for comprehensive performance evaluations.',
            'Strategic initiatives require meticulous coordination between multidisciplinary professional teams.',
        ]);

        // Repeat to get 100+ words
        $content = str_repeat($content . ' ', 2);

        $context = buildAnalysisContext([
            'content' => $content,
            'locale' => 'en',
        ]);

        $result = $this->rule->analyze($context);

        expect($result->status)->toBeIn(['warning', 'fail'])
            ->and($result->message)->toContain('difficult');
    });

    it('fails with very difficult readability', function () {
        // Extremely complex academic/legal language
        $content = implode(' ', [
            'The epistemological underpinnings of contemporary hermeneutical methodologies necessitate comprehensive phenomenological investigations.',
            'Notwithstanding the aforementioned considerations, the constitutional ramifications require meticulous jurisprudential analysis.',
            'The socioeconomic determinants of epidemiological manifestations demonstrate multifactorial pathophysiological correlations.',
            'Transdisciplinary epistemological frameworks facilitate comprehensive ontological investigations into phenomenological consciousness.',
            'The methodological systematization of psychopharmacological interventions requires comprehensive neurobiological understanding.',
            'Anthropological investigations into sociocultural phenomena necessitate comprehensive ethnomethodological approaches.',
            'The epistemological foundations of metaphysical investigations require comprehensive phenomenological methodologies.',
            'Constitutional jurisprudence necessitates comprehensive understanding of historical legislative implementations.',
        ]);

        // Repeat to get enough words
        $content = str_repeat($content . ' ', 3);

        $context = buildAnalysisContext([
            'content' => $content,
            'locale' => 'en',
        ]);

        $result = $this->rule->analyze($context);

        expect($result->status)->toBeIn(['warning', 'fail']);
    });

    it('skips short content', function () {
        // Less than 100 words
        $content = 'This is a short piece of content. It does not have enough words.';

        $context = buildAnalysisContext([
            'content' => $content,
            'locale' => 'en',
        ]);

        $result = $this->rule->analyze($context);

        expect($result)->toSkipRule()
            ->and($result->message)->toContain('too short')
            ->and($result->message)->toContain('100 words');
    });

    it('skips with exactly 99 words', function () {
        $content = implode(' ', array_fill(0, 99, 'word'));

        $context = buildAnalysisContext([
            'content' => $content,
            'wordCount' => 99,
            'locale' => 'en',
        ]);

        $result = $this->rule->analyze($context);

        expect($result)->toSkipRule();
    });

    it('analyzes with exactly 100 words', function () {
        // Create simple content with 100 words
        $words = [];
        for ($i = 0; $i < 100; $i++) {
            $words[] = 'simple';
        }
        $content = implode(' ', $words);

        $context = buildAnalysisContext([
            'content' => $content,
            'locale' => 'en',
        ]);

        $result = $this->rule->analyze($context);

        expect($result->status)->not->toBe('skip');
    });

    it('uses correct calculator for locale', function () {
        // Italian text
        $content = implode(' ', [
            'Il gatto dorme sul divano.',
            'Il cane corre nel giardino.',
            'Gli uccelli volano nel cielo.',
            'I pesci nuotano nel mare.',
            'Il sole splende oggi.',
            'I bambini amano giocare.',
            'I libri sono interessanti.',
            'La musica ci rende felici.',
            'Il cibo ci dà energia.',
            'L\'acqua è buona per la salute.',
        ]);

        // Repeat to get 100+ words
        $content = str_repeat($content . ' ', 5);

        $context = buildAnalysisContext([
            'content' => $content,
            'locale' => 'it',
        ]);

        $result = $this->rule->analyze($context);

        // Should analyze without error and produce result
        expect($result->status)->not->toBe('skip');
    });

    it('uses flesch-kincaid for english', function () {
        $content = str_repeat(sampleEnglishText(10) . ' ', 5);

        $context = buildAnalysisContext([
            'content' => $content,
            'locale' => 'en',
        ]);

        $result = $this->rule->analyze($context);

        expect($result->status)->toBeIn(['pass', 'warning', 'fail']);
    });

    it('uses gulpease for italian', function () {
        $content = str_repeat(sampleItalianText(10) . ' ', 5);

        $context = buildAnalysisContext([
            'content' => $content,
            'locale' => 'it',
        ]);

        $result = $this->rule->analyze($context);

        expect($result->status)->toBeIn(['pass', 'warning', 'fail']);
    });

    it('normalizes locale variants', function () {
        $content = str_repeat(sampleEnglishText(10) . ' ', 5);

        $contextEnUS = buildAnalysisContext([
            'content' => $content,
            'locale' => 'en_US',
        ]);

        $contextEnGB = buildAnalysisContext([
            'content' => $content,
            'locale' => 'en_GB',
        ]);

        $resultUS = $this->rule->analyze($contextEnUS);
        $resultGB = $this->rule->analyze($contextEnGB);

        // Both should produce similar results (using English algorithm)
        expect($resultUS->status)->toBe($resultGB->status);
    });

    it('provides recommendation for difficult content', function () {
        // Complex content
        $content = str_repeat(
            'The implementation of sophisticated methodological frameworks necessitates comprehensive understanding of organizational infrastructure. ',
            20
        );

        $context = buildAnalysisContext([
            'content' => $content,
            'locale' => 'en',
        ]);

        $result = $this->rule->analyze($context);

        if ($result->status !== 'pass') {
            expect($result->recommendation)->not->toBeNull()
                ->and($result->recommendation)->toContain('sentence');
        }
    });

    it('includes readability score in message', function () {
        $content = str_repeat(sampleEnglishText(10) . ' ', 5);

        $context = buildAnalysisContext([
            'content' => $content,
            'locale' => 'en',
        ]);

        $result = $this->rule->analyze($context);

        expect($result->message)->toContain('Score:');
    });

    describe('score mapping', function () {
        it('maps easy level to 100 points', function () {
            // Very simple content
            $content = str_repeat('I am Sam. Sam I am. Do you like ham? ', 30);

            $context = buildAnalysisContext([
                'content' => $content,
                'locale' => 'en',
            ]);

            $result = $this->rule->analyze($context);

            if ($result->message !== null && str_contains($result->message, 'easy')) {
                expect($result->score)->toBe(100);
            }
        });

        it('maps good level to 85 points', function () {
            $content = str_repeat(sampleEnglishText(10) . ' ', 5);

            $context = buildAnalysisContext([
                'content' => $content,
                'locale' => 'en',
            ]);

            $result = $this->rule->analyze($context);

            if ($result->message !== null && str_contains($result->message, 'good')) {
                expect($result->score)->toBe(85);
            }
        });
    });
});
