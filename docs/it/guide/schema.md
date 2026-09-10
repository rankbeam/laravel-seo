---
description: "Genera un grafo JSON-LD con nodi collegati tramite @id stabili: Organization, WebSite, WebPage e Article, con riferimenti coerenti su ogni pagina."
---

# Grafo dello schema JSON-LD {#schema-graph-json-ld}

I riferimenti tra nodi rendono esplicite le relazioni nei dati JSON-LD: Organization pubblica WebSite, WebSite contiene WebPage e WebPage riguarda Article. `SchemaGraph` produce un insieme di nodi collegati tramite **valori `@id` stabili**, così ogni pagina genera un grafo coerente.

## Il grafo della pagina {#the-page-graph}

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

Generalo in Blade, nell’head o nel body:

```blade
{!! $schemas->toScript() !!}
```

I dati di Organization e WebSite provengono da `config/seo.php`, nelle sezioni `schema.organization` e `schema.website`. Il nodo WebPage viene compilato dal `SEOData` risolto.

WebPage riceve `inLanguage` dalla lingua risolta della pagina, nel formato BCP 47 (`it_IT` → `it-IT`). `ArticleSchema::fromModel()` lo ricava dalla lingua memorizzata in `seo_meta`; WebSite elenca le lingue del sito configurate in `schema.website.inLanguage`, che accetta un codice o un elenco. Imposta `schema.in_language` a `false` per non emettere alcun `inLanguage`. Vedi [Contenuti multilingua](/it/guide/multilingual#inlanguage-in-the-schema-graph).

## Builder tipizzati {#typed-builders}

Sono disponibili builder per i tipi comuni usati nei risultati avanzati:

| Builder | Note |
|---|---|
| `ArticleSchema::fromModel($post)` | Date, autore ed editore ricavati dal modello e dalla configurazione |
| `ProductSchema` | Offerte, prezzo e disponibilità |
| `BreadcrumbSchema::fromArray([...])` | Coppie ordinate di nome e URL |
| `BreadcrumbSchema::fromModelAncestors($page)` | Percorre una catena `parent`, con protezione dai cicli |
| `FAQSchema` | Coppie di domande e risposte |
| `LocalBusinessSchema` | Indirizzo, posizione geografica e orari di apertura |
| `OrganizationSchema` | Nodo dell’organizzazione autonomo |

Una pagina articolo completa:

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

## Schema associato e `@seoSchema` {#attached-schema-and-seoschema}

Lo schema memorizzato nel `SEOData` risolto, per esempio insieme ai metadati espliciti, viene generato dalla direttiva `@seoSchema` o dalla sezione `script` di `SEO::toArray()`:

```blade
@seoSchema($post)
```

Gli editor possono compilare `seo_meta.schema_jsonld` senza scrivere codice tramite la sezione facoltativa **Dati strutturati** dei [campi Filament](/it/guide/filament#structured-data-schema-org). Include un’opzione per i breadcrumb automatici e blocchi FAQ o Product, validati da `SchemaValidator` prima del salvataggio.

## Escaping {#escaping}

Tutto l’output JSON-LD — `SchemaCollection::toScript()`, `toJson()` e i percorsi del renderer — viene codificato con `JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP`. Una sequenza `</script>` in titoli o contenuti non può chiudere l’elemento script. Non aggirare questa protezione codificando gli array dello schema direttamente con `json_encode`.
