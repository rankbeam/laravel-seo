---
description: "Opções de config/seo.php por camada de resolução, com o valor padrão de cada configuração."
---

# Configuração {#configuration}

Publique o arquivo de configuração:

```bash
php artisan vendor:publish --tag=seo-config
```

Todas as opções abaixo ficam em `config/seo.php`. Os valores exibidos são os padrões.

## Padrões do site (camada 1) {#site-wide-defaults-layer-1}

```php
'site_name'                       => env('APP_NAME', 'My Site'),
'title_suffix'                    => ' | ' . env('APP_NAME', 'My Site'),
'title_suffix_skip_when_contains' => [],   // brand tokens, e.g. ['Acme']
'default_og_image'                => env('SEO_DEFAULT_OG_IMAGE', '/images/og-default.jpg'),
'default_robots'                  => env('SEO_DEFAULT_ROBOTS', 'index,follow'),
'default_twitter_card'            => env('SEO_DEFAULT_TWITTER_CARD', 'summary_large_image'),
'twitter_site'                    => env('SEO_TWITTER_SITE'),     // @username, without @
'twitter_creator'                 => env('SEO_TWITTER_CREATOR'),
'favicon'                         => '/favicon.ico',
```

`title_suffix` é acrescentado ao título resolvido, exceto quando ele já termina com esse sufixo.

`title_suffix_skip_when_contains` evita repetir a marca. Se o título resolvido já contém um dos termos da lista **como palavra inteira**, o sufixo é omitido. A comparação ignora maiúsculas e minúsculas e respeita os limites das palavras: `Acmestic` não corresponde a `Acme`. O padrão `[]` mantém o comportamento anterior.

## Política de renderização de robots {#robots-rendering-policy}

```php
'robots' => [
    'emit_default' => env('SEO_EMIT_DEFAULT_ROBOTS', false),
],
```

O `<head>` omite `<meta name="robots">` quando a diretiva resolvida é igual a `default_robots`. Com o padrão `index,follow`, a tag seria redundante: sua ausência permite esse mesmo comportamento ao rastreador. Uma diretiva **diferente**, como `noindex`, `nofollow` ou `max-snippet:-1`, é sempre emitida sem alterações. Defina `emit_default` como `true` para sempre renderizar a tag, restaurando o comportamento anterior à versão 3.1. A diretiva granular `@seoRobots` não muda: por ser uma escolha explícita, ela sempre renderiza a tag. O [Contrato de renderização](/pt-BR/contributing/rendering-contract) documenta as diretivas aceitas e sua precedência.

## Proteção de indexação (ambientes fora de produção) {#indexing-guard-non-production-safety-net}

```php
'indexing_guard' => [
    'enabled' => env('SEO_INDEXING_GUARD', false),
    'allowed_environments' => ['production'],
    'send_header' => env('SEO_INDEXING_GUARD_HEADER', true),
],
```

Quando ativada em um ambiente **fora** de `allowed_environments`, a proteção força `noindex,nofollow` em todas as páginas, acima de toda a cadeia de precedência, inclusive dos valores salvos por página. Também envia o cabeçalho `X-Robots-Tag` correspondente, emite um `robots.txt` que bloqueia todo o rastreamento e exibe um aviso em `seo:audit`. Nos ambientes permitidos — `production` por padrão — ela não interfere.

O recurso vem **desativado**, preservando a saída até você optar por usá-lo. Ative com `SEO_INDEXING_GUARD=true` ou desative com `SEO_INDEXING_GUARD=false`. Para alterar a lista permitida, use `SEO_INDEXING_GUARD_ALLOWED`, com valores separados por vírgula; curingas de `Str::is()`, como `prod*`, são aceitos. Uma lista de configuração vazia (`[]`) protege todos os ambientes; uma variável de ambiente em branco pode continuar usando o padrão `production`, conforme a configuração publicada.

`send_header`, ativado por padrão dentro da proteção, envia `X-Robots-Tag: noindex,nofollow` em cada resposta que passa pela aplicação. Isso cobre PDFs, feeds e imagens sem `<meta robots>`. O middleware só é registrado quando a proteção está ativada; arquivos estáticos servidos fora do PHP exigem configuração no servidor. Consulte o [guia de proteção de indexação](/pt-BR/guide/indexing-guard).

## URLs canônicas {#canonical-urls}

```php
'canonical' => [
    'query_whitelist' => [],   // e.g. ['page']
],
```

Uma URL canônica **derivada** pelo resolvedor, a partir da requisição ou de `getUrlForSEO()` do modelo, perde a query string por padrão. Parâmetros de rastreamento, filtro e ordenação podem apontar para o mesmo conteúdo. As chaves listadas em `query_whitelist` são **preservadas**, na ordem configurada; as demais continuam sendo removidas. Um uso comum é `page` em arquivos paginados, já que `/blog?page=2` corresponde a uma página diferente de `/blog`.

