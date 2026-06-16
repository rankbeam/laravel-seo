<?php

declare(strict_types=1);

namespace Rankbeam\Seo\Services\Schema;

use Closure;
use Illuminate\Database\Eloquent\Model;
use Rankbeam\Seo\Data\SEOData;

/**
 * Fluent composition of an @id-linked JSON-LD graph.
 *
 * Assembles the cross-linked Organization / WebSite / WebPage + BreadcrumbList
 * nodes that a sitewide schema needs, ON TOP of the existing primitives:
 * {@see SchemaGraph} builds each node with its stable @id, {@see BreadcrumbSchema}
 * (via its loop-guarded `fromModelAncestors()`) builds the breadcrumb, and
 * {@see SchemaCollection} accumulates the list. This helper is just the glue —
 * it adds no schema logic and introduces no parallel breadcrumb API.
 *
 * It exists so an app does not have to hand-roll a `SitewideSchema` class: a
 * model's `getSEOSchema()` hook can return a complete graph in one expression.
 *
 * ## Usage
 * ```php
 * // Inside a model's getSEOSchema() hook:
 * public function getSEOSchema(): array
 * {
 *     return SchemaGraph::for($this)
 *         ->organization()
 *         ->website()
 *         ->webPage()
 *         ->breadcrumbFromAncestors()
 *         ->toArray();
 * }
 * ```
 *
 * The resulting nodes share stable @ids ({site_url}#organization,
 * {site_url}#website, {canonical}#webpage), so search engines connect them into
 * a single graph.
 *
 * @see SchemaGraph For the node primitives and the @id conventions
 * @see BreadcrumbSchema::fromModelAncestors() For the loop-guarded breadcrumb
 */
class SchemaGraphBuilder
{
    protected SchemaGraph $graph;

    protected SchemaCollection $collection;

    protected ?Model $model = null;

    protected ?SEOData $seo = null;

    /**
     * @param Model|SEOData|null $subject The model (resolves its own SEOData /
     *                                    ancestors) or a hand-built SEOData
     * @param SchemaGraph|null $graph An existing node builder, or a fresh one
     */
    public function __construct(Model|SEOData|null $subject = null, ?SchemaGraph $graph = null)
    {
        $this->graph = $graph ?? new SchemaGraph();
        $this->collection = SchemaCollection::make();

        if ($subject instanceof Model) {
            $this->model = $subject;
        } elseif ($subject instanceof SEOData) {
            $this->seo = $subject;
        }
    }

    /**
     * Add the Organization node (from config('seo.schema.organization')).
     */
    public function organization(): static
    {
        return $this->addNode($this->graph->organization());
    }

    /**
     * Add the WebSite node (from config('seo.schema.website')), linked to the
     * Organization via publisher.
     */
    public function website(): static
    {
        return $this->addNode($this->graph->webSite());
    }

    /**
     * Add the WebPage node, linked into the graph via isPartOf + about.
     *
     * Resolves the subject's SEOData when no explicit data is passed: the
     * hand-built SEOData given to {@see SchemaGraph::for()}, otherwise the
     * model's resolved `seoData()`. Does nothing when there is no data to
     * describe a page from.
     *
     * @param SEOData|null $seo Override the resolved page data
     */
    public function webPage(?SEOData $seo = null): static
    {
        $seo ??= $this->resolveSeo();

        if ($seo === null) {
            return $this;
        }

        return $this->addNode($this->graph->webPage($seo));
    }

    /**
     * Add a BreadcrumbList node walking the model's ancestor chain.
     *
     * Delegates to the existing loop-guarded
     * {@see BreadcrumbSchema::fromModelAncestors()} — there is no second
     * breadcrumb engine. Adds nothing when the subject is not a model, or when
     * the model has no breadcrumb (home page / no ancestors).
     *
     * @param Closure|null $name Resolves an item label (default: title ?? name)
     * @param Closure|null $url Resolves an item URL (default: getUrlForSEO() ?? url)
     * @param string $homeLabel Label for the prepended home item
     */
    public function breadcrumbFromAncestors(
        ?Closure $name = null,
        ?Closure $url = null,
        string $homeLabel = 'Home',
    ): static {
        if (! $this->model instanceof Model) {
            return $this;
        }

        $breadcrumb = BreadcrumbSchema::fromModelAncestors($this->model, $name, $url, $homeLabel);

        if ($breadcrumb === null) {
            return $this;
        }

        return $this->addNode($breadcrumb->toArray());
    }

    /**
     * Add an arbitrary, pre-built schema node to the graph.
     *
     * The escape hatch for nodes the primitives don't cover (a built
     * {@see ArticleSchema}, {@see ProductSchema}, a SearchAction, …): pass
     * its array form and it joins the graph unchanged.
     *
     * @param array<string, mixed> $node
     */
    public function add(array $node): static
    {
        return $this->addNode($node);
    }

    /**
     * The assembled graph as a list of schema.org nodes.
     *
     * @return array<int, array<string, mixed>>
     */
    public function toArray(): array
    {
        return $this->collection->toArray();
    }

    /**
     * The underlying collection, for callers that want toScript()/toJson().
     */
    public function toCollection(): SchemaCollection
    {
        return $this->collection;
    }

    /**
     * Whether no node has been added yet.
     */
    public function isEmpty(): bool
    {
        return $this->collection->isEmpty();
    }

    /**
     * Add a node, skipping empty ones so partial config never emits a stub.
     *
     * @param array<string, mixed> $node
     */
    protected function addNode(array $node): static
    {
        if ($node !== []) {
            $this->collection->add($node);
        }

        return $this;
    }

    /**
     * Resolve the page SEOData for the WebPage node.
     *
     * Prefers an explicitly supplied SEOData; otherwise asks the model for its
     * resolved data. The model path runs through the resolver, which guards
     * against the re-entrancy this would otherwise cause when called from
     * `getSEOSchema()`.
     */
    protected function resolveSeo(): ?SEOData
    {
        if ($this->seo instanceof SEOData) {
            return $this->seo;
        }

        if ($this->model instanceof Model && method_exists($this->model, 'seoData')) {
            return $this->model->seoData();
        }

        return null;
    }
}
