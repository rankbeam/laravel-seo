---
description: "Genereer een samenhangende JSON-LD-schemagraaf met stabiele @id-verwijzingen tussen Organization, WebSite, WebPage en Article, zodat elke pagina een consistente graaf uitvoert."
---

# Schemagraaf (JSON-LD) {#schema-graph-json-ld}

Zoekmachines kunnen JSON-LD het best lezen wanneer de knooppunten naar elkaar verwijzen:
Organization publiceert WebSite, WebSite bevat WebPage en WebPage gaat over Article.
`SchemaGraph` maakt precies dat: knooppunten die via **stabiele `@id`-waarden**
met elkaar verbonden zijn, zodat elke pagina een consistente graaf uitvoert.

## De paginagraaf {#the-page-graph}

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

Render deze in Blade (in de head of body):

```blade
{!! $schemas->toScript() !!}
```

De gegevens voor Organization en WebSite komen uit `config/seo.php` (`schema.organization`,
`schema.website`); het WebPage-knooppunt wordt gevuld vanuit de uiteindelijke `SEOData`.

Het WebPage-knooppunt krijgt `inLanguage` vanuit de uiteindelijke locale van de pagina,
in BCP 47-notatie (`it_IT` → `it-IT`). `ArticleSchema::fromModel()` haalt deze uit de
opgeslagen locale in `seo_meta`. Het WebSite-knooppunt vermeldt de talen van de
site vanuit `schema.website.inLanguage` (één code of een lijst). Stel `schema.in_language` in op
`false` om nergens `inLanguage` uit te voeren. Zie [Meertalige content](/nl/guide/multilingual#inlanguage-in-the-schema-graph).

## Getypeerde builders {#typed-builders}

Er zijn builders voor veelgebruikte typen uitgebreide zoekresultaten:

| Builder | Opmerkingen |
|---|---|
| `ArticleSchema::fromModel($post)` | datums, auteur en uitgever uit het model en de configuratie |
| `ProductSchema` | aanbiedingen, prijs en beschikbaarheid |
| `BreadcrumbSchema::fromArray([...])` | geordende naam/URL-paren |
| `BreadcrumbSchema::fromModelAncestors($page)` | doorloopt een `parent`-keten (met bescherming tegen lussen) |
| `FAQSchema` | vraag/antwoord-paren |
| `LocalBusinessSchema` | adres, geografische gegevens en openingstijden |
| `OrganizationSchema` | zelfstandig organisatieknooppunt |

Een volledige artikelpagina:

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

## Opgeslagen schema en `@seoSchema` {#attached-schema-and-seoschema}

Schema dat in de uiteindelijke `SEOData` is opgeslagen (bijvoorbeeld samen met
expliciete metadata), wordt gerenderd via de `@seoSchema`-directive of het
`script`-gedeelte van `SEO::toArray()`:

```blade
@seoSchema($post)
```

Redacteuren kunnen `seo_meta.schema_jsonld` zonder code invullen via het optionele onderdeel
**Gestructureerde data** van het pakket met [Filament-velden](/nl/guide/filament#structured-data-schema-org).
Dit biedt een schakelaar voor automatische breadcrumbs en FAQ- en Product-blokken,
die vóór het opslaan door `SchemaValidator` worden gevalideerd.

## Escaping {#escaping}

Alle JSON-LD-uitvoer — `SchemaCollection::toScript()`, `toJson()` en de uitvoer van de
renderers — wordt gecodeerd met `JSON_HEX_TAG | JSON_HEX_APOS |
JSON_HEX_QUOT | JSON_HEX_AMP`. Een `</script>`-reeks in titels of
content kan daardoor het scriptelement niet afsluiten. Omzeil dit niet door
schema-arrays zelf met `json_encode` te coderen.
