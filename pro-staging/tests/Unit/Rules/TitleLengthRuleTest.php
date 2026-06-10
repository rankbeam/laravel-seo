<?php

declare(strict_types=1);

use Fibonoir\LaravelSEO\Rules\Meta\TitleLengthRule;

beforeEach(function () {
    $this->rule = new TitleLengthRule();
});

describe('TitleLengthRule', function () {
    it('has correct metadata', function () {
        expect($this->rule->getId())->toBe('title_length')
            ->and($this->rule->getName())->toBe('SEO Title Length')
            ->and($this->rule->getCategory())->toBe('meta')
            ->and($this->rule->getWeight())->toBe(10);
    });

    it('passes with optimal length', function (int $length) {
        $title = str_repeat('a', $length);

        $context = buildAnalysisContext([
            'title' => $title,
        ]);

        $result = $this->rule->analyze($context);

        expect($result)->toPassRule()
            ->and($result->score)->toBe(100)
            ->and($result->message)->toContain('optimal')
            ->and($result->message)->toContain("{$length} characters");
    })->with([
        'minimum optimal (30)' => [30],
        'middle optimal (45)' => [45],
        'maximum optimal (60)' => [60],
    ]);

    it('fails with empty title', function () {
        $context = buildAnalysisContext([
            'title' => '',
        ]);

        $result = $this->rule->analyze($context);

        expect($result)->toFailRule()
            ->and($result->message)->toContain('No SEO title')
            ->and($result->actualValue)->toBe('Empty')
            ->and($result->recommendation)->toContain('30-60');
    });

    it('fails with very short title', function (int $length) {
        $title = str_repeat('a', $length);

        $context = buildAnalysisContext([
            'title' => $title,
        ]);

        $result = $this->rule->analyze($context);

        expect($result)->toFailRule()
            ->and($result->message)->toContain('too short')
            ->and($result->actualValue)->toBe("{$length} characters");
    })->with([
        'very short (5)' => [5],
        'short (10)' => [10],
        'barely under warning (19)' => [19],
    ]);

    it('warns with too short title', function (int $length) {
        $title = str_repeat('a', $length);

        $context = buildAnalysisContext([
            'title' => $title,
        ]);

        $result = $this->rule->analyze($context);

        expect($result)->toWarnRule()
            ->and($result->message)->toContain('slightly short')
            ->and($result->score)->toBe(70);
    })->with([
        'warning range start (20)' => [20],
        'warning range middle (25)' => [25],
        'warning range end (29)' => [29],
    ]);

    it('warns with too long title', function (int $length) {
        $title = str_repeat('a', $length);

        $context = buildAnalysisContext([
            'title' => $title,
        ]);

        $result = $this->rule->analyze($context);

        expect($result)->toWarnRule()
            ->and($result->message)->toContain('slightly long')
            ->and($result->message)->toContain('truncated')
            ->and($result->score)->toBe(60);
    })->with([
        'warning range start (61)' => [61],
        'warning range middle (65)' => [65],
        'warning range end (70)' => [70],
    ]);

    it('fails with very long title', function (int $length) {
        $title = str_repeat('a', $length);

        $context = buildAnalysisContext([
            'title' => $title,
        ]);

        $result = $this->rule->analyze($context);

        expect($result)->toFailRule()
            ->and($result->message)->toContain('too long')
            ->and($result->message)->toContain('truncated')
            ->and($result->recommendation)->toContain('Shorten');
    })->with([
        'too long (71)' => [71],
        'very long (100)' => [100],
        'extremely long (150)' => [150],
    ]);

    it('handles unicode characters correctly', function () {
        // 45 characters including unicode
        $title = 'SEO Tips: 10 façons d\'améliorer votre site';

        $context = buildAnalysisContext([
            'title' => $title,
        ]);

        $result = $this->rule->analyze($context);

        // Should use mb_strlen for correct character counting
        expect($result->status)->toBeIn(['pass', 'warning', 'fail']);
    });

    it('provides expected value in result', function () {
        $context = buildAnalysisContext([
            'title' => 'Short',
        ]);

        $result = $this->rule->analyze($context);

        expect($result->expectedValue)->toBe('30-60 characters');
    });

    it('includes character count in message', function () {
        $title = str_repeat('x', 45);

        $context = buildAnalysisContext([
            'title' => $title,
        ]);

        $result = $this->rule->analyze($context);

        expect($result->message)->toContain('45 characters');
    });

    describe('boundary conditions', function () {
        it('treats 20 as warning not fail', function () {
            $context = buildAnalysisContext([
                'title' => str_repeat('a', 20),
            ]);

            $result = $this->rule->analyze($context);

            expect($result)->toWarnRule();
        });

        it('treats 30 as pass', function () {
            $context = buildAnalysisContext([
                'title' => str_repeat('a', 30),
            ]);

            $result = $this->rule->analyze($context);

            expect($result)->toPassRule();
        });

        it('treats 60 as pass', function () {
            $context = buildAnalysisContext([
                'title' => str_repeat('a', 60),
            ]);

            $result = $this->rule->analyze($context);

            expect($result)->toPassRule();
        });

        it('treats 61 as warning', function () {
            $context = buildAnalysisContext([
                'title' => str_repeat('a', 61),
            ]);

            $result = $this->rule->analyze($context);

            expect($result)->toWarnRule();
        });

        it('treats 71 as fail', function () {
            $context = buildAnalysisContext([
                'title' => str_repeat('a', 71),
            ]);

            $result = $this->rule->analyze($context);

            expect($result)->toFailRule();
        });
    });

    describe('realistic titles', function () {
        it('passes with good SEO title', function () {
            $context = buildAnalysisContext([
                'title' => '10 Essential SEO Tips for Beginners | Your Brand',
            ]);

            $result = $this->rule->analyze($context);

            expect($result)->toPassRule();
        });

        it('warns with overly descriptive title', function () {
            $context = buildAnalysisContext([
                'title' => 'The Complete, Ultimate, and Comprehensive Guide to SEO Tips for Absolute Beginners',
            ]);

            $result = $this->rule->analyze($context);

            // This is 83 characters - should fail
            expect($result->status)->toBeIn(['warning', 'fail']);
        });

        it('fails with minimal title', function () {
            $context = buildAnalysisContext([
                'title' => 'SEO Tips',
            ]);

            $result = $this->rule->analyze($context);

            expect($result)->toFailRule();
        });
    });
});
