<?php

declare(strict_types=1);

namespace Fibonoir\LaravelSEO\Services\Schema;

use DateTimeInterface;
use Illuminate\Database\Eloquent\Model;

/**
 * Builder for Article JSON-LD schema.
 *
 * Supports Article, NewsArticle, and BlogPosting types.
 *
 * ## Usage
 * ```php
 * $schema = (new ArticleSchema())
 *     ->setHeadline('My Article Title')
 *     ->setDescription('A brief description')
 *     ->setAuthor('John Doe')
 *     ->setImage('https://example.com/image.jpg')
 *     ->setDatePublished(now())
 *     ->setDateModified(now())
 *     ->toArray();
 * ```
 *
 * ## From Model
 * ```php
 * $schema = ArticleSchema::fromModel($post);
 * ```
 */
class ArticleSchema
{
    protected string $type = 'Article';

    protected ?string $headline = null;

    protected ?string $description = null;

    /** @var string|array<string, mixed>|null */
    protected string|array|null $image = null;

    /** @var string|array<string, mixed>|null */
    protected string|array|null $author = null;

    /** @var array<string, mixed>|null */
    protected ?array $publisher = null;

    protected ?DateTimeInterface $datePublished = null;

    protected ?DateTimeInterface $dateModified = null;

    protected ?string $mainEntityOfPage = null;

    protected ?string $articleBody = null;

    protected ?int $wordCount = null;

    /** @var array<int, string>|null */
    protected ?array $keywords = null;

    /**
     * Set the article type.
     */
    public function setType(string $type): self
    {
        $this->type = $type;

        return $this;
    }

    /**
     * Set as BlogPosting type.
     */
    public function asBlogPosting(): self
    {
        $this->type = 'BlogPosting';

        return $this;
    }

    /**
     * Set as NewsArticle type.
     */
    public function asNewsArticle(): self
    {
        $this->type = 'NewsArticle';

        return $this;
    }

    /**
     * Set the headline.
     */
    public function setHeadline(string $headline): self
    {
        $this->headline = $headline;

        return $this;
    }

    /**
     * Set the description.
     */
    public function setDescription(string $description): self
    {
        $this->description = $description;

        return $this;
    }

    /**
     * Set the image.
     *
     * @param string|array<string, mixed> $image
     */
    public function setImage(string|array $image): self
    {
        $this->image = $image;

        return $this;
    }

    /**
     * Set the author.
     *
     * @param string|array<string, mixed> $author
     */
    public function setAuthor(string|array $author): self
    {
        $this->author = $author;

        return $this;
    }

    /**
     * Set the publisher.
     *
     * @param array<string, mixed> $publisher
     */
    public function setPublisher(array $publisher): self
    {
        $this->publisher = $publisher;

        return $this;
    }

    /**
     * Set publisher from organization details.
     */
    public function setPublisherOrganization(string $name, ?string $logo = null): self
    {
        $publisher = [
            '@type' => 'Organization',
            'name' => $name,
        ];

        if ($logo) {
            $publisher['logo'] = [
                '@type' => 'ImageObject',
                'url' => $logo,
            ];
        }

        $this->publisher = $publisher;

        return $this;
    }

    /**
     * Set the date published.
     */
    public function setDatePublished(DateTimeInterface $date): self
    {
        $this->datePublished = $date;

        return $this;
    }

    /**
     * Set the date modified.
     */
    public function setDateModified(DateTimeInterface $date): self
    {
        $this->dateModified = $date;

        return $this;
    }

    /**
     * Set the main entity of page URL.
     */
    public function setMainEntityOfPage(string $url): self
    {
        $this->mainEntityOfPage = $url;

        return $this;
    }

    /**
     * Set the article body.
     */
    public function setArticleBody(string $body): self
    {
        $this->articleBody = $body;

        return $this;
    }

    /**
     * Set the word count.
     */
    public function setWordCount(int $count): self
    {
        $this->wordCount = $count;

        return $this;
    }

    /**
     * Set keywords.
     *
     * @param array<int, string> $keywords
     */
    public function setKeywords(array $keywords): self
    {
        $this->keywords = $keywords;

        return $this;
    }

    /**
     * Build schema from an Eloquent model.
     */
    public static function fromModel(Model $model): self
    {
        $schema = new self();

        // Set type based on model name
        $className = strtolower(class_basename($model));
        if (str_contains($className, 'post') || str_contains($className, 'blog')) {
            $schema->asBlogPosting();
        } elseif (str_contains($className, 'news')) {
            $schema->asNewsArticle();
        }

        // Title/headline
        $title = $model->seoMeta?->title
            ?? $model->title
            ?? $model->name
            ?? null;
        if ($title) {
            $schema->setHeadline($title);
        }

        // Description
        $description = $model->seoMeta?->description
            ?? $model->excerpt
            ?? $model->description
            ?? null;
        if ($description) {
            $schema->setDescription($description);
        }

        // Image
        $image = $model->seoMeta?->og_image
            ?? $model->featured_image
            ?? $model->image
            ?? null;
        if ($image) {
            $schema->setImage($image);
        }

        // Author
        if (isset($model->author)) {
            $author = is_object($model->author)
                ? ($model->author->name ?? null)
                : $model->author;
            if ($author) {
                $schema->setAuthor($author);
            }
        }

        // Dates
        if ($model->created_at) {
            $schema->setDatePublished($model->created_at);
        }
        if ($model->updated_at) {
            $schema->setDateModified($model->updated_at);
        }

        // URL
        if (method_exists($model, 'getUrlForSEO')) {
            $schema->setMainEntityOfPage($model->getUrlForSEO());
        }

        return $schema;
    }

    /**
     * Convert to array.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $schema = [
            '@context' => 'https://schema.org',
            '@type' => $this->type,
        ];

        if ($this->headline) {
            $schema['headline'] = $this->headline;
        }

        if ($this->description) {
            $schema['description'] = $this->description;
        }

        if ($this->image) {
            $schema['image'] = is_array($this->image)
                ? $this->image
                : ['@type' => 'ImageObject', 'url' => $this->image];
        }

        if ($this->author) {
            $schema['author'] = is_array($this->author)
                ? $this->author
                : ['@type' => 'Person', 'name' => $this->author];
        }

        if ($this->publisher) {
            $schema['publisher'] = $this->publisher;
        }

        if ($this->datePublished) {
            $schema['datePublished'] = $this->datePublished->format('c');
        }

        if ($this->dateModified) {
            $schema['dateModified'] = $this->dateModified->format('c');
        }

        if ($this->mainEntityOfPage) {
            $schema['mainEntityOfPage'] = [
                '@type' => 'WebPage',
                '@id' => $this->mainEntityOfPage,
            ];
        }

        if ($this->articleBody) {
            $schema['articleBody'] = $this->articleBody;
        }

        if ($this->wordCount) {
            $schema['wordCount'] = $this->wordCount;
        }

        if ($this->keywords) {
            $schema['keywords'] = implode(', ', $this->keywords);
        }

        return $schema;
    }
}
