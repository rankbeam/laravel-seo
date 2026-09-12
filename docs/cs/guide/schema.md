---
description: "Vykreslujte propojený graf JSON-LD, jehož uzly Organization, WebSite, WebPage a Article na sebe odkazují stabilními @id, aby každá stránka poskytovala konzistentní graf."
---

# Graf strukturovaných dat (JSON-LD) {#schema-graph-json-ld}

Vyhledávače nejlépe zpracují JSON-LD, když na sebe uzly odkazují: Organization vydává WebSite, WebSite obsahuje WebPage a WebPage se týká Article. `SchemaGraph` vytváří právě takovou sadu uzlů propojených přes **stabilní hodnoty `@id`**, takže každá stránka poskytuje konzistentní graf.

## Graf stránky {#the-page-graph}

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

Vykreslete ho v Blade, v hlavičce nebo těle:

```blade
{!! $schemas->toScript() !!}
```

Data Organization a WebSite pocházejí z `config/seo.php` (`schema.organization`, `schema.website`). Uzel WebPage se plní z vyhodnoceného `SEOData`.

Uzel WebPage přebírá `inLanguage` z vyhodnocené jazykové verze stránky ve tvaru BCP 47 (`it_IT` → `it-IT`). `ArticleSchema::fromModel()` ho získává z uložené jazykové verze `seo_meta` a uzel WebSite uvádí jazyky webu z `schema.website.inLanguage` jako jeden kód nebo seznam. Nastavením `schema.in_language` na `false` se `inLanguage` vůbec nevypíše. Viz [vícejazyčný obsah](/cs/guide/multilingual#inlanguage-in-the-schema-graph).

## Typované buildery {#typed-builders}

Pro běžné typy rozšířených výsledků existují buildery:

| Builder | Poznámky |
|---|---|
| `ArticleSchema::fromModel($post)` | Časové údaje, autor a vydavatel z modelu a konfigurace |
| `ProductSchema` | Nabídky, cena, dostupnost |
| `BreadcrumbSchema::fromArray([...])` | Seřazené dvojice názvu a URL |
| `BreadcrumbSchema::fromModelAncestors($page)` | Prochází řetězec `parent` s ochranou proti smyčkám |
| `FAQSchema` | Dvojice otázek a odpovědí |
| `LocalBusinessSchema` | Adresa, souřadnice, otevírací doba |
| `OrganizationSchema` | Samostatný uzel organizace |

Úplná stránka článku:

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

## Připojená strukturovaná data a `@seoSchema` {#attached-schema-and-seoschema}

Strukturovaná data uložená ve vyhodnoceném `SEOData`, například spolu s explicitními metadaty, se vykreslují direktivou `@seoSchema` nebo sekcí `script` výstupu `SEO::toArray()`:

```blade
@seoSchema($post)
```

Redaktoři mohou `seo_meta.schema_jsonld` vyplnit bez kódu ve volitelné sekci **Strukturovaná data** balíčku [polí Filament](/cs/guide/filament#structured-data-schema-org). Nabízí přepínač automatické drobečkové navigace a bloky FAQ / Product, které před uložením validuje `SchemaValidator`.

## Escapování {#escaping}

Veškerý výstup JSON-LD — `SchemaCollection::toScript()`, `toJson()` i ostatní cesty rendereru — se kóduje s `JSON_HEX_TAG | JSON_HEX_APOS |
JSON_HEX_QUOT | JSON_HEX_AMP`. Sekvence `</script>` v titulku nebo obsahu nemůže ukončit element skriptu. Neobcházejte tuto ochranu vlastním voláním `json_encode` nad poli strukturovaných dat.