Uma URL canônica **definida explicitamente**, pelo administrador ou por uma camada de maior precedência, é emitida sem alterações, incluindo a query string. A lista permitida só controla o fallback derivado. O padrão `[]` mantém a remoção de todos os parâmetros nesse fallback.

## Ativação de recursos {#feature-toggles}

```php
'features' => [
    'auto_create_meta' => env('SEO_AUTO_CREATE_META', true),
    'sitemap'          => env('SEO_SITEMAP_ENABLED', true),
    'schema'           => env('SEO_SCHEMA_ENABLED', true),
    'multilingual'     => env('SEO_MULTILINGUAL_ENABLED', false),
],
```

`auto_create_meta` cria uma linha vazia em `seo_meta` quando um modelo com `HasSEO` é criado. Seeders que usam `WithoutModelEvents` não acionam esse comportamento.

## Palavras-chave de foco {#focus-keywords}

```php
'keywords' => [
    'enabled' => env('SEO_KEYWORDS_ENABLED', false),
],
```

Esta opção ativa o **fluxo de palavras-chave de foco**. Com `false`, o padrão, a ausência de uma palavra-chave não gera avisos em [`seo:audit`](/pt-BR/guide/audit) nem na varredura Pro. Assim, uma aplicação que não usa esse recurso não recebe cobranças por ele. Ative a opção quando começar a definir palavras-chave, por exemplo com o [campo do Filament](/pt-BR/guide/filament). A auditoria gratuita, a varredura Pro e o editor Pro passam a informar `missing_focus_keyword` nas páginas que ainda não têm uma, usando a mesma configuração.

## Auditoria gratuita (`seo:audit`) {#free-audit-seo-audit}

```php
'audit' => [
    // \App\Models\Post::class, \App\Models\Page::class
    'models' => [],
],
```

Define os modelos auditados pelo comando gratuito [`seo:audit`](/pt-BR/guide/audit) quando você não passa `--model`. Cada modelo deve usar o trait `HasSEO`. Se a lista estiver vazia, o comando usa os modelos registrados em `sitemap.models`.

## Fallbacks calculados (camada 5) {#computed-fallbacks-layer-5}

```php
'computed' => [
    // Ordered attribute candidates for the description fallback.
    // Empty = built-in chain: excerpt, summary, description, intro,
    // lead, teaser, content, body, text, article.
    'description_fields' => [],

    // Truncation length — word boundary, no ellipsis.
    'description_max_length' => 160,

    // Social / Open Graph image selection.
    'image_selection' => [
        // 'first' (default) — first non-empty source wins, nothing measured.
        // 'best' — score local candidates by closeness to the ideal below,
        //          skipping any under the minimum.
        'strategy' => env('SEO_IMAGE_SELECTION', 'first'),
        'minimum_width' => 200,
        'minimum_height' => 200,
        'ideal_width' => 1200,
        'ideal_height' => 630,
    ],
],
```

Na estratégia opcional `best`, o builder avalia uma lista ordenada de imagens candidatas: primeiro `getSEOImage()`, que continua sendo a candidata prioritária; depois `getSEOImages()`, os campos de imagem comuns, a primeira imagem do conteúdo e a imagem padrão configurada. A seleção considera a proximidade das dimensões em pixels ao tamanho ideal e **descarta imagens abaixo do mínimo**. Apenas imagens **locais** são medidas: caminhos relativos em `public/`, arquivos do disco público ou URLs absolutas do próprio servidor. URLs remotas nunca são buscadas e servem apenas como fallback. Se nenhuma imagem local atingir o mínimo, a seleção volta à primeira correspondência; `best` não retorna menos do que `first`. Exponha as candidatas no modelo:

```php
use Rankbeam\Seo\Data\SEOImageCandidate;

public function getSEOImages(): iterable
{
    return [
        SEOImageCandidate::make($this->hero_url)->priority(100),
        SEOImageCandidate::make($this->thumbnail_url)->priority(10),
    ];
}
```

## Sitemaps {#sitemaps}

```php
'sitemap' => [
    'disk'                 => env('SEO_SITEMAP_DISK', 'public'),
    'path'                 => 'sitemap.xml',
    'max_urls_per_sitemap' => 50000,

    // ModelClass::class => ['priority' => 0.8, 'changefreq' => 'weekly']
    'models' => [],

    // [['url' => '/', 'priority' => 1.0, 'changefreq' => 'daily']]
    'static_urls' => [],

    'ping_search_engines' => env('SEO_SITEMAP_PING', false),
],
```

Para fontes registradas por código, consulte o [guia do registro de sitemaps](/pt-BR/guide/sitemaps).

