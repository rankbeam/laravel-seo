---
description: "Registre fontes de sitemap a partir de models, funções ou listas de URLs e gere um índice XML servido em /sitemap.xml."
---

# Registro de sitemaps

O pacote gera um XML por fonte e um índice, servidos em `/sitemap.xml` e `/sitemap-{name}.xml`. A geração usa [spatie/laravel-sitemap](https://github.com/spatie/laravel-sitemap):

```bash
composer require spatie/laravel-sitemap
```

## Registrar fontes {#registering-sources}

Registre fontes nomeadas no `boot()` de um service provider:

```php
use App\Models\Post;
use Rankbeam\Seo\Facades\SEO;

// A model class — every (indexable) record's getUrlForSEO()
SEO::sitemaps()->register('posts', Post::class);

// A closure returning URLs
SEO::sitemaps()->register('pages', fn () => ['/about', '/contact']);

// Any iterable of URLs
SEO::sitemaps()->register('legal', ['/imprint', '/privacy']);
```

Cada fonte produz `sitemap-{name}.xml`; `sitemap.xml` lista os arquivos. O registro também oferece `has($name)`, `names()`, `forget($name)` e `flush()`.

## Fontes pela configuração {#config-driven-sources}

Você também pode definir models e URLs estáticas em `config/seo.php`:

```php
'sitemap' => [
    'models' => [
        \App\Models\Post::class => ['priority' => 0.8, 'changefreq' => 'weekly'],
    ],
    'static_urls' => [
        ['url' => '/', 'priority' => 1.0, 'changefreq' => 'daily'],
    ],
],
```

::: info O registro tem prioridade
A descoberta automática ignora models já cobertos por uma fonte nomeada. Registrar `posts` não gera também um `sitemap-post.xml`.
:::

## Gerar {#generating}

```bash
php artisan seo:sitemap
```

Os arquivos são gravados em `seo.sitemap.disk`, por padrão `public`. Agende o comando para mantê-los atualizados:

```php
// routes/console.php or bootstrap/app.php scheduling
Schedule::command('seo:sitemap')->daily();
```

Sitemaps acima de `seo.sitemap.max_urls_per_sitemap`, por padrão 50.000 conforme o limite XML, são divididos automaticamente.

## Servir os arquivos {#serving}

As rotas do pacote servem o resultado da geração com cabeçalhos XML, de cache e `X-Robots-Tag: noindex`:

- `/sitemap.xml`: índice ou sitemap único.
- `/sitemap-posts.xml`: fonte nomeada.

Se você serve seus próprios arquivos estáticos, desative as rotas:

```php
// config/seo.php
'routes' => ['enabled' => false],
```

## Visualização legível no navegador {#styled-sitemap-in-the-browser}

O Rankbeam associa uma folha XSL ao XML para mostrar uma tabela com URL, `lastmod`, frequência de atualização, prioridade, quantidade de imagens e versões de idioma, além de notas de validação.

![Sitemap do Rankbeam exibido como tabela legível com a identidade visual do produto](/sitemap-styled.png)

Cada sitemap referencia a folha:

```xml
<?xml version="1.0" encoding="UTF-8"?>
<?xml-stylesheet type="text/xsl" href="https://your-app.test/sitemap.xsl"?>
<urlset ...>
```

A instrução altera a apresentação para pessoas; o documento continua sendo XML legível por buscadores. O índice e os arquivos filhos usam o mesmo estilo.

A função vem **ativada por padrão**. Ela acrescenta apenas uma instrução, sem dados extras nem processamento por registro, diferentemente das extensões de imagens e idiomas.

::: warning Requer spatie/laravel-sitemap 8.1 ou superior
A instrução usa `setStylesheet()`, disponível desde 8.1. Com uma versão anterior, a geração continua funcionando e produz XML sem estilo. `composer update spatie/laravel-sitemap` permite atualizar se as restrições da aplicação forem compatíveis.
:::

Para desativar o estilo:

```php
// config/seo.php
'sitemap' => [
    'stylesheet' => ['enabled' => false],
],
```

### Notas de validação {#validation-notes}

A visualização sinaliza dois casos que consegue verificar no navegador:

- **Ausência de `lastmod`:** a data faltante é indicada, nunca inventada.
- **URLs não absolutas:** um `<loc>` que não contenha uma URL `http(s)` absoluta.

### Hospedar a folha de estilo {#self-hosting-the-stylesheet}

Por padrão, o pacote serve a folha em `/sitemap.xsl`. O navegador exige a **mesma origem** do sitemap. Se os arquivos estiverem em um CDN, publique a folha nessa origem e configure sua URL:

```bash
php artisan vendor:publish --tag=seo-assets
```

```php
// config/seo.php
'sitemap' => [
    'stylesheet' => [
        'url' => 'https://cdn.example.com/vendor/seo/sitemap.xsl',
    ],
],
```

::: info Preserve o escape de saída
Todos os valores, inclusive URLs, passam pelo escape XSLT. Um `<loc>` só vira link se usar `http(s)`. Assim, uma URL não injeta marcação nem links `javascript:` na visualização. Ao personalizar o `.xsl`, preserve esse comportamento e não acrescente `disable-output-escaping`.
:::

## O que é incluído {#what-gets-included}

Fontes de models incluem registros resolvidos como indexáveis. Se as diretivas robots contiverem `noindex`, o registro fica fora. As URLs vêm de `getUrlForSEO()`, também usado para o canonical.

## Extensões de imagens e hreflang {#image-hreflang-extensions}

Duas extensões opcionais acrescentam os dados já resolvidos para cada model. Ambas vêm **desativadas por padrão**:

```php
'sitemap' => [
    'images' => true,      // <image:image> per URL
    'alternates' => true,  // <xhtml:link rel="alternate"> per URL
],
```

Elas se aplicam a models com `HasSEO`, usando o `seoData()` completo:

- **`images`** acrescenta uma entrada de [sitemap de imagens do Google](https://developers.google.com/search/docs/crawling-indexing/sitemaps/image-sitemaps) com a mesma imagem de `og:image`. Sem imagem própria, usa `default_og_image`. Ative apenas se as imagens por URL forem úteis para seu conteúdo.
- **`alternates`** acrescenta `<xhtml:link rel="alternate" hreflang="…">` a partir de `getSEOAlternates()`, como no `<head>`. Retorne URLs absolutas:

```php
public function getSEOAlternates(): ?array
{
    return [
        ['hreflang' => 'en', 'href' => route('posts.show', [$this, 'locale' => 'en'])],
        ['hreflang' => 'fr', 'href' => route('posts.show', [$this, 'locale' => 'fr'])],
        ['hreflang' => 'x-default', 'href' => route('posts.show', $this)],
    ];
}
```

::: warning Referências recíprocas e autorreferência
Cada versão deve listar **a própria URL e as demais**, com referências recíprocas. `getSEOAlternates()` precisa devolver o conjunto completo em todas as versões. Use códigos `language[-Script][-REGION]` válidos ou `x-default`, com URLs `http(s)` absolutas. Entradas sem `hreflang` ou `href` preenchidos são ignoradas.

Antes da escrita, aplicam-se as [políticas `seo.hreflang` (EN)](/guide/multilingual#hreflang): normalização (`it_IT` → `it-IT`) e inclusão opcional da própria página e de `x-default`. A lista é a mesma do `<head>`. A auditoria gratuita sinaliza `hreflang_invalid_code`, `hreflang_duplicate_code` e `hreflang_missing_self`; reciprocidade exige o rastreamento do Pro.
:::

::: info Custo em catálogos grandes
Ativar qualquer extensão resolve o `seoData()` completo por URL: padrões, valores calculados e getters `getSEO*()`. Cada registro pode causar operações de cache ou banco, além das consultas dos seus getters. O processamento foi pensado para `seo:sitemap` agendado, não para uma requisição web. Meça o custo antes de ativar perto de 50.000 URLs e mantenha as opções desligadas se não precisar desses dados.
:::

::: tip Configuração já publicada
`config/seo.php` é combinado sem mesclagem recursiva. Uma configuração publicada antes dessas extensões não recebe automaticamente `sitemap.images` e `sitemap.alternates`. As variáveis `SEO_SITEMAP_IMAGES` e `SEO_SITEMAP_ALTERNATES` não bastam: acrescente as chaves ao array publicado ou publique novamente a configuração.
:::

## Controle completo com tags Spatie {#full-control-hand-built-spatie-tags}

Para legendas de imagens, vídeos, notícias ou listas hreflang próprias, retorne um [`Spatie\Sitemap\Tags\Url`](https://github.com/spatie/laravel-sitemap#adding-images) construído manualmente na fonte. O gerador o preserva e não acrescenta suas extensões:

```php
use Spatie\Sitemap\Tags\Url;

SEO::sitemaps()->register('videos', fn () => Video::query()
    ->get()
    ->map(fn (Video $video) => Url::create($video->url)
        ->addImage($video->thumbnail_url, caption: $video->title)
        ->addVideo(
            thumbnailLoc: $video->thumbnail_url,
            title: $video->title,
            description: $video->description,
            contentLoc: $video->file_url,
        )
        ->addAlternate($video->frenchUrl, 'fr')
    ));
```

O mesmo vale por registro: se um model implementa `Sitemapable` e `toSitemapTag()` retorna um `Url`, ele é emitido sem alterações.
