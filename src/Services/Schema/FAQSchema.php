<?php

declare(strict_types=1);

namespace Fibonoir\LaravelSEO\Services\Schema;

/**
 * Builder for FAQ JSON-LD schema.
 *
 * FAQ schema helps search engines understand Q&A content
 * and can result in rich snippets.
 *
 * ## Usage
 * ```php
 * $schema = (new FAQSchema())
 *     ->addQuestion('What is Laravel?', 'Laravel is a PHP framework.')
 *     ->addQuestion('Is it free?', 'Yes, Laravel is open source.')
 *     ->toArray();
 * ```
 *
 * ## From Array
 * ```php
 * $schema = FAQSchema::fromArray([
 *     ['question' => 'What is Laravel?', 'answer' => 'A PHP framework.'],
 *     ['question' => 'Is it free?', 'answer' => 'Yes, it is open source.'],
 * ]);
 * ```
 */
class FAQSchema
{
    /**
     * @var array<int, array{question: string, answer: string}>
     */
    protected array $questions = [];

    /**
     * Add a question and answer.
     */
    public function addQuestion(string $question, string $answer): self
    {
        $this->questions[] = [
            'question' => $question,
            'answer' => $answer,
        ];

        return $this;
    }

    /**
     * Set all questions at once.
     *
     * @param array<int, array{question: string, answer: string}> $questions
     */
    public function setQuestions(array $questions): self
    {
        $this->questions = $questions;

        return $this;
    }

    /**
     * Create from array of Q&A pairs.
     *
     * @param array<int, array{question: string, answer: string}> $faqs
     */
    public static function fromArray(array $faqs): self
    {
        $schema = new self();
        $schema->setQuestions($faqs);

        return $schema;
    }

    /**
     * Convert to array.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $mainEntity = [];

        foreach ($this->questions as $qa) {
            $mainEntity[] = [
                '@type' => 'Question',
                'name' => $qa['question'],
                'acceptedAnswer' => [
                    '@type' => 'Answer',
                    'text' => $qa['answer'],
                ],
            ];
        }

        return [
            '@context' => 'https://schema.org',
            '@type' => 'FAQPage',
            'mainEntity' => $mainEntity,
        ];
    }
}
