---
description: "Gere um grafo JSON-LD com nós Organization, WebSite, WebPage e Article conectados por valores @id estáveis e consistentes entre páginas."
---

# Grafo de esquemas JSON-LD {#schema-graph-json-ld}

Os nós JSON-LD podem explicitar as relações entre entidades: Organization publica WebSite, WebSite contém WebPage e WebPage trata de Article. `SchemaGraph` produz esse conjunto de nós conectados por **valores `@id` estáveis**, para que as páginas emitam um grafo consistente.

## O grafo da página {#the-page-graph}

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

Renderize em Blade, no head ou no body:

```blade
{!! $schemas->toScript() !!}
```

Os dados de Organization e WebSite vêm de `config/seo.php`, em `schema.organization` e `schema.website`. O nó WebPage é preenchido a partir do `SEOData` resolvido.

WebPage recebe `inLanguage` do idioma resolvido em formato BCP 47, como `it_IT` → `it-IT`. `ArticleSchema::fromModel()` usa o idioma salvo em `seo_meta`, e WebSite lista os idiomas de `schema.website.inLanguage`, que aceita um código ou uma lista. Defina `schema.in_language` como `false` para não emitir nenhum `inLanguage`. Consulte [conteúdo multilíngue](/pt-BR/guide/multilingual#inlanguage-in-the-schema-graph).

## Construtores tipados {#typed-builders}

O pacote oferece construtores para tipos comuns de resultados enriquecidos:

| Construtor | Observações |
|---|---|
| `ArticleSchema::fromModel($post)` | Datas, autor e editora a partir do modelo e da configuração |
| `ProductSchema` | Ofertas, preço e disponibilidade |
| `BreadcrumbSchema::fromArray([...])` | Pares ordenados de nome e URL |
| `BreadcrumbSchema::fromModelAncestors($page)` | Percorre a cadeia `parent`, com proteção contra ciclos |
| `FAQSchema` | Pares de pergunta e resposta |
| `LocalBusinessSchema` | Endereço, localização e horário de funcionamento |
| `OrganizationSchema` | Nó de organização independente |

Uma página completa de artigo:

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

## Esquemas associados e `@seoSchema` {#attached-schema-and-seoschema}

Os esquemas presentes no `SEOData` resolvido, por exemplo salvos junto dos metadados explícitos, são emitidos por `@seoSchema` ou pela seção `script` de `SEO::toArray()`:

```blade
@seoSchema($post)
```

Editores podem preencher `seo_meta.schema_jsonld` sem escrever código usando a seção opcional **Structured data** do pacote de [campos Filament](/pt-BR/guide/filament#structured-data-schema-org). Ela oferece navegação estrutural automática e blocos FAQ e Product, validados por `SchemaValidator` antes de salvar.

## Escape {#escaping}

Toda saída JSON-LD, incluindo `SchemaCollection::toScript()`, `toJson()` e os renderizadores, usa `JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP`. Uma sequência `</script>` no título ou conteúdo não consegue encerrar o elemento script. Preserve essa proteção: não codifique os arrays de esquema por conta própria com `json_encode`.
