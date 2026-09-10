---
description: "Renderize SEO no Laravel com as diretivas Blade do pacote: @seo resolve o modelo e gera metadados, Open Graph, Twitter Cards e JSON-LD."
---

# Guia de Blade {#blade-guide}

Para aplicações renderizadas no servidor, o pacote oferece sete diretivas Blade. Uma delas, `@seo`, costuma ser suficiente.

## A diretiva completa {#the-all-in-one-directive}

```blade
<!DOCTYPE html>
<html>
<head>
    @seo($post)
</head>
```

`@seo` resolve o modelo pela [ordem de prioridade](/pt-BR/concepts/resolver-precedence) e gera o bloco completo do head: `<title>`, metadescrição, link canônico, robots, Open Graph, Twitter Cards e o JSON-LD associado. A tag robots só é emitida **quando difere do padrão do site**; o valor redundante `index,follow` é omitido, pois a ausência da tag já tem esse significado. Ative `seo.robots.emit_default` para sempre emiti-la. Consulte o [contrato de renderização](/pt-BR/contributing/rendering-contract).

Assinaturas:

```blade
@seo($post)                  {{-- model page --}}
@seo($seoData)               {{-- a hand-built SEOData (model-less page) --}}
@seo($post, 'blog.show')     {{-- model + route defaults --}}
@seo($post, null, 'fr')      {{-- model + locale --}}
@seo(null)                   {{-- current page, no model --}}
```

`@seo` aceita um `Model`, um `SEOData` construído manualmente ou `null`. Os argumentos de rota e idioma só se aplicam a `Model` e `null`: um `SEOData` manual contém seus próprios valores.

## Páginas de rota, sem modelo {#route-pages-no-model}

Para páginas estáticas, arquivos e outras páginas vinculadas a rotas:

```blade
@seoForRoute('pages.about')
@seoForRoute('contact', 'de')   {{-- with locale --}}
```

Os valores vêm de linhas de `seo_defaults` associadas ao nome da rota.

## Páginas sem modelo: `SEOData` manual {#model-less-pages-hand-built-seodata}

Listagens, resultados de busca e páginas montadas em um controlador nem sempre têm um único modelo. Construa um `SEOData` e passe-o diretamente para `@seo` ou para a facade `SEO`, sem precisar chamar `app(TagRenderer::class)->render(...)`:

```php
use Rankbeam\Seo\Data\SEOData;

return view('search.results', [
    'seo' => new SEOData(
        title: "Results for \"{$query}\"",
        description: "Browse {$count} matches for {$query}.",
        ogImage: '/images/search-share.jpg',   // relative is fine — see below
    ),
]);
```

```blade
<head>
    @seo($seo)
</head>
```

Um `SEOData` manual representa **valores definidos explicitamente**. Tudo o que você configura é preservado; apenas as lacunas da renderização são preenchidas:

- `canonical` e `og:url` são derivados da URL atual quando ausentes; um `canonical` explícito é preservado literalmente, incluindo os parâmetros de consulta;
- `title_suffix` só é adicionado quando ainda não está no título e é totalmente ignorado se o título já contém um termo da marca; consulte [`title_suffix_skip_when_contains`](/pt-BR/reference/configuration);
- caminhos relativos de `og:image` e `twitter:image` tornam-se absolutos com `url()`, que respeita o esquema atual e **não** força HTTPS;
- `og:site_name` e `locale` são preenchidos pela configuração e pelo idioma da aplicação.

A cadeia de prioridade do banco de dados — valores globais, por tipo de modelo, por rota e de `seo_meta` — **não** é mesclada a um `SEOData` manual. A saída usa os valores fornecidos e preenche apenas as lacunas acima.

O mesmo objeto funciona pela facade:

```php
SEO::render($seoData);     // HTML string
SEO::toArray($seoData);    // Vue/React structure
SEO::forInertia($seoData); // Inertia Head structure
```

## Um layout para diferentes tipos de página {#a-layout-pattern-that-scales}

Um único layout pode atender páginas de modelo, de rota e outros casos:

```blade
<head>
    @if(isset($seoModel))
        @seo($seoModel)
    @elseif(isset($seoRoute))
        @seoForRoute($seoRoute)
    @else
        @seo(null)
    @endif
</head>
```

Os controladores passam `'seoModel' => $post` ou `'seoRoute' => 'blog.index'` sem manipular o HTML.

## Diretivas individuais {#granular-directives}

Quando você precisa controlar tags separadamente, por exemplo ao combinar a saída com outro pacote:

| Diretiva | Saída |
|---|---|
| `@seoTitle($post)` | Apenas `<title>` |
| `@seoMeta($post)` | Apenas a metadescrição |
| `@seoCanonical($post)` | Apenas o link canônico, com a URL atual como alternativa |
| `@seoRobots($post)` | Apenas a tag robots, **sempre emitida**: a diretiva é uma escolha explícita e não aplica a supressão do valor padrão usada por `@seo` |
| `@seoSchema($post)` | Apenas o `<script>` JSON-LD, válido no head ou no body |

Todas aceitam a mesma expressão `($model, $route, $locale)` de `@seo`, ou nenhum argumento para a página atual.

## Alternativas hreflang {#hreflang-alternates}

Modelos com `HasSEO` podem fornecer links hreflang diretamente pelo resolvedor:

```php
public function getSEOAlternates(): ?array
{
    return [
        ['hreflang' => 'en', 'href' => route('posts.show', ['locale' => 'en', 'post' => $this])],
        ['hreflang' => 'it', 'href' => route('posts.show', ['locale' => 'it', 'post' => $this])],
    ];
}
```

Use URLs absolutas. `@seo($post)` resolve essas entradas e gera uma tag `<link rel="alternate" hreflang="..." href="...">` para cada uma. Os códigos são primeiro convertidos para BCP 47, como `it_IT` → `it-IT`. As políticas `seo.hreflang` podem adicionar a referência à própria página e `x-default`; a auditoria gratuita aponta códigos inválidos, duplicados ou ausência da autorreferência. Consulte [conteúdo multilíngue](/pt-BR/guide/multilingual#hreflang).

## Escape e segurança {#escaping-and-safety}

Valores de texto passam por `e()`. O JSON-LD é codificado com `JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP`, para que um `</script>` no conteúdo do usuário não possa encerrar o elemento script.
