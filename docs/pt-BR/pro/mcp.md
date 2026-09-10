---
description: "Servidor MCP por stdio, sem dependências adicionais, para um assistente ler e, quando autorizado, editar o SEO da aplicação Laravel. PHP 8.2–8.4 e Laravel 11–13."
---

# Servidor MCP {#mcp-server}

O servidor MCP do Rankbeam permite que um assistente de IA **leia e, opcionalmente, edite o SEO do site** pelo [Model Context Protocol](https://modelcontextprotocol.io). Conecte um cliente como Claude Code, Claude Desktop, Cursor ou Codex à aplicação Laravel para resolver metadados, executar auditorias, consultar a pontuação Pro, ler a política de rastreadores de IA e, quando autorizado, gravar alterações.

É um servidor stdio **sem dependências adicionais**, SDK ou novos pacotes. Funciona com PHP 8.2–8.4 e Laravel 11, 12 e 13, respeitando os requisitos PHP de cada versão Laravel.

::: tip Recurso Pro
O servidor acompanha `rankbeam/laravel-seo-pro`. O padrão é **somente leitura**; a edição exige ativação na configuração e uma lista de modelos permitidos.
:::

## O que o assistente pode fazer {#what-the-assistant-can-do}

### Ferramentas de análise (sempre disponíveis) {#analysis-tools-always-available}

| Ferramenta | Função |
| --- | --- |
| `seo_resolve` | Metadados completamente resolvidos de um registro: título, descrição, canonical, robots, Open Graph e JSON-LD que a página renderizaria. |
| `seo_audit` | [Auditoria de metadados](/pt-BR/guide/audit) dentro da aplicação para um registro ou os primeiros N; mesmas verificações de `seo:audit`, sem fila. |
| `seo_score` | [Pontuação Pro](/pt-BR/pro/scoring) persistida mais recente de um registro, com valor de 0 a 100 e classificação. |
| `seo_robots_directives` | [Diretivas gerenciadas de rastreadores de IA](/pt-BR/guide/ai-crawlers) e política allow/disallow resolvida por bot. |
| `validate_schema` | Valida um objeto JSON-LD ou o grafo resolvido de um modelo permitido usando o validador de dados estruturados do Core e suas regras por `@type`. |
| `analyze_robots` | Decisão allow/disallow **por rastreador de IA conhecido** e sua origem: sobrescrita por bot, política por finalidade ou padrão. A política é global para o site. |
| `debug_social_share` | Open Graph e Twitter Card resolvidos para o registro, após fallbacks, com observações consultivas sobre o cartão. |
| `check_meta` | Estado de título, descrição, canonical, robots e `og:image`, com presença, tamanho e ocorrências de auditoria de um registro. |

### Ferramentas de conteúdo (sempre disponíveis) {#site-content-tools-always-available}

Essas ferramentas permitem ao assistente listar e pesquisar as páginas do site, além de analisar um registro previamente conhecido.

| Ferramenta | Função |
| --- | --- |
| `list_pages` | Lista registros com SEO de um modelo **permitido**, com URL e título efetivo. Aceita paginação por `limit` e `offset`. |
| `search_pages` | Pesquisa páginas de um modelo **permitido** usando [Laravel Scout](https://laravel.com/docs/scout), quando disponível, ou SQL `LIKE` seguro em title/name/headline e metadados SEO associados. Retorna URL, título e trecho. |

### Ferramentas operacionais (ativação opcional) {#ops-tools-opt-in}

Leem o estado das varreduras e alteram a configuração do site. Como a edição, dependem de **`allow_edits`**; ficam indisponíveis e não são anunciadas no servidor padrão de leitura.

| Ferramenta | Função |
| --- | --- |
| `list_issues` | Ocorrências SEO atualmente abertas, mantidas entre execuções, e cabeçalho da [varredura](/pt-BR/pro/scan-issues) mais recente. Filtros por `severity` e `type`. |
| `trigger_scan` | Inicia varredura de um registro permitido, retornando a execução, ou de todos os alvos. Usa fila por padrão; `sync: true` executa na chamada. |
| `create_redirect` | Cria redirecionamento de caminho ou regex para um destino, com status `301`, `302`, `307`, `308` ou `410`, usando os validadores do próprio modelo. |

### Ferramenta de edição (ativação opcional) {#edit-tool-opt-in}

| Ferramenta | Função |
| --- | --- |
| `seo_save_meta` | Grava título, descrição, canonical, robots, Open Graph, Twitter e JSON-LD de um registro **permitido** por `saveSEO()`. |

As ferramentas operacionais e `seo_save_meta` **não aparecem em `tools/list` nem podem ser executadas** sem ativar a edição. Veja [Segurança](#security). O servidor de leitura não as anuncia ao assistente.

## Conectar um cliente de IA {#wiring-an-ai-client}

O servidor usa JSON-RPC por **stdio**. O cliente inicia um comando Artisan e troca mensagens pelos canais do processo. O mesmo servidor funciona com os clientes abaixo.

::: tip Um comando para todos os clientes
O comando é `php artisan seo-pro:mcp`, iniciado **na raiz da aplicação**. Se `php` não estiver no `PATH` do cliente — comum no Windows e em aplicativos gráficos — informe os **caminhos absolutos de `php` e `artisan`**. O Artisan inicializa a aplicação a partir do diretório do próprio script, sem precisar de `cwd` nesse caso.
:::

### Claude Code (CLI) {#claude-code-cli}

Registre o servidor a partir da **raiz da aplicação**:

```bash
claude mcp add rankbeam-seo -- php artisan seo-pro:mcp
```

Confira a conexão:

```bash
claude mcp list
# rankbeam-seo: php artisan seo-pro:mcp - ✔ Connected
```

No Windows com Laravel Herd, use caminhos absolutos para não depender do diretório de lançamento:

```bash
claude mcp add rankbeam-seo -- "C:\Users\you\.config\herd\bin\php84\php.exe" "C:\path\to\app\artisan" seo-pro:mcp
```

### Claude Desktop {#claude-desktop}

Abra **Settings → Developer → Edit Config** ou edite diretamente:

- **Windows**: `%APPDATA%\Claude\claude_desktop_config.json`.
- **macOS**: `~/Library/Application Support/Claude/claude_desktop_config.json`.

```json
{
  "mcpServers": {
    "rankbeam-seo": {
      "command": "php",
      "args": ["/absolute/path/to/your/app/artisan", "seo-pro:mcp"]
    }
  }
}
```

No **Windows**, use o caminho absoluto de `php.exe` e escape cada barra invertida no JSON:

```json
{
  "mcpServers": {
    "rankbeam-seo": {
      "command": "C:\\Users\\you\\.config\\herd\\bin\\php84\\php.exe",
      "args": ["C:\\path\\to\\app\\artisan", "seo-pro:mcp"]
    }
  }
}
```

Encerre completamente e abra novamente o Claude Desktop. As ferramentas aparecem no controle de ferramentas da barra de mensagem.

### Cursor {#cursor}

Crie `.cursor/mcp.json` no projeto ou `~/.cursor/mcp.json` para todos os projetos. Use o caminho absoluto de `artisan`:

```json
{
  "mcpServers": {
    "rankbeam-seo": {
      "command": "php",
      "args": ["/absolute/path/to/your/app/artisan", "seo-pro:mcp"]
    }
  }
}
```

No Windows, use `php.exe` absoluto e barras invertidas escapadas, como no exemplo Claude Desktop. Ative o servidor em **Settings → MCP**.

### Codex CLI {#codex-cli}

```bash
codex mcp add rankbeam-seo -- php artisan seo-pro:mcp
codex mcp get rankbeam-seo   # transport: stdio, enabled
```

Como no Claude Code, use caminhos absolutos de `php` e `artisan` no Windows ou Herd.

Com a conexão configurada, o assistente pode chamar as ferramentas.

## Exemplo de conversa {#a-worked-conversation}

Esta troca foi registrada em uma aplicação de demonstração de um instituto médico, com 900 páginas. As saídas reais foram abreviadas. Os modelos permitidos eram `App\Models\Page` e `App\Models\PublicPage`, com `allow_edits` ativado.

> **Você:** Como está o SEO da página “Accoglienza”, Page id 1? Corrija o que estiver claramente errado.

O assistente primeiro **audita** o registro com `seo_audit { model: "App\\Models\\Page", id: 1 }`:

```json
[
  {
    "id": 1,
    "url": "http://localhost/accoglienza",
    "issue_count": 2,
    "issues": [
      { "code": "title_too_short", "severity": "notice", "field": "title", "message": "Title is only 17 characters (recommended min 30).", "context": { "length": 17, "min": 30 } },
      { "code": "description_too_short", "severity": "notice", "field": "description", "message": "Description is only 11 characters (recommended min 70).", "context": { "length": 11, "min": 70 } }
    ]
  }
]
```

Depois **resolve** os metadados atuais com `seo_resolve { model: "App\\Models\\Page", id: 1 }`. Encontra título de 17 caracteres e descrição de 11, abaixo dos mínimos recomendados, e **grava a correção** com `seo_save_meta`:

```json
{
  "model": "App\\Models\\Page",
  "id": 1,
  "title": "Accoglienza IDI | Istituto Dermopatico dell'Immacolata",
  "description": "Il reparto Accoglienza dell'IDI di Roma: accessi, orari di visita e servizi di supporto ai pazienti."
}
```

A ferramenta confirma a gravação e retorna o registro resolvido novamente:

```json
{ "saved": true, "fields": ["title", "description"], "resolved": { "title": "Accoglienza IDI | Istituto Dermopatico dell'Immacolata - IDI", "...": "…" } }
```

Uma **nova auditoria** confirma `issue_count: 0`:

```json
[ { "id": 1, "url": "http://localhost/accoglienza", "issue_count": 0, "issues": [] } ]
```

> **Assistente:** O título, com 17 caracteres, e a descrição, com 11, estavam abaixo dos mínimos recomendados. Salvei versões mais completas e descritivas. A nova auditoria retornou zero ocorrências.

A gravação usa o mesmo `saveSEO()` das alterações por código, passando pelos campos fillable e casts do modelo. Ela **não** passa pelos validadores de tamanho do formulário Filament; a nova auditoria confirma apenas as verificações automáticas. A exatidão editorial ainda exige revisão. Uma auditoria sem ocorrências não certifica a qualidade do texto.

### Consultar o conteúdo do site {#talking-to-your-site-s-content}

As ferramentas de conteúdo permitem responder sobre **o conjunto de páginas**, sem exigir que você indique um registro específico.

> **Você:** Quais páginas mencionam “immunoterapia”? Os títulos estão adequados?

O assistente **pesquisa** com `search_pages { model: "App\\Models\\Page", query: "immunoterapia" }`:

```json
{
  "model": "App\\Models\\Page",
  "query": "immunoterapia",
  "driver": "like",
  "count": 3,
  "results": [
    { "id": 42, "url": "http://localhost/ricerca/immunoterapia-oncologica", "title": "Immunoterapia oncologica", "snippet": "Il programma di immunoterapia dell'IDI: terapie, sperimentazioni e accesso…" },
    { "id": 87, "url": "http://localhost/reparti/dermatologia-oncologica", "title": "Dermatologia oncologica", "snippet": "…include protocolli di immunoterapia per i tumori cutanei avanzati…" },
    { "id": 91, "url": "http://localhost/news/nuova-immunoterapia-2026", "title": "Nuova", "snippet": "Al via una nuova immunoterapia…" }
  ]
}
```

O terceiro título é apenas “Nuova”. O assistente **consulta seus metadados** com `check_meta { model: "App\\Models\\Page", id: 91 }`:

```json
{
  "url": "http://localhost/news/nuova-immunoterapia-2026",
  "title": { "value": "Nuova", "length": 5, "present": true },
  "description": { "value": null, "length": 0, "present": false },
  "og_image": { "value": null, "present": false },
  "issue_count": 2,
  "issues": [
    { "code": "title_too_short", "severity": "notice", "field": "title", "message": "Title is only 5 characters (recommended min 30)." },
    { "code": "missing_description", "severity": "warning", "field": "description", "message": "No meta description is set." }
  ]
}
```

> **Assistente:** Três páginas mencionam “immunoterapia”. Duas têm títulos adequados, mas **/news/nuova-immunoterapia-2026** tem um título de cinco caracteres, “Nuova”, e nenhuma meta description. Quer que eu proponha versões mais completas?

Com `allow_edits` ativado, o assistente poderia gravar a correção por `seo_save_meta`; em leitura, fica na recomendação. Aqui, `search_pages` usou SQL `LIKE`, identificado por `"driver": "like"`, porque o modelo não estava indexado no Scout. Ao adicionar [Laravel Scout](https://laravel.com/docs/scout), a mesma ferramenta passa a usar o mecanismo de pesquisa configurado.

## Segurança {#security}

Três controles formam a configuração padrão. Altere-os deliberadamente quando necessário.

### 1. Edição desativada por padrão {#_1-edits-are-gated-off-by-default}

A ferramenta de gravação fica invisível e indisponível até a ativação:

```php
// config/seo-pro.php
'mcp' => [
    'allow_edits' => true,   // default: false
],
```

Com `allow_edits` desativado, `seo_save_meta` **não aparece em `tools/list`**, e uma chamada direta retorna o erro JSON-RPC `-32602`. Ative apenas para um cliente e um banco de dados em que você confie.

### 2. Lista de modelos permitidos {#_2-the-model-allowlist}

Ferramentas de leitura **e** gravação limitadas a modelos só podem acessar classes com `HasSEO` incluídas na lista. O cliente não pode apontá-las para uma classe arbitrária, como `User` ou um modelo financeiro:

```php
'mcp' => [
    'models' => [
        \App\Models\Post::class,
        \App\Models\Page::class,
    ],
],
```

Uma classe fora da lista retorna um erro legível, como `Model [App\Models\User] is not in the MCP allowlist`, sem acessá-la. Quando `models` está vazio, são usados `seo.audit.models` ou `seo.sitemap.models`, mantendo o escopo dos modelos já configurados no pacote.

### 3. Transporte apenas por stdio {#_3-stdio-only-—-nothing-is-exposed-to-the-network}

O cliente cria um processo local e troca JSON-RPC por **stdio**. O servidor MCP não abre listener HTTP, porta ou socket para conexões remotas. STDOUT fica reservado ao protocolo; diagnósticos vão para STDERR, onde o cliente os registra. No Claude Desktop, por exemplo, podem aparecer em `%APPDATA%\Claude\logs\mcp-server-rankbeam-seo.log`. Essa separação evita que mensagens de diagnóstico corrompam o fluxo do protocolo.

::: warning Edição equivale a acesso de gravação ao banco
`allow_edits` permite que o assistente conectado altere o SEO no banco usado pelo comando. Durante testes, use ambiente local ou staging, limite os modelos e desative a edição ao terminar. `'enabled' => false` impede o comando de iniciar. O transporte stdio local não impede que o cliente de IA envie resultados ao seu próprio provedor; confira também a configuração de dados desse cliente.
:::

## Configuração {#configuration}

```php
// config/seo-pro.php
'mcp' => [
    'enabled'     => true,           // master switch; the command refuses to run when false
    'allow_edits' => false,          // expose + permit the ops tools + seo_save_meta
    'models'      => [],             // allowlist; [] = fall back to audit/sitemap models
    'server_name' => 'rankbeam-seo', // reported in the MCP initialize handshake

    // Optional Server Card discovery route (off by default) — see below.
    'server_card' => [
        'enabled'     => false,      // serve GET {path} with the discovery card
        'path'        => '.well-known/mcp/server-card.json',
        'name'        => null,       // reverse-DNS server name (null = derived from app.url)
        'schema_url'  => 'https://modelcontextprotocol.io/schemas/draft/server-card.json',
        'website_url' => null,       // optional homepage/docs URL stamped on the card
    ],
],
```

## Server Card: descoberta experimental baseada em proposta {#server-card-discovery-—-experimental-draft-spec}

Um **MCP Server Card** é um pequeno documento JSON em uma URL conhecida, usado para descobrir nome, versão e recursos de um servidor antes da conexão. O Rankbeam pode servir esse documento para indicar às ferramentas de agentes que o site tem um servidor MCP. O recurso vem **desativado** e sua ativação não altera os demais comportamentos.

```php
// config/seo-pro.php
'mcp' => [
    'server_card' => [
        'enabled' => true,   // default: false
    ],
],
```

Quando ativado, `GET /.well-known/mcp/server-card.json` retorna um documento como:

```json
{
  "$schema": "https://modelcontextprotocol.io/schemas/draft/server-card.json",
  "name": "com.example/rankbeam-seo",
  "version": "1.0.0",
  "title": "Rankbeam SEO MCP server",
  "description": "Read — and optionally edit — this site's SEO over the Model Context Protocol…",
  "_meta": {
    "io.rankbeam.seo/transport": "stdio",
    "io.rankbeam.seo/launch": "php artisan seo-pro:mcp",
    "io.rankbeam.seo/tool_count": 10,
    "io.rankbeam.seo/tools": [ { "name": "seo_resolve", "description": "…" } ]
  }
}
```

O documento lista apenas as ferramentas **atualmente habilitadas**. Um servidor de leitura não anuncia as ferramentas operacionais ou de edição restritas.

::: warning Especificação ainda em proposta
O formato segue a proposta **MCP Server Card**, [SEP-2127](https://github.com/modelcontextprotocol/modelcontextprotocol/pull/2127), aberta e ainda não incorporada na revisão de 10 de setembro de 2026. Caminho conhecido, URL de `$schema` e campos **não são definitivos**; por isso `path`, `schema_url`, `name` e `website_url` são configuráveis. O servidor usa **stdio**, por `php artisan seo-pro:mcp`, e o documento não contém um bloco HTTP `remotes`. É uma indicação de descoberta, não um endpoint HTTP de conexão. Confira o formato aceito pelo cliente antes de depender dele e mantenha-o desativado se não precisar.
:::

## Notas do protocolo {#protocol-notes}

O servidor implementa diretamente as operações JSON-RPC 2.0 necessárias às ferramentas: `initialize`, com negociação de versão e recursos, `tools/list`, `tools/call` e `ping`. Anuncia a versão `2025-06-18` e entende `2025-03-26` e `2024-11-05`. Métodos desconhecidos retornam `-32601`; linhas malformadas, `-32700`. Falhas de **ferramenta** são resultados com `isError`, legíveis pelo assistente, não erros de transporte. Notificações sem `id`, como `notifications/initialized`, não recebem resposta.

## Uso sem painel e extensões {#headless-extending}

`SeoPro::mcp()` retorna o registro de ferramentas para inspeção ou inclusão de ferramentas próprias:

```php
use Rankbeam\Seo\Pro\Facades\SeoPro;

SeoPro::mcp()->all();                 // enabled tools, keyed by name
SeoPro::mcp()->register($myCustomTool); // any Rankbeam\Seo\Pro\Mcp\McpTool
```

Uma ferramenta implementa `McpTool`, com `name`, `description`, `inputSchema`, `isEnabled` e `handle`. Estenda `AbstractTool` para reutilizar a resolução pela lista de modelos permitidos e seus controles de acesso.
