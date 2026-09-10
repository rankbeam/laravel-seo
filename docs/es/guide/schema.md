---
description: "Genera un grafo JSON-LD con nodos Organization, WebSite, WebPage y Article enlazados mediante valores @id estables y coherentes entre páginas."
---

# Grafo de datos estructurados (JSON-LD) {#schema-graph-json-ld}

Los enlaces entre nodos ayudan a los buscadores a interpretar JSON-LD: Organization publica WebSite, WebSite contiene WebPage y WebPage trata sobre Article. `SchemaGraph` genera ese conjunto de nodos enlazados mediante **valores `@id` estables**, para que cada página emita un grafo coherente.

## El grafo de la página {#the-page-graph}

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

Renderízalo en Blade, en el head o en el body:

```blade
{!! $schemas->toScript() !!}
```

Los datos de Organization y WebSite proceden de `config/seo.php` (`schema.organization`, `schema.website`); el nodo WebPage se completa con el `SEOData` resuelto.

El nodo WebPage obtiene `inLanguage` del idioma resuelto de la página, en formato BCP 47 (`it_IT` → `it-IT`). `ArticleSchema::fromModel()` lo obtiene del idioma guardado en `seo_meta`, y WebSite enumera los idiomas del sitio desde `schema.website.inLanguage`, que acepta un código o una lista. Establece `schema.in_language` en `false` para omitir todo `inLanguage`. Consulta [Contenido multilingüe](/es/guide/multilingual#inlanguage-in-the-schema-graph).

## Constructores por tipo {#typed-builders}

Hay constructores para tipos habituales de resultados enriquecidos:

| Constructor | Detalles |
|---|---|
| `ArticleSchema::fromModel($post)` | Fechas, autor y editor a partir del modelo y la configuración |
| `ProductSchema` | Ofertas, precio y disponibilidad |
| `BreadcrumbSchema::fromArray([...])` | Pares nombre/URL ordenados |
| `BreadcrumbSchema::fromModelAncestors($page)` | Recorre una cadena `parent` con protección contra bucles |
| `FAQSchema` | Pares de pregunta y respuesta |
| `LocalBusinessSchema` | Dirección, coordenadas y horarios |
| `OrganizationSchema` | Nodo de organización independiente |

Una página de artículo completa:

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

## Datos estructurados asociados y `@seoSchema` {#attached-schema-and-seoschema}

Los datos estructurados del `SEOData` resuelto, por ejemplo los guardados junto a metadatos explícitos, se generan mediante `@seoSchema` o la sección `script` de `SEO::toArray()`:

```blade
@seoSchema($post)
```

Los editores pueden rellenar `seo_meta.schema_jsonld` sin código mediante la sección opcional **Datos estructurados** del paquete de [campos Filament](/es/guide/filament#structured-data-schema-org). Incluye un interruptor para migas de pan automáticas y bloques FAQ / Product, validados con `SchemaValidator` antes de guardarlos.

## Escape {#escaping}

Toda la salida JSON-LD —`SchemaCollection::toScript()`, `toJson()` y las vías del renderizador— se codifica con `JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP`. Una secuencia `</script>` en un título o contenido no puede cerrar el elemento script. No evites esta protección codificando los arrays por tu cuenta con `json_encode`.
