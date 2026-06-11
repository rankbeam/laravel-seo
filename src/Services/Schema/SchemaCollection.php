<?php

declare(strict_types=1);

namespace Fibonoir\LaravelSEO\Services\Schema;

use Closure;

/**
 * Fluent API for building multiple JSON-LD schemas.
 *
 * Provides a convenient way to build multiple structured data
 * schemas for a single page.
 *
 * ## Usage
 * ```php
 * $schema = SchemaCollection::make()
 *     ->addArticle(fn ($s) => $s
 *         ->setHeadline('My Article')
 *         ->setAuthor('John Doe')
 *     )
 *     ->addBreadcrumb(fn ($s) => $s
 *         ->prependHome()
 *         ->addItem('Blog', '/blog', 2)
 *         ->addItem('My Article', '/blog/my-article', 3)
 *     )
 *     ->addFAQ(fn ($s) => $s
 *         ->addQuestion('What is this?', 'This is an example.')
 *     );
 *
 * // Output as script tag
 * echo $schema->toScript();
 * ```
 *
 * ## Output
 * Generates valid JSON-LD with @context and @type automatically.
 */
class SchemaCollection
{
    /**
     * json_encode flags for JSON-LD output.
     *
     * Inside a <script> element no HTML entity escaping applies, so a value
     * containing `</script>` could terminate the element early (XSS).
     * JSON_HEX_TAG encodes `<` and `>` as unicode escapes to prevent that;
     * the other JSON_HEX_* flags harden the payload for attribute contexts.
     * Same flags as TagRenderer::SCHEMA_JSON_FLAGS.
     */
    protected const JSON_FLAGS = JSON_UNESCAPED_SLASHES
        | JSON_UNESCAPED_UNICODE
        | JSON_HEX_TAG
        | JSON_HEX_AMP
        | JSON_HEX_APOS
        | JSON_HEX_QUOT;

    /**
     * Collection of schemas.
     *
     * @var array<int, array<string, mixed>>
     */
    protected array $schemas = [];

    /**
     * Create a new schema collection.
     */
    public static function make(): self
    {
        return new self();
    }

    /**
     * Add an Article schema.
     */
    public function addArticle(?Closure $callback = null): self
    {
        $schema = new ArticleSchema();

        if ($callback) {
            $callback($schema);
        }

        $this->schemas[] = $schema->toArray();

        return $this;
    }

    /**
     * Add a FAQ schema.
     */
    public function addFAQ(?Closure $callback = null): self
    {
        $schema = new FAQSchema();

        if ($callback) {
            $callback($schema);
        }

        $this->schemas[] = $schema->toArray();

        return $this;
    }

    /**
     * Add a Breadcrumb schema.
     */
    public function addBreadcrumb(?Closure $callback = null): self
    {
        $schema = new BreadcrumbSchema();

        if ($callback) {
            $callback($schema);
        }

        $this->schemas[] = $schema->toArray();

        return $this;
    }

    /**
     * Add a LocalBusiness schema.
     */
    public function addLocalBusiness(?Closure $callback = null): self
    {
        $schema = new LocalBusinessSchema();

        if ($callback) {
            $callback($schema);
        }

        $this->schemas[] = $schema->toArray();

        return $this;
    }

    /**
     * Add a Product schema.
     */
    public function addProduct(?Closure $callback = null): self
    {
        $schema = new ProductSchema();

        if ($callback) {
            $callback($schema);
        }

        $this->schemas[] = $schema->toArray();

        return $this;
    }

    /**
     * Add an Organization schema.
     */
    public function addOrganization(?Closure $callback = null): self
    {
        $schema = new OrganizationSchema();

        if ($callback) {
            $callback($schema);
        }

        $this->schemas[] = $schema->toArray();

        return $this;
    }

    /**
     * Add a raw schema array.
     *
     * @param array<string, mixed> $schema
     */
    public function add(array $schema): self
    {
        // Ensure @context is present
        if (! isset($schema['@context'])) {
            $schema = ['@context' => 'https://schema.org'] + $schema;
        }

        $this->schemas[] = $schema;

        return $this;
    }

    /**
     * Get all schemas as array.
     *
     * @return array<int, array<string, mixed>>
     */
    public function toArray(): array
    {
        return $this->schemas;
    }

    /**
     * Get schemas as JSON string.
     */
    public function toJson(): string
    {
        if (count($this->schemas) === 0) {
            return '[]';
        }

        if (count($this->schemas) === 1) {
            return json_encode($this->schemas[0], self::JSON_FLAGS | JSON_PRETTY_PRINT) ?: '{}';
        }

        return json_encode($this->schemas, self::JSON_FLAGS | JSON_PRETTY_PRINT) ?: '[]';
    }

    /**
     * Get schemas as HTML script tag.
     */
    public function toScript(): string
    {
        if (empty($this->schemas)) {
            return '';
        }

        $json = $this->toJson();

        return '<script type="application/ld+json">' . "\n" . $json . "\n" . '</script>';
    }

    /**
     * Check if collection has any schemas.
     */
    public function isEmpty(): bool
    {
        return empty($this->schemas);
    }

    /**
     * Get the number of schemas.
     */
    public function count(): int
    {
        return count($this->schemas);
    }
}
