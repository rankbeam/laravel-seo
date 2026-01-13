<?php

declare(strict_types=1);

namespace Fibonoir\LaravelSEO\Services\Schema;

/**
 * Builder for BreadcrumbList JSON-LD schema.
 *
 * Breadcrumb schema helps search engines understand site structure
 * and can result in breadcrumb-style rich snippets.
 *
 * ## Usage
 * ```php
 * $schema = (new BreadcrumbSchema())
 *     ->prependHome()
 *     ->addItem('Blog', '/blog', 2)
 *     ->addItem('My Article', '/blog/my-article', 3)
 *     ->toArray();
 * ```
 *
 * ## From Array
 * ```php
 * $schema = BreadcrumbSchema::fromArray([
 *     ['name' => 'Home', 'url' => '/'],
 *     ['name' => 'Blog', 'url' => '/blog'],
 *     ['name' => 'My Article', 'url' => '/blog/my-article'],
 * ]);
 * ```
 */
class BreadcrumbSchema
{
    /**
     * @var array<int, array{name: string, url: string, position: int}>
     */
    protected array $items = [];

    /**
     * Add a breadcrumb item.
     */
    public function addItem(string $name, string $url, ?int $position = null): self
    {
        // Auto-calculate position if not provided
        if ($position === null) {
            $position = count($this->items) + 1;
        }

        $this->items[] = [
            'name' => $name,
            'url' => $this->normalizeUrl($url),
            'position' => $position,
        ];

        return $this;
    }

    /**
     * Prepend home link.
     */
    public function prependHome(string $name = 'Home', string $url = '/'): self
    {
        // Shift all existing positions
        foreach ($this->items as &$item) {
            $item['position']++;
        }

        // Add home at the beginning
        array_unshift($this->items, [
            'name' => $name,
            'url' => $this->normalizeUrl($url),
            'position' => 1,
        ]);

        return $this;
    }

    /**
     * Create from array of breadcrumb items.
     *
     * @param array<int, array{name: string, url: string}> $items
     */
    public static function fromArray(array $items): self
    {
        $schema = new self();

        foreach ($items as $index => $item) {
            $schema->addItem(
                $item['name'],
                $item['url'],
                $index + 1
            );
        }

        return $schema;
    }

    /**
     * Normalize URL to absolute.
     */
    protected function normalizeUrl(string $url): string
    {
        if (str_starts_with($url, 'http://') || str_starts_with($url, 'https://')) {
            return $url;
        }

        return url($url);
    }

    /**
     * Convert to array.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        // Sort by position
        $sorted = $this->items;
        usort($sorted, fn ($a, $b) => $a['position'] <=> $b['position']);

        $itemListElement = [];

        foreach ($sorted as $item) {
            $itemListElement[] = [
                '@type' => 'ListItem',
                'position' => $item['position'],
                'name' => $item['name'],
                'item' => $item['url'],
            ];
        }

        return [
            '@context' => 'https://schema.org',
            '@type' => 'BreadcrumbList',
            'itemListElement' => $itemListElement,
        ];
    }
}
