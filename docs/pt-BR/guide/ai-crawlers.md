---
description: "Gere robots.txt e, se necessário, ai.txt a partir de políticas para rastreadores de busca, assistentes e treinamento de IA."
---

# Controle de rastreadores de IA (robots.txt / ai.txt) {#ai-crawler-control-robots-txt-ai-txt}

Provedores de IA percorrem a Web com robôs identificados por nome. Muitos consultam **robots.txt** para decidir o que podem buscar. O Rankbeam inclui um catálogo mantido e gera uma `robots.txt` gerenciada, mais uma `ai.txt` opcional, a partir de regras `allow` / `disallow`. Você pode permitir busca e assistentes e recusar treinamento.

É uma função gratuita do núcleo. O Pro acrescenta um [registro de visitas de bots de IA](/pt-BR/pro/ai-bot-monitor), para observar os acessos recebidos.

## Política padrão {#the-default-policy}

Cada robô tem uma finalidade principal:

| Finalidade | Uso | Padrão |
|---|---|---|
| `ai_search` | Buscar páginas para um índice de pesquisa com IA | **allow** |
| `ai_assistant` | Buscar uma página em tempo real a pedido de uma pessoa | **allow** |
| `ai_training` | Coletar conteúdo para treinar um modelo | **disallow** |

A configuração inicial permite busca e assistentes, como os do ChatGPT Search e Perplexity, e recusa treinamento. Você pode alterar cada decisão.

::: warning Acesso não significa citação
Permitir um rastreador torna a busca do conteúdo possível. Não garante descoberta, indexação, posições, inclusão em respostas, citação ou link para a fonte. A política descreve acesso, não os resultados posteriores.
:::

## Início rápido {#quick-start}

Mostre primeiro o bloco que seria publicado:

```bash
php artisan seo:robots-txt --print
```

Você pode usá-lo de duas formas.

### Opção A — acrescentar a uma robots.txt existente {#option-a-—-paste-the-block-into-your-existing-robots-txt}

Se já mantém `public/robots.txt`, obtenha apenas o bloco gerenciado e cole no arquivo:

```php
use Rankbeam\Seo\Facades\SEO;

echo SEO::robotsTxt()->aiDirectives();
```

```
# --- AI crawlers (managed by Rankbeam) ---

# GPTBot — OpenAI (AI training)
User-agent: GPTBot
Disallow: /

# Bytespider — ByteDance (AI training) — advisory: this bot may not honour robots.txt
User-agent: Bytespider
Disallow: /
...
```

### Opção B — deixar o Rankbeam gerenciar todo o arquivo {#option-b-—-let-rankbeam-manage-the-whole-file}

Gere a `robots.txt` completa, com seção geral, regras de IA, linha `Sitemap:` e referência a [llms.txt](/pt-BR/guide/sitemaps):

```bash
php artisan seo:robots-txt          # writes public/robots.txt
php artisan seo:robots-txt --ai-txt # also write public/ai.txt
```

Agende o comando para acompanhar a política:

```php
// routes/console.php
Schedule::command('seo:robots-txt')->daily();
```

Ou sirva dinamicamente: com `seo.ai_crawlers.route = true`, o pacote responde a `/robots.txt` a partir da configuração atual, sem gerar um arquivo antes.

::: warning O arquivo estático tem prioridade
O servidor normalmente serve `public/robots.txt` antes de o Laravel receber a requisição. A rota dinâmica vem desativada para evitar conflito silencioso com um arquivo esquecido. Use-a apenas sem uma `robots.txt` estática.
:::

## Limites de aplicação das regras {#honesty-about-enforcement}

robots.txt expressa um pedido, não uma barreira técnica. Muitos robôs declaram respeitá-lo, mas alguns agentes acionados por usuários (`ChatGPT-User`, `Perplexity-User`) e rastreadores de treinamento (`Bytespider`) não oferecem essa garantia. O Rankbeam marca essas linhas como `advisory`. Para bloquear um bot que não coopera, use regras no servidor ou na borda da rede: firewall, WAF ou regras de bots da Cloudflare. O [registro Pro](/pt-BR/pro/ai-bot-monitor) ajuda a identificar visitas observadas.

## Content Signals: preferências de uso {#content-signals-usage-preferences}

