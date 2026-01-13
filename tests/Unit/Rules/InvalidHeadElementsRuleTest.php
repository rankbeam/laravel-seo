<?php

declare(strict_types=1);

use Fibonoir\LaravelSEO\Rules\Technical\InvalidHeadElementsRule;

beforeEach(function () {
    $this->rule = new InvalidHeadElementsRule();
});

describe('InvalidHeadElementsRule', function () {
    it('has correct metadata', function () {
        expect($this->rule->getId())->toBe('invalid_head_elements')
            ->and($this->rule->getName())->toBe('Invalid Head Elements')
            ->and($this->rule->getCategory())->toBe('technical')
            ->and($this->rule->getWeight())->toBe(10);
    });

    it('passes with valid head', function () {
        $headHtml = <<<'HTML'
        <title>Page Title</title>
        <meta charset="utf-8">
        <meta name="description" content="Page description">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <link rel="stylesheet" href="/css/app.css">
        <link rel="canonical" href="https://example.com/page">
        <script src="/js/app.js"></script>
        <style>body { margin: 0; }</style>
        <base href="https://example.com">
        <noscript>JavaScript is required</noscript>
        HTML;

        $context = buildAnalysisContext([
            'headHtml' => $headHtml,
        ]);

        $result = $this->rule->analyze($context);

        expect($result)->toPassRule()
            ->and($result->score)->toBe(100)
            ->and($result->message)->toContain('valid');
    });

    it('fails with invalid elements', function () {
        $headHtml = <<<'HTML'
        <title>Page Title</title>
        <meta charset="utf-8">
        <div>This should not be here</div>
        <p>Neither should this</p>
        HTML;

        $context = buildAnalysisContext([
            'headHtml' => $headHtml,
        ]);

        $result = $this->rule->analyze($context);

        expect($result)->toFailRule()
            ->and($result->message)->toContain('invalid')
            ->and($result->message)->toContain('Google')
            ->and($result->actualValue)->toContain('<div>')
            ->and($result->actualValue)->toContain('<p>');
    });

    it('fails with div element', function () {
        $headHtml = <<<'HTML'
        <title>Test</title>
        <div class="hidden">Content</div>
        HTML;

        $context = buildAnalysisContext([
            'headHtml' => $headHtml,
        ]);

        $result = $this->rule->analyze($context);

        expect($result)->toFailRule()
            ->and($result->actualValue)->toContain('<div>');
    });

    it('fails with span element', function () {
        $headHtml = <<<'HTML'
        <title>Test</title>
        <span>Inline content</span>
        HTML;

        $context = buildAnalysisContext([
            'headHtml' => $headHtml,
        ]);

        $result = $this->rule->analyze($context);

        expect($result)->toFailRule()
            ->and($result->actualValue)->toContain('<span>');
    });

    it('fails with img element', function () {
        $headHtml = <<<'HTML'
        <title>Test</title>
        <img src="/logo.png" alt="Logo">
        HTML;

        $context = buildAnalysisContext([
            'headHtml' => $headHtml,
        ]);

        $result = $this->rule->analyze($context);

        expect($result)->toFailRule()
            ->and($result->actualValue)->toContain('<img>');
    });

    it('fails with anchor element', function () {
        $headHtml = <<<'HTML'
        <title>Test</title>
        <a href="https://example.com">Link</a>
        HTML;

        $context = buildAnalysisContext([
            'headHtml' => $headHtml,
        ]);

        $result = $this->rule->analyze($context);

        expect($result)->toFailRule()
            ->and($result->actualValue)->toContain('<a>');
    });

    it('fails with text content outside valid elements', function () {
        $headHtml = <<<'HTML'
        <title>Test</title>
        Random text that should not be here
        <meta name="test" content="value">
        HTML;

        $context = buildAnalysisContext([
            'headHtml' => $headHtml,
        ]);

        $result = $this->rule->analyze($context);

        expect($result)->toFailRule()
            ->and($result->actualValue)->toContain('text content');
    });

    it('ignores template content', function () {
        $headHtml = <<<'HTML'
        <title>Test</title>
        <template id="my-template">
            <div>This is template content</div>
            <p>Templates can contain anything</p>
            <img src="test.jpg">
        </template>
        <meta name="description" content="Test">
        HTML;

        $context = buildAnalysisContext([
            'headHtml' => $headHtml,
        ]);

        $result = $this->rule->analyze($context);

        expect($result)->toPassRule();
    });

    it('ignores script content', function () {
        $headHtml = <<<'HTML'
        <title>Test</title>
        <script>
            // HTML-like strings in JavaScript
            var html = '<div>This looks like HTML</div>';
            document.write('<p>Dynamic content</p>');
        </script>
        <meta name="description" content="Test">
        HTML;

        $context = buildAnalysisContext([
            'headHtml' => $headHtml,
        ]);

        $result = $this->rule->analyze($context);

        expect($result)->toPassRule();
    });

    it('ignores style content', function () {
        $headHtml = <<<'HTML'
        <title>Test</title>
        <style>
            /* Comments with HTML-like content */
            div { color: red; }
            .class > div { margin: 0; }
        </style>
        <meta name="description" content="Test">
        HTML;

        $context = buildAnalysisContext([
            'headHtml' => $headHtml,
        ]);

        $result = $this->rule->analyze($context);

        expect($result)->toPassRule();
    });

    it('skips without head html', function () {
        $context = buildAnalysisContext([
            'headHtml' => null,
        ]);

        $result = $this->rule->analyze($context);

        expect($result)->toSkipRule()
            ->and($result->message)->toContain('No head HTML');
    });

    it('skips with empty head html', function () {
        $context = buildAnalysisContext([
            'headHtml' => '',
        ]);

        $result = $this->rule->analyze($context);

        expect($result)->toSkipRule();
    });

    it('provides detailed recommendation', function () {
        $headHtml = <<<'HTML'
        <title>Test</title>
        <div>Invalid</div>
        HTML;

        $context = buildAnalysisContext([
            'headHtml' => $headHtml,
        ]);

        $result = $this->rule->analyze($context);

        expect($result->recommendation)->toContain('Remove')
            ->and($result->recommendation)->toContain('body');
    });

    it('warns about google parsing behavior', function () {
        $headHtml = <<<'HTML'
        <title>Test</title>
        <div>Invalid</div>
        HTML;

        $context = buildAnalysisContext([
            'headHtml' => $headHtml,
        ]);

        $result = $this->rule->analyze($context);

        expect($result->message)->toContain('Google')
            ->and($result->details['warning'])->toContain('ignored');
    });

    it('counts multiple invalid elements', function () {
        $headHtml = <<<'HTML'
        <title>Test</title>
        <div>First</div>
        <p>Second</p>
        <span>Third</span>
        <img src="test.jpg">
        HTML;

        $context = buildAnalysisContext([
            'headHtml' => $headHtml,
        ]);

        $result = $this->rule->analyze($context);

        expect($result)->toFailRule()
            ->and($result->message)->toContain('4 invalid');
    });

    it('lists unique invalid elements', function () {
        $headHtml = <<<'HTML'
        <title>Test</title>
        <div>First</div>
        <div>Second div</div>
        <div>Third div</div>
        HTML;

        $context = buildAnalysisContext([
            'headHtml' => $headHtml,
        ]);

        $result = $this->rule->analyze($context);

        expect($result)->toFailRule()
            ->and($result->details['invalid_elements'])->toContain('<div>');
    });

    describe('valid head elements', function () {
        it('accepts title element', function () {
            $context = buildAnalysisContext([
                'headHtml' => '<title>Page Title</title>',
            ]);

            $result = $this->rule->analyze($context);

            expect($result)->toPassRule();
        });

        it('accepts meta element', function () {
            $context = buildAnalysisContext([
                'headHtml' => '<meta name="robots" content="index,follow">',
            ]);

            $result = $this->rule->analyze($context);

            expect($result)->toPassRule();
        });

        it('accepts link element', function () {
            $context = buildAnalysisContext([
                'headHtml' => '<link rel="canonical" href="https://example.com">',
            ]);

            $result = $this->rule->analyze($context);

            expect($result)->toPassRule();
        });

        it('accepts script element', function () {
            $context = buildAnalysisContext([
                'headHtml' => '<script src="/app.js"></script>',
            ]);

            $result = $this->rule->analyze($context);

            expect($result)->toPassRule();
        });

        it('accepts style element', function () {
            $context = buildAnalysisContext([
                'headHtml' => '<style>body{margin:0}</style>',
            ]);

            $result = $this->rule->analyze($context);

            expect($result)->toPassRule();
        });

        it('accepts noscript element', function () {
            $context = buildAnalysisContext([
                'headHtml' => '<noscript>Enable JavaScript</noscript>',
            ]);

            $result = $this->rule->analyze($context);

            expect($result)->toPassRule();
        });

        it('accepts base element', function () {
            $context = buildAnalysisContext([
                'headHtml' => '<base href="https://example.com/">',
            ]);

            $result = $this->rule->analyze($context);

            expect($result)->toPassRule();
        });

        it('accepts template element', function () {
            $context = buildAnalysisContext([
                'headHtml' => '<template id="tmpl"><div>Content</div></template>',
            ]);

            $result = $this->rule->analyze($context);

            expect($result)->toPassRule();
        });
    });

    describe('common invalid elements', function () {
        it('detects br element', function () {
            $context = buildAnalysisContext([
                'headHtml' => '<title>Test</title><br>',
            ]);

            $result = $this->rule->analyze($context);

            expect($result)->toFailRule();
        });

        it('detects hr element', function () {
            $context = buildAnalysisContext([
                'headHtml' => '<title>Test</title><hr>',
            ]);

            $result = $this->rule->analyze($context);

            expect($result)->toFailRule();
        });

        it('detects header element', function () {
            $context = buildAnalysisContext([
                'headHtml' => '<title>Test</title><header>Site Header</header>',
            ]);

            $result = $this->rule->analyze($context);

            expect($result)->toFailRule();
        });

        it('detects footer element', function () {
            $context = buildAnalysisContext([
                'headHtml' => '<title>Test</title><footer>Site Footer</footer>',
            ]);

            $result = $this->rule->analyze($context);

            expect($result)->toFailRule();
        });
    });

    describe('self-closing tags', function () {
        it('detects self-closing invalid tags', function () {
            $context = buildAnalysisContext([
                'headHtml' => '<title>Test</title><img src="test.jpg" />',
            ]);

            $result = $this->rule->analyze($context);

            expect($result)->toFailRule();
        });

        it('accepts self-closing valid tags', function () {
            $context = buildAnalysisContext([
                'headHtml' => '<meta charset="utf-8" /><link rel="icon" href="/favicon.ico" />',
            ]);

            $result = $this->rule->analyze($context);

            expect($result)->toPassRule();
        });
    });
});
