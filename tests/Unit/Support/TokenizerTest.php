<?php

declare(strict_types=1);

use Fibonoir\LaravelSEO\Support\Tokenizer;

beforeEach(function () {
    $this->tokenizer = new Tokenizer();
});

describe('Tokenizer', function () {
    it('tokenizes simple text', function () {
        $text = 'Hello world this is a test';

        $tokens = $this->tokenizer->tokenize($text);

        expect($tokens)->toBeArray()
            ->and($tokens)->toContain('hello')
            ->and($tokens)->toContain('world')
            ->and($tokens)->toContain('this')
            ->and($tokens)->toContain('test');
    });

    it('converts text to lowercase', function () {
        $text = 'Hello WORLD This IS Test';

        $tokens = $this->tokenizer->tokenize($text);

        expect($tokens)->each->toMatch('/^[a-z]+$/');
    });

    it('removes punctuation', function () {
        $text = 'Hello, world! How are you? Fine.';

        $tokens = $this->tokenizer->tokenize($text);

        expect($tokens)->not->toContain(',')
            ->and($tokens)->not->toContain('!')
            ->and($tokens)->not->toContain('?')
            ->and($tokens)->not->toContain('.')
            ->and($tokens)->toContain('hello')
            ->and($tokens)->toContain('world')
            ->and($tokens)->toContain('how')
            ->and($tokens)->toContain('are')
            ->and($tokens)->toContain('you')
            ->and($tokens)->toContain('fine');
    });

    it('handles contractions', function () {
        $text = "Don't stop believing! You're amazing.";

        $tokens = $this->tokenizer->tokenize($text);

        // Contractions should be merged: don't → dont, you're → youre
        expect($tokens)->toContain('dont')
            ->and($tokens)->toContain('stop')
            ->and($tokens)->toContain('believing')
            ->and($tokens)->toContain('youre')
            ->and($tokens)->toContain('amazing');
    });

    it('filters short words', function () {
        $text = 'I am a test of the tokenizer';

        $tokens = $this->tokenizer->tokenize($text);

        // Default min word length is 2
        expect($tokens)->not->toContain('i')
            ->and($tokens)->not->toContain('a')
            ->and($tokens)->toContain('am')
            ->and($tokens)->toContain('of')
            ->and($tokens)->toContain('test')
            ->and($tokens)->toContain('the')
            ->and($tokens)->toContain('tokenizer');
    });

    it('filters pure numbers', function () {
        $text = 'The year 2024 has 12 months and 365 days';

        $tokens = $this->tokenizer->tokenize($text);

        expect($tokens)->not->toContain('2024')
            ->and($tokens)->not->toContain('12')
            ->and($tokens)->not->toContain('365')
            ->and($tokens)->toContain('year')
            ->and($tokens)->toContain('months')
            ->and($tokens)->toContain('days');
    });

    it('tokenizes with positions', function () {
        $text = 'Hello World';

        $tokens = $this->tokenizer->tokenizeWithPositions($text);

        expect($tokens)->toBeArray()
            ->and($tokens)->toHaveCount(2)
            ->and($tokens[0])->toMatchArray([
                'word' => 'hello',
                'position' => 0,
            ])
            ->and($tokens[1])->toMatchArray([
                'word' => 'world',
                'position' => 6,
            ]);
    });

    it('tokenizes with positions preserves original case', function () {
        $text = 'Hello WORLD';

        $tokens = $this->tokenizer->tokenizeWithPositions($text);

        expect($tokens[0]['original'])->toBe('Hello')
            ->and($tokens[1]['original'])->toBe('WORLD');
    });

    it('handles HTML entities', function () {
        $text = 'Hello &amp; world &lt;test&gt;';

        $tokens = $this->tokenizer->tokenize($text);

        expect($tokens)->toContain('hello')
            ->and($tokens)->toContain('world')
            ->and($tokens)->toContain('test');
    });

    it('strips HTML tags', function () {
        $text = '<p>Hello <strong>world</strong></p>';

        $tokens = $this->tokenizer->tokenize($text);

        expect($tokens)->toBe(['hello', 'world']);
    });

    it('handles unicode characters', function () {
        $text = 'Héllo wörld naïve café résumé';

        $tokens = $this->tokenizer->tokenize($text);

        expect($tokens)->toContain('héllo')
            ->and($tokens)->toContain('wörld')
            ->and($tokens)->toContain('naïve')
            ->and($tokens)->toContain('café')
            ->and($tokens)->toContain('résumé');
    });

    it('handles empty text', function () {
        expect($this->tokenizer->tokenize(''))->toBe([])
            ->and($this->tokenizer->tokenize('   '))->toBe([]);
    });

    it('counts words correctly', function () {
        $text = 'This is a simple test with seven words';

        // "a" is filtered (length 1), so 7 words remain
        $count = $this->tokenizer->countWords($text);

        expect($count)->toBe(7);
    });

    it('counts words excluding short words', function () {
        $text = 'I am a test';

        // "I" (1 char) and "a" (1 char) filtered
        $count = $this->tokenizer->countWords($text);

        expect($count)->toBe(2); // "am", "test"
    });

    it('gets word frequencies', function () {
        $text = 'hello world hello test hello world';

        $frequencies = $this->tokenizer->getWordFrequencies($text);

        expect($frequencies)->toBeArray()
            ->and($frequencies['hello'])->toBe(3)
            ->and($frequencies['world'])->toBe(2)
            ->and($frequencies['test'])->toBe(1);
    });

    it('sorts frequencies by count descending', function () {
        $text = 'alpha beta beta gamma gamma gamma';

        $frequencies = $this->tokenizer->getWordFrequencies($text);
        $keys = array_keys($frequencies);

        expect($keys[0])->toBe('gamma')
            ->and($keys[1])->toBe('beta')
            ->and($keys[2])->toBe('alpha');
    });

    it('splits text into sentences', function () {
        $text = 'Hello world. How are you? Fine!';

        $sentences = $this->tokenizer->splitSentences($text);

        expect($sentences)->toHaveCount(3)
            ->and($sentences[0])->toBe('Hello world')
            ->and($sentences[1])->toBe('How are you')
            ->and($sentences[2])->toBe('Fine');
    });

    it('handles abbreviations when splitting sentences', function () {
        $text = 'Dr. Smith met Mr. Jones. They discussed etc.';

        $sentences = $this->tokenizer->splitSentences($text);

        // Abbreviations should not cause false splits
        expect($sentences)->toHaveCount(2);
    });

    it('splits text into paragraphs', function () {
        $text = "First paragraph.\n\nSecond paragraph.\n\nThird paragraph.";

        $paragraphs = $this->tokenizer->splitParagraphs($text);

        expect($paragraphs)->toHaveCount(3)
            ->and($paragraphs[0])->toBe('First paragraph.')
            ->and($paragraphs[1])->toBe('Second paragraph.')
            ->and($paragraphs[2])->toBe('Third paragraph.');
    });

    it('handles HTML paragraph tags', function () {
        $text = '<p>First paragraph.</p><p>Second paragraph.</p>';

        $paragraphs = $this->tokenizer->splitParagraphs($text);

        expect($paragraphs)->toHaveCount(2);
    });

    it('allows setting minimum word length', function () {
        $text = 'I am a test of the tokenizer';

        // Set min word length to 3
        $this->tokenizer->setMinWordLength(3);
        $tokens = $this->tokenizer->tokenize($text);

        expect($tokens)->not->toContain('am')
            ->and($tokens)->not->toContain('of')
            ->and($tokens)->not->toContain('the')
            ->and($tokens)->toContain('test')
            ->and($tokens)->toContain('tokenizer');
    });

    it('gets current minimum word length', function () {
        expect($this->tokenizer->getMinWordLength())->toBe(2);

        $this->tokenizer->setMinWordLength(5);

        expect($this->tokenizer->getMinWordLength())->toBe(5);
    });

    it('enforces minimum word length of 1', function () {
        $this->tokenizer->setMinWordLength(0);

        expect($this->tokenizer->getMinWordLength())->toBe(1);

        $this->tokenizer->setMinWordLength(-5);

        expect($this->tokenizer->getMinWordLength())->toBe(1);
    });

    it('extracts n-grams (bigrams)', function () {
        $text = 'hello beautiful world today';

        $ngrams = $this->tokenizer->getNgrams($text, 2);

        expect($ngrams)->toHaveCount(3)
            ->and($ngrams)->toContain('hello beautiful')
            ->and($ngrams)->toContain('beautiful world')
            ->and($ngrams)->toContain('world today');
    });

    it('extracts n-grams (trigrams)', function () {
        $text = 'one two three four five';

        $ngrams = $this->tokenizer->getNgrams($text, 3);

        expect($ngrams)->toHaveCount(3)
            ->and($ngrams)->toContain('one two three')
            ->and($ngrams)->toContain('two three four')
            ->and($ngrams)->toContain('three four five');
    });

    it('returns empty array for n-grams when text too short', function () {
        $text = 'hello';

        $ngrams = $this->tokenizer->getNgrams($text, 2);

        expect($ngrams)->toBe([]);
    });

    it('supports method chaining for setMinWordLength', function () {
        $result = $this->tokenizer->setMinWordLength(3);

        expect($result)->toBeInstanceOf(Tokenizer::class);
    });
});