`Allow` e `Disallow` descrevem **acesso**. [Content Signals](https://contentsignals.org), apoiado pela Cloudflare, descreve o **uso desejado** do conteúdo após a coleta. Uma linha `Content-Signal:` em `User-agent: *` expressa três preferências:

| Sinal | Finalidade associada | Significado |
|---|---|---|
| `search` | `ai_search` | Criar índice de busca com links e trechos curtos |
| `ai-input` | `ai_assistant` | Usar a página como entrada de modelo em tempo real, por exemplo em RAG |
| `ai-train` | `ai_training` | Treinar ou ajustar um modelo |

A função vem **desativada por padrão** e não muda os bytes do arquivo até ser ativada. A linha é então derivada de `policy`: `allow` vira `yes`, `disallow` vira `no`.

```php
'ai_crawlers' => [
    'content_signals' => true,   // env: SEO_AI_CONTENT_SIGNALS
    // ...with the default policy, this emits, in the User-agent: * group:
    //   Content-Signal: search=yes, ai-input=yes, ai-train=no
],
```

Remover uma finalidade de `policy` omite seu sinal. Isso significa nenhuma preferência expressa, diferente de `yes` ou `no` explícito.

::: warning Preferências, não proteção técnica
Um robô pode ignorar Content Signals. Eles complementam regras de acesso e bloqueios de rede, sem substituí-los.
:::

## Configuração {#configuration}

```php
// config/seo.php
'ai_crawlers' => [
    'enabled' => true,
    'route'   => false,             // serve /robots.txt dynamically (off by default)
    'disk'    => 'public',
    'path'    => 'robots.txt',
    'ai_txt_path' => 'ai.txt',

    // Policy by purpose. A purpose left out is allowed.
    'policy' => [
        'ai_training'   => 'disallow',
        'ai_search'     => 'allow',
        'ai_assistant'  => 'allow',
        'search_engine' => 'allow',   // Yandex, Baidu, Naver, Seznam, … (3.15)
    ],

    // Per-bot overrides, keyed by catalog id (win over the purpose policy).
    'overrides' => [
        'gptbot' => 'allow',          // e.g. opt GPTBot back in
        'baiduspider' => 'disallow',  // e.g. keep a search engine you don't serve off your bandwidth
    ],

    // 'blocked' = only disallowed bots get a line (lean file);
    // 'all'     = every known bot gets an explicit allow/disallow (auditable).
    'list' => 'blocked',

    // Emit a Content-Signal usage-preference line (off by default), derived
    // from `policy` above. See "Content signals" above.
    'content_signals' => false,

    // The general `User-agent: *` section: true = permissive default,
    // a string = your own rules verbatim, false = omit.
    'general' => true,

    'include_sitemap' => true,
    'sitemap_url'     => null,        // null = derive from the sitemap route
    'include_llms_txt' => true,
],
```

`overrides` substitui a política da finalidade para um bot específico. As chaves são os **IDs do catálogo**, como `gptbot`, `claudebot`, `perplexitybot` ou `google-extended`.

## Catálogo {#the-catalog}

`SEO::aiCrawlers()` fornece o mesmo catálogo usado pelo registro Pro para reconhecer visitantes. As regras e a observação compartilham essa fonte.

```php
SEO::aiCrawlers()->all();               // every known AiCrawler
SEO::aiCrawlers()->get('gptbot');       // one bot
SEO::aiCrawlers()->actionFor('gptbot'); // 'allow' | 'disallow' (resolved policy)
SEO::aiCrawlers()->match($userAgent);   // identify a request UA, or null
```

Ele inclui OpenAI (GPTBot, OAI-SearchBot, ChatGPT-User), Anthropic (ClaudeBot, Claude-SearchBot, Claude-User), Google (Google-Extended), Perplexity, Apple (Applebot-Extended), Common Crawl (CCBot), Meta, Amazon e ByteDance, entre outros, com finalidade documentada e token robots.txt.

### Buscadores regionais {#regional-search-engines}

Desde 3.15, o catálogo também contém rastreadores de buscadores clássicos além de Google e Bing. Eles têm finalidade `search_engine` e são **permitidos por padrão**:

| ID | Token | Operador |
|---|---|---|
| `yandex` | `Yandex` | Yandex, Rússia; o token base abrange seus robôs |
| `baiduspider` | `Baiduspider` | Baidu, China |
| `yeti` | `Yeti` | Naver, Coreia |
| `seznambot` | `SeznamBot` | Seznam, Tchéquia |
| `sogou` | `Sogou web spider` | Sogou, China |
| `360spider` | `360Spider` | Qihoo 360, China |
| `coccocbot` | `coccocbot-web` | Cốc Cốc, Vietnã |
| `duckduckbot` | `DuckDuckBot` | DuckDuckGo |

Eles seguem `policy` e `overrides`. Por exemplo, `'overrides' => ['baiduspider' => 'disallow', 'sogou' => 'disallow']` pede a esses robôs que não rastreiem o site. Com `'list' => 'all'`, cada um recebe uma regra explícita.

São excluídos de `all()` e `match()` sem solicitação por `searchEngines()`, `all(true)` ou `match($ua, true)`. Assim, o registro e os contadores de IA mantêm seu significado:

```php
SEO::aiCrawlers()->searchEngines();          // the eight engines
SEO::aiCrawlers()->get('yandex');            // works for both lists
SEO::aiCrawlers()->match($userAgent, true);  // identify an engine too
```

Reconhecer um robô não garante visibilidade nem posicionamento no buscador. As tags de verificação (`yandex-verification`, `baidu-site-verification`, `naver-site-verification`, `seznam-wmt`) ficam em `seo.verification`; veja [conteúdo multilíngue](/pt-BR/guide/multilingual#site-verification).