## Schema (JSON-LD) {#schema-json-ld}

```php
'schema' => [
    'organization' => [
        'name' => env('APP_NAME'),
        'url'  => env('APP_URL'),
        'logo' => env('SEO_ORGANIZATION_LOGO'),
        // 'sameAs' => [],   // social profile URLs
    ],
    'publisher' => [
        'name' => env('APP_NAME'),
        'logo' => env('SEO_PUBLISHER_LOGO'),
    ],
    'website' => [
        'name' => env('APP_NAME'),
        'url'  => env('APP_URL'),
        // 'potentialAction' => [],   // SearchAction for sitelinks search box
    ],
],
```

Estas opções alimentam os nós do [grafo de schema](/pt-BR/guide/schema).

## Rotas {#routes}

```php
'routes' => [
    'enabled'        => env('SEO_ROUTES_ENABLED', true),
    'prefix'         => '',
    'middleware'     => ['web'],
    'api_prefix'     => 'api/seo',
    'api_middleware' => ['api'],
],
```

Defina `enabled => false` quando a aplicação já servir seu próprio `/sitemap.xml` estático.

## Cache {#cache}

```php
'cache' => [
    'prefix' => 'seo_',
    'store'  => env('SEO_CACHE_STORE'),   // null = app default

    // Resolver result cache — the scale lever for hot frontends. OFF by default.
    'resolver' => [
        'enabled' => env('SEO_RESOLVER_CACHE', false),
        'ttl'     => env('SEO_RESOLVER_CACHE_TTL', 3600),
    ],
],
```

### Cache do resultado da resolução {#resolver-result-cache}

O `SEOResolver` percorre toda a cadeia de precedência a **cada** renderização: configuração → padrões globais, do tipo de modelo e da rota → valores calculados do modelo → `seo_meta` explícito → sufixo do título, URL canônica e schema. Em um site com tráfego alto — a aplicação de referência recebe cerca de 20 mil requisições por dia — isso representa várias leituras do banco por página.

Ative `cache.resolver.enabled` para armazenar o SEO completamente resolvido de um modelo. Um **acerto no cache evita toda a cadeia de precedência**. No benchmark do pacote, um acerto com cache aquecido faz **zero** consultas ao banco, enquanto uma resolução sem cache lê novamente o `seo_meta` do modelo. O conteúdo armazenado é um array simples, reconstruído por `SEOData::fromArray()`, nunca um objeto. Isso evita `__PHP_Incomplete_Class` no Laravel 13, cujo padrão é `cache.serializable_classes = false`.

O resolvedor usa o `store` configurado acima. Em produção, escolha um **cache persistente e compartilhado**, como `redis` ou `memcached`, para que todos os workers web e de fila vejam os valores e suas invalidações. Deixe o recurso desativado até ter essa estrutura.

A **invalidação automática** mantém o resultado sincronizado nas alterações acompanhadas pelo pacote. As entradas usam `(classe do modelo, id, locale, rota, URL da requisição)` como chave e são removidas quando:

- a linha `seo_meta` da página é **salva ou excluída**, seja por `saveSEO()`, Filament ou gravação direta através de `SEOMeta`;
- muda um **campo de conteúdo** do modelo listado em `getSEOContentFields()`. O padrão inclui os campos usados pelos fallbacks internos: títulos, `excerpt`, `summary`, `content`, `body`, `text`, `article` e imagens comuns como `featured_image`, `thumbnail`, `cover_image`, `og_image`, `photo`, `banner` e `hero_image`. Sobrescreva o método se o seu SEO depender de outras colunas;
- muda **qualquer linha de `seo_defaults`**. Como um padrão pode afetar qualquer modelo, isso limpa todo o cache de resolução.

Em stores **com suporte a tags**, como `redis`, `memcached` e `array`, as entradas de um modelo são removidas pelas **tags**. Nos stores **sem tags**, como `file` e `database`, o pacote usa um **identificador de versão por modelo**. Nenhum dos caminhos precisa percorrer todas as chaves.

::: tip
Apenas resoluções associadas a modelos entram no cache. `SEO::render()` e `@seo()` com um `SEOData` montado manualmente, assim como `@seoForRoute()` para uma rota sem modelo, continuam resolvendo os valores a cada chamada.
:::

::: warning
O cache reflete `updated_at` e o `modified_time` calculado na última alteração de um **campo de conteúdo**, ou até o TTL expirar. Um `touch()` que altera apenas `updated_at`, sem mudar uma coluna de `getSEOContentFields()`, não força nova resolução; `article:modified_time` pode ficar desatualizado por até um TTL. Inclua colunas específicas da aplicação em `getSEOContentFields()` quando elas precisarem invalidar o resultado imediatamente.
:::
