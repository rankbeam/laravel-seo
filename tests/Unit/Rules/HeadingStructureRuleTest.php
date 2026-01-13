<?php

declare(strict_types=1);

use Fibonoir\LaravelSEO\Rules\Content\HeadingStructureRule;

beforeEach(function () {
    $this->rule = new HeadingStructureRule();
});

describe('HeadingStructureRule', function () {
    it('has correct metadata', function () {
        expect($this->rule->getId())->toBe('heading_structure')
            ->and($this->rule->getName())->toBe('Heading Structure')
            ->and($this->rule->getCategory())->toBe('content')
            ->and($this->rule->getWeight())->toBe(5);
    });

    it('passes with single h1', function () {
        $context = buildAnalysisContext([
            'headings' => [
                'h1' => ['Main Page Title'],
                'h2' => ['Section One', 'Section Two'],
                'h3' => [],
            ],
            'wordCount' => 200,
        ]);

        $result = $this->rule->analyze($context);

        expect($result)->toPassRule()
            ->and($result->score)->toBe(100)
            ->and($result->message)->toContain('good')
            ->and($result->message)->toContain('1 H1');
    });

    it('fails with multiple h1 headings', function () {
        $context = buildAnalysisContext([
            'headings' => [
                'h1' => ['First H1', 'Second H1', 'Third H1'],
                'h2' => [],
                'h3' => [],
            ],
            'wordCount' => 200,
        ]);

        $result = $this->rule->analyze($context);

        expect($result)->toFailRule()
            ->and($result->message)->toContain('Multiple H1')
            ->and($result->message)->toContain('3')
            ->and($result->recommendation)->toContain('one H1');
    });

    it('fails with no h1 heading', function () {
        $context = buildAnalysisContext([
            'headings' => [
                'h1' => [],
                'h2' => ['Section One', 'Section Two'],
                'h3' => [],
            ],
            'wordCount' => 200,
        ]);

        $result = $this->rule->analyze($context);

        expect($result)->toFailRule()
            ->and($result->message)->toContain('No H1')
            ->and($result->recommendation)->toContain('Add an H1');
    });

    it('warns about missing subheadings in long content', function () {
        $context = buildAnalysisContext([
            'headings' => [
                'h1' => ['Main Title'],
                'h2' => [],  // No H2 headings
                'h3' => [],
            ],
            'wordCount' => 600,  // Long content (>500 words)
        ]);

        $result = $this->rule->analyze($context);

        expect($result->status)->toBeIn(['warning', 'fail'])
            ->and($result->message)->toContain('H2 subheadings')
            ->and($result->recommendation)->toContain('Break up');
    });

    it('passes short content without h2 headings', function () {
        $context = buildAnalysisContext([
            'headings' => [
                'h1' => ['Main Title'],
                'h2' => [],  // No H2 headings
                'h3' => [],
            ],
            'wordCount' => 200,  // Short content (<500 words)
        ]);

        $result = $this->rule->analyze($context);

        expect($result)->toPassRule();
    });

    it('requires at least 2 h2 for long content', function () {
        $context = buildAnalysisContext([
            'headings' => [
                'h1' => ['Main Title'],
                'h2' => ['Only One Section'],  // Only 1 H2
                'h3' => [],
            ],
            'wordCount' => 600,  // Long content
        ]);

        $result = $this->rule->analyze($context);

        expect($result->status)->toBeIn(['warning', 'fail'])
            ->and($result->message)->toContain('at least 2 H2');
    });

    it('passes long content with sufficient h2 headings', function () {
        $context = buildAnalysisContext([
            'headings' => [
                'h1' => ['Main Title'],
                'h2' => ['Section One', 'Section Two'],
                'h3' => [],
            ],
            'wordCount' => 600,  // Long content
        ]);

        $result = $this->rule->analyze($context);

        expect($result)->toPassRule();
    });

    it('warns about h3 without h2', function () {
        $context = buildAnalysisContext([
            'headings' => [
                'h1' => ['Main Title'],
                'h2' => [],
                'h3' => ['Subsection One', 'Subsection Two'],
            ],
            'wordCount' => 200,
        ]);

        $result = $this->rule->analyze($context);

        expect($result->status)->toBeIn(['warning', 'fail'])
            ->and($result->message)->toContain('H3')
            ->and($result->message)->toContain('without')
            ->and($result->recommendation)->toContain('H2');
    });

    it('detects empty headings', function () {
        $context = buildAnalysisContext([
            'headings' => [
                'h1' => ['Main Title'],
                'h2' => ['', 'Section Two'],  // Empty H2
                'h3' => ['  '],  // Whitespace-only H3
            ],
            'wordCount' => 200,
        ]);

        $result = $this->rule->analyze($context);

        expect($result->status)->toBeIn(['warning', 'fail'])
            ->and($result->message)->toContain('empty heading');
    });

    it('builds summary of heading structure', function () {
        $context = buildAnalysisContext([
            'headings' => [
                'h1' => ['Title'],
                'h2' => ['Section 1', 'Section 2', 'Section 3'],
                'h3' => ['Sub 1', 'Sub 2'],
            ],
            'wordCount' => 200,
        ]);

        $result = $this->rule->analyze($context);

        expect($result)->toPassRule()
            ->and($result->message)->toContain('1 H1')
            ->and($result->message)->toContain('3 H2')
            ->and($result->message)->toContain('2 H3');
    });

    describe('score calculation', function () {
        it('gives 100 for perfect structure', function () {
            $context = buildAnalysisContext([
                'headings' => [
                    'h1' => ['Main Title'],
                    'h2' => ['Section 1', 'Section 2'],
                    'h3' => ['Subsection 1'],
                ],
                'wordCount' => 200,
            ]);

            $result = $this->rule->analyze($context);

            expect($result->score)->toBe(100);
        });

        it('penalizes missing h1 heavily', function () {
            $context = buildAnalysisContext([
                'headings' => [
                    'h1' => [],
                    'h2' => ['Section 1', 'Section 2'],
                    'h3' => [],
                ],
                'wordCount' => 200,
            ]);

            $result = $this->rule->analyze($context);

            expect($result->score)->toBeLessThanOrEqual(50);
        });

        it('penalizes multiple h1 moderately', function () {
            $context = buildAnalysisContext([
                'headings' => [
                    'h1' => ['Title 1', 'Title 2'],
                    'h2' => [],
                    'h3' => [],
                ],
                'wordCount' => 200,
            ]);

            $result = $this->rule->analyze($context);

            expect($result->score)->toBeLessThanOrEqual(70);
        });
    });

    describe('boundary conditions', function () {
        it('treats 500 words as short content', function () {
            $context = buildAnalysisContext([
                'headings' => [
                    'h1' => ['Title'],
                    'h2' => [],
                    'h3' => [],
                ],
                'wordCount' => 500,  // Boundary
            ]);

            $result = $this->rule->analyze($context);

            // 500 words should not require H2 headings
            expect($result)->toPassRule();
        });

        it('treats 501 words as long content', function () {
            $context = buildAnalysisContext([
                'headings' => [
                    'h1' => ['Title'],
                    'h2' => [],
                    'h3' => [],
                ],
                'wordCount' => 501,  // Just over boundary
            ]);

            $result = $this->rule->analyze($context);

            // 501 words should require H2 headings
            expect($result->status)->toBeIn(['warning', 'fail']);
        });
    });

    describe('multiple issues', function () {
        it('combines multiple issues in message', function () {
            $context = buildAnalysisContext([
                'headings' => [
                    'h1' => [],  // No H1
                    'h2' => [],  // No H2 for long content
                    'h3' => ['Orphan H3'],  // H3 without H2
                ],
                'wordCount' => 600,  // Long content
            ]);

            $result = $this->rule->analyze($context);

            expect($result)->toFailRule()
                ->and($result->message)->toContain('No H1')
                ->and($result->message)->toContain('H3');
        });
    });

    describe('edge cases', function () {
        it('handles empty headings array', function () {
            $context = buildAnalysisContext([
                'headings' => [],
                'wordCount' => 200,
            ]);

            $result = $this->rule->analyze($context);

            expect($result)->toFailRule()
                ->and($result->message)->toContain('No H1');
        });

        it('handles headings with only whitespace', function () {
            $context = buildAnalysisContext([
                'headings' => [
                    'h1' => ['   '],  // Whitespace only
                    'h2' => ["\t\n"],  // Tabs and newlines
                    'h3' => [],
                ],
                'wordCount' => 200,
            ]);

            $result = $this->rule->analyze($context);

            // Should detect empty headings
            expect($result->message)->toContain('empty');
        });
    });
});
