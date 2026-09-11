---
description: "Generuj spójny graf schematu JSON-LD, którego węzły odwołują się do siebie przez stabilne @id: Organization, WebSite, WebPage i Article."
---

# Graf schematu (JSON-LD) {#schema-graph-json-ld}

Wyszukiwarki najlepiej odczytują JSON-LD, gdy węzły odwołują się do siebie: Organization publikuje WebSite, WebSite zawiera WebPage, a WebPage opisuje Article. Właśnie to tworzy `SchemaGraph`: zbiór węzłów powiązanych przez **stabilne wartości `@id`**, dzięki czemu każda strona generuje spójny graf.

## Graf strony {#the-page-graph}

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

Wygeneruj go w Blade (w sekcji head lub body):

```blade
{!! $schemas->toScript() !!}
```

Dane Organization i WebSite pochodzą z `config/seo.php` (`schema.organization`, `schema.website`). Węzeł WebPage jest wypełniany na podstawie rozstrzygniętego `SEOData`.

Węzeł WebPage otrzymuje `inLanguage` z rozstrzygniętego locale strony w formacie BCP 47 (`it_IT` → `it-IT`). `ArticleSchema::fromModel()` pobiera go z locale zapisanego w `seo_meta`, a węzeł WebSite podaje języki witryny z `schema.website.inLanguage` (jeden kod lub lista). Ustaw `schema.in_language` na `false`, aby w ogóle nie generować `inLanguage`. Zobacz [Treści wielojęzyczne](/pl/guide/multilingual#inlanguage-in-the-schema-graph).

## Typowane kreatory {#typed-builders}

Dostępne są kreatory najczęstszych typów wyników rozszerzonych:

| Kreator | Uwagi |
|---|---|
| `ArticleSchema::fromModel($post)` | Daty, autor i wydawca z modelu oraz konfiguracji |
| `ProductSchema` | Oferty, cena i dostępność |
| `BreadcrumbSchema::fromArray([...])` | Uporządkowane pary nazwa/URL |
| `BreadcrumbSchema::fromModelAncestors($page)` | Przechodzi przez łańcuch `parent` (z ochroną przed pętlami) |
| `FAQSchema` | Pary pytanie/odpowiedź |
| `LocalBusinessSchema` | Adres, współrzędne i godziny otwarcia |
| `OrganizationSchema` | Samodzielny węzeł organizacji |

Kompletna strona artykułu:

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

## Dołączony schemat i `@seoSchema` {#attached-schema-and-seoschema}

Schemat zapisany w rozstrzygniętym `SEOData` (np. razem z jawnymi metadanymi) jest renderowany przez dyrektywę `@seoSchema` lub sekcję `script` w `SEO::toArray()`:

```blade
@seoSchema($post)
```

Redaktorzy mogą wypełniać `seo_meta.schema_jsonld` bez kodu dzięki opcjonalnej sekcji **Dane strukturalne** w pakiecie [pól Filament](/pl/guide/filament#structured-data-schema-org). Obejmuje ona przełącznik automatycznej ścieżki nawigacyjnej oraz bloki FAQ / Produkt, sprawdzane przez `SchemaValidator` przed zapisem.

## Bezpieczne kodowanie {#escaping}

Cały wynik JSON-LD — `SchemaCollection::toScript()`, `toJson()` i wyjście rendererów — jest kodowany przy użyciu `JSON_HEX_TAG | JSON_HEX_APOS |
JSON_HEX_QUOT | JSON_HEX_AMP`. Sekwencja `</script>` w tytule lub treści nie może zamknąć elementu script. Nie obchodź tego zabezpieczenia, samodzielnie kodując tablice schematu przez `json_encode`.
