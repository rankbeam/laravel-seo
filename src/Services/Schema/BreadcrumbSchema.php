<?php

declare(strict_types=1);

namespace Rankbeam\Seo\Services\Schema;

use Closure;
use Illuminate\Database\Eloquent\Model;

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
     * Build a breadcrumb from a page-like model's ancestor chain.
     *
     * Walks the model's `parent` relation (via `parent_id`) up to the root,
     * producing Home → ancestors → page. Returns null when no breadcrumb
     * should be rendered: for the home page itself (slug '/') and for pages
     * without ancestors.
     *
     * The walk is guarded against parent loops (a page whose ancestor chain
     * cycles back, e.g. via bad CMS data): each parent_id is visited at most
     * once, so a cycle terminates instead of recursing forever. Ancestors
     * with slug '/' are skipped because Home is already prepended.
     *
     * @param Model $page The page model (expects parent_id / parent relation)
     * @param Closure|null $name Resolves an item label (default: title ?? name)
     * @param Closure|null $url Resolves an item URL (default: getUrlForSEO() ?? url attribute)
     * @param string $homeLabel Label for the prepended home item
     *
     * @example
     * ```php
     * $schema = BreadcrumbSchema::fromModelAncestors($page)?->toArray();
     * ```
     */
    public static function fromModelAncestors(
        Model $page,
        ?Closure $name = null,
        ?Closure $url = null,
        string $homeLabel = 'Home',
    ): ?self {
        $name ??= fn (Model $item): string => (string) ($item->title ?? $item->name ?? '');
        $url ??= fn (Model $item): string => method_exists($item, 'getUrlForSEO')
            ? (string) $item->getUrlForSEO()
            : (string) ($item->url ?? '');

        if (($page->slug ?? null) === '/') {
            return null;
        }

        $ancestors = self::ancestorsOf($page);

        if ($ancestors === []) {
            return null;
        }

        $schema = new self();
        $schema->addItem($homeLabel, url('/'));

        foreach ($ancestors as $ancestor) {
            if (($ancestor->slug ?? null) === '/') {
                continue;
            }

            $schema->addItem($name($ancestor), $url($ancestor));
        }

        $schema->addItem($name($page), $url($page));

        return $schema;
    }

    /**
     * Collect the ancestor chain (root first), guarding against loops.
     *
     * @return array<int, Model>
     */
    protected static function ancestorsOf(Model $page): array
    {
        $ancestors = [];
        $visited = [];

        while ($page->parent_id !== null && ! in_array($page->parent_id, $visited, true)) {
            $visited[] = $page->parent_id;

            $parent = $page->relationLoaded('parent')
                ? $page->getRelation('parent')
                : $page->parent()->first();

            if (! $parent instanceof Model) {
                break;
            }

            array_unshift($ancestors, $parent);
            $page = $parent;
        }

        return $ancestors;
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
