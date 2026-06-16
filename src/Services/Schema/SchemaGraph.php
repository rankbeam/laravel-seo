<?php

declare(strict_types=1);

namespace Rankbeam\Seo\Services\Schema;

use DateTimeInterface;
use Illuminate\Database\Eloquent\Model;
use Rankbeam\Seo\Data\SEOData;

/**
 * Site-wide JSON-LD graph nodes with stable @id cross-references.

 * Encodes the @id conventions that let search engines connect the
 * Organization, WebSite, and per-page WebPage nodes into one graph:
 *
 * - Organization: `{site_url}#organization`
 * - WebSite:      `{site_url}#website`, publisher → organization @id
 * - WebPage:      `{canonical_url}#webpage`, isPartOf → website @id,
 *                 about → organization @id
 *
 * Node content comes from config('seo.schema.organization') and
 * config('seo.schema.website'); the WebPage node is built from resolved
 * SEOData. Empty values are filtered out so nodes stay valid with partial
 * configuration.
 *
 * ## Usage
 * ```php
 * $graph = new SchemaGraph();
 *
 * SchemaCollection::make()
 *     ->add($graph->organization())
 *     ->add($graph->webSite())
 *     ->add($graph->webPage($seoData));
 * ```
 */
class SchemaGraph
{
    /**
     * Start a fluent graph composition for a model or hand-built SEOData.
     *
     * The composition entry point (some apps hand-roll a sitewide schema for
     * exactly this): chain {@see SchemaGraphBuilder::organization()},
     * `website()`, `webPage()`, and `breadcrumbFromAncestors()`, then
     * `toArray()`, to assemble the @id-linked graph from these primitives.
     *
     * ```php
     * SchemaGraph::for($page)
     *     ->organization()
     *     ->website()
     *     ->webPage()
     *     ->breadcrumbFromAncestors()
     *     ->toArray();
     * ```
     *
     * @param Model|SEOData|null $subject The page model, hand-built SEOData, or null
     */
    public static function for(Model|SEOData|null $subject = null): SchemaGraphBuilder
    {
        return new SchemaGraphBuilder($subject, new self());
    }

    /**
     * The stable @id for the Organization node.
     */
    public function organizationId(): string
    {
        return $this->siteUrl() . '#organization';
    }

    /**
     * The stable @id for the WebSite node.
     */
    public function webSiteId(): string
    {
        return $this->siteUrl() . '#website';
    }

    /**
     * The @id for a WebPage node, derived from its canonical URL.
     */
    public function webPageId(?string $url = null): string
    {
        return rtrim($url ?: url()->current(), '#') . '#webpage';
    }

    /**
     * Build the Organization node from config('seo.schema.organization').
     *
     * @return array<string, mixed>
     */
    public function organization(): array
    {
        $config = config('seo.schema.organization', []);

        return $this->filter([
            '@context' => 'https://schema.org',
            '@id' => $this->organizationId(),
            '@type' => $config['type'] ?? 'Organization',
            'name' => $config['name'] ?? config('seo.site_name'),
            'url' => $this->siteUrl(),
            'logo' => isset($config['logo']) ? $this->absoluteUrl($config['logo']) : null,
            'sameAs' => $config['sameAs'] ?? [],
            'address' => $config['address'] ?? null,
            'contactPoint' => $config['contactPoint'] ?? null,
        ]);
    }

    /**
     * Build the WebSite node from config('seo.schema.website'), linked to
     * the Organization node via publisher.
     *
     * @return array<string, mixed>
     */
    public function webSite(): array
    {
        $config = config('seo.schema.website', []);

        return $this->filter([
            '@context' => 'https://schema.org',
            '@id' => $this->webSiteId(),
            '@type' => 'WebSite',
            'name' => $config['name'] ?? config('seo.site_name'),
            'url' => $this->siteUrl(),
            'publisher' => [
                '@id' => $this->organizationId(),
            ],
            'potentialAction' => $config['potentialAction'] ?? null,
        ]);
    }

    /**
     * Build a WebPage node from resolved SEO data, linked into the graph
     * via isPartOf (WebSite) and about (Organization).
     *
     * The page image is emitted as primaryImageOfPage only when it differs
     * from the configured default OG image: the site-wide fallback image
     * says nothing about this specific page and would pollute the graph.
     *
     * @return array<string, mixed>
     */
    public function webPage(SEOData $seo): array
    {
        $canonical = $seo->canonical ?: url()->current();
        $image = $this->pageImageUrl($seo->ogImage);

        return $this->filter([
            '@context' => 'https://schema.org',
            '@id' => $this->webPageId($canonical),
            '@type' => 'WebPage',
            'name' => $seo->title,
            'description' => $seo->description,
            'url' => $canonical,
            'datePublished' => $this->iso8601($seo->publishedTime),
            'dateModified' => $this->iso8601($seo->modifiedTime),
            'isPartOf' => [
                '@id' => $this->webSiteId(),
            ],
            'about' => [
                '@id' => $this->organizationId(),
            ],
            'primaryImageOfPage' => $image ? [
                '@type' => 'ImageObject',
                'url' => $image,
            ] : null,
        ]);
    }

    /**
     * The site root URL without a trailing slash.
     */
    protected function siteUrl(): string
    {
        return rtrim((string) config('app.url'), '/');
    }

    /**
     * Resolve the page image, suppressing the site-wide fallback image.
     */
    protected function pageImageUrl(?string $image): ?string
    {
        if ($image === null || trim($image) === '') {
            return null;
        }

        $imageUrl = $this->absoluteUrl($image);
        $fallback = config('seo.default_og_image');
        $fallbackUrl = $fallback ? $this->absoluteUrl($fallback) : null;

        return $imageUrl !== $fallbackUrl ? $imageUrl : null;
    }

    /**
     * Turn a relative path into an absolute URL; pass absolute URLs through.
     */
    protected function absoluteUrl(?string $url): ?string
    {
        if ($url === null || trim($url) === '') {
            return null;
        }

        if (str_starts_with($url, 'http://') || str_starts_with($url, 'https://')) {
            return $url;
        }

        return url($url);
    }

    /**
     * Format a date as ISO-8601, or null.
     */
    protected function iso8601(?DateTimeInterface $date): ?string
    {
        return $date?->format('c');
    }

    /**
     * Drop null/empty values so partial config yields valid nodes.
     *
     * @param array<string, mixed> $node
     * @return array<string, mixed>
     */
    protected function filter(array $node): array
    {
        return array_filter($node, fn ($value) => $value !== null && $value !== '' && $value !== []);
    }
}
