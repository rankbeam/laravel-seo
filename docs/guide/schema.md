# Schema graph (JSON-LD)

Search engines read JSON-LD best when the nodes reference each other — the
Organization publishes the WebSite, the WebSite contains the WebPage, the
WebPage is about the Article. `SchemaGraph` produces exactly that: a set of
nodes cross-linked via **stable `@id` values**, so every page emits a
consistent graph.

## The page graph

```php
use Rankbeam\Seo\Facades\SEO;
use Rankbeam\Seo\Services\Schema\SchemaCollection;
use Rankbeam\Seo\Services\Schema\SchemaGraph;

$seo = SEO::resolve($post);

$graph = new SchemaGraph();

$schemas = SchemaCollection::make()
    ->add($graph->organization())   // @id: {app_url}#organization
    ->add($graph->webSite())        // @id: {app_url}#website, publisher → #organization
    ->add($graph->webPage($seo));   // @id: {page_url}#webpage, isPartOf → #website
```

Render it in Blade (head or body):

```blade
{!! $schemas->toScript() !!}
```

Organization and WebSite data come from `config/seo.php` (`schema.organization`,
`schema.website`); the WebPage node is filled from the resolved `SEOData`.

## Typed builders

Builders exist for the common rich-result types:

| Builder | Notes |
|---|---|
| `ArticleSchema::fromModel($post)` | dates, author, publisher from the model + config |
| `ProductSchema` | offers, price, availability |
| `BreadcrumbSchema::fromArray([...])` | ordered name/url pairs |
| `BreadcrumbSchema::fromModelAncestors($page)` | walks a `parent` chain (with a loop guard) |
| `FAQSchema` | question/answer pairs |
| `LocalBusinessSchema` | address, geo, opening hours |
| `OrganizationSchema` | standalone organization node |

A full article page:

```php
$article = ArticleSchema::fromModel($post)
    ->setPublisherOrganization(config('seo.schema.publisher.name'));

$schemas = SchemaCollection::make()
    ->add($graph->organization())
    ->add($graph->webSite())
    ->add($graph->webPage($seo))
    ->add($article->toArray())
    ->add(BreadcrumbSchema::fromArray([
        ['name' => 'Home', 'url' => '/'],
        ['name' => 'Blog', 'url' => '/blog'],
        ['name' => $post->title, 'url' => "/blog/{$post->slug}"],
    ])->toArray());
```

## Attached schema and `@seoSchema`

Schema stored on the resolved `SEOData` (e.g. saved alongside explicit meta)
renders through the `@seoSchema` directive or `SEO::toArray()`'s `script`
section:

```blade
@seoSchema($post)
```

## Escaping

All JSON-LD output — `SchemaCollection::toScript()`, `toJson()`, and the
renderer paths — is encoded with `JSON_HEX_TAG | JSON_HEX_APOS |
JSON_HEX_QUOT | JSON_HEX_AMP`. A `</script>` sequence inside titles or
content cannot terminate the script element. Don't bypass this by
`json_encode`-ing schema arrays yourself.
