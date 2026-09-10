---
description: "Einen verknüpften JSON-LD-Graphen ausgeben: Organization, WebSite, WebPage und Article verweisen über stabile @id-Werte aufeinander und bilden einen konsistenten Graphen pro Seite."
---

# Schema-Graph (JSON-LD) {#schema-graph-json-ld}

Verknüpfte JSON-LD-Knoten machen ihre Beziehungen für Suchmaschinen ausdrücklich sichtbar: Die Organization veröffentlicht die WebSite, die WebSite enthält die WebPage und die WebPage behandelt den Article. `SchemaGraph` erzeugt solche Knoten mit Querverweisen über **stabile `@id`-Werte**, damit jede Seite einen konsistenten Graphen ausgibt.

## Der Seitengraph {#the-page-graph}

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

In Blade rendern, im Head oder Body:

```blade
{!! $schemas->toScript() !!}
```

Die Daten für Organization und WebSite stammen aus `config/seo.php`, genauer aus `schema.organization` und `schema.website`. Der WebPage-Knoten wird aus dem aufgelösten `SEOData` befüllt.

Der WebPage-Knoten übernimmt `inLanguage` aus der aufgelösten Seiten-Locale in BCP-47-Schreibweise (`it_IT` → `it-IT`). `ArticleSchema::fromModel()` verwendet die gespeicherte `seo_meta`-Locale. Der WebSite-Knoten enthält die Website-Sprachen aus `schema.website.inLanguage`, als einzelner Code oder Liste. Setze `schema.in_language` auf `false`, wenn `inLanguage` vollständig entfallen soll. Siehe [Mehrsprachige Inhalte](/de/guide/multilingual#inlanguage-in-the-schema-graph).

## Typisierte Builder {#typed-builders}

Für häufige Rich-Result-Typen stehen Builder bereit:

| Builder | Hinweise |
|---|---|
| `ArticleSchema::fromModel($post)` | Datumsangaben, Autor und Herausgeber aus Modell und Konfiguration |
| `ProductSchema` | Angebote, Preis und Verfügbarkeit |
| `BreadcrumbSchema::fromArray([...])` | Geordnete Name/URL-Paare |
| `BreadcrumbSchema::fromModelAncestors($page)` | Durchläuft eine `parent`-Kette mit Schutz vor Schleifen |
| `FAQSchema` | Frage/Antwort-Paare |
| `LocalBusinessSchema` | Adresse, Geodaten und Öffnungszeiten |
| `OrganizationSchema` | Eigenständiger Organisationsknoten |

Eine vollständige Artikelseite:

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

## Angehängtes Schema und `@seoSchema` {#attached-schema-and-seoschema}

Schema im aufgelösten `SEOData`, etwa zusammen mit expliziten Metadaten gespeichert, wird über `@seoSchema` oder den Abschnitt `script` von `SEO::toArray()` ausgegeben:

```blade
@seoSchema($post)
```

Redakteure können `seo_meta.schema_jsonld` ohne Code über den optionalen Bereich **Structured data** im Paket für [Filament-Felder](/de/guide/filament#structured-data-schema-org) befüllen. Dieser bietet einen Schalter für automatische Breadcrumbs sowie FAQ- und Product-Blöcke, die `SchemaValidator` vor dem Speichern prüft.

## Escaping {#escaping}

Sämtliche JSON-LD-Ausgabe, einschließlich `SchemaCollection::toScript()`, `toJson()` und der Renderer, wird mit `JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP` kodiert. Ein `</script>` in Titeln oder Inhalten kann das Script-Element nicht beenden. Umgehe diesen Schutz nicht, indem du Schema-Arrays selbst mit `json_encode` kodierst.
